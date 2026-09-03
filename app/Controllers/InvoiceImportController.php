<?php

namespace App\Controllers;

use App\Libraries\Accounting\PurchasePoster;
use App\Libraries\Accounting\SalesPoster;
use App\Libraries\Import\InvoiceImporter;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\AccountAliasModel;
use App\Models\AccountModel;
use App\Models\ImportBatchModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SalesInvoiceModel;

class InvoiceImportController extends BaseController
{
    private ImportBatchModel $batches;

    public function __construct()
    {
        $this->batches = model(ImportBatchModel::class);
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
    }

    private function base(string $kind): string
    {
        return $kind === 'sales' ? 'sales/import' : 'purchases/import';
    }

    private function deny(string $kind, string $msg = 'Not allowed.')
    {
        return redirect()->to($this->base($kind))->with('error', $msg);
    }

    private function batchFor(string $kind, int $id): ?array
    {
        $b = $this->batches->find($id);

        return ($b && $b['kind'] === $kind) ? $b : null;
    }

    private function gridFor(array $batch): array
    {
        return (new SpreadsheetReader())->grid($batch['stored_path'], $batch['sheet'] ?: null);
    }

    private function invoiceModel(string $kind)
    {
        return $kind === 'sales' ? model(SalesInvoiceModel::class) : model(PurchaseInvoiceModel::class);
    }

    // ------------------------------------------------------------------ list + upload

    public function index(string $kind)
    {
        return view('imports/invoice/index', [
            'title'   => ucfirst($kind) . ' Import',
            'kind'    => $kind,
            'base'    => $this->base($kind),
            'batches' => $this->batches->where('kind', $kind)->orderBy('id', 'DESC')->findAll(30),
        ]);
    }

    public function upload(string $kind)
    {
        if (! user_can('journal.create')) {
            return $this->deny($kind);
        }
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Choose a .xlsx or .csv file.');
        }
        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->back()->with('error', 'Only .xlsx, .xls or .csv files are supported.');
        }

        $dir = WRITEPATH . 'uploads/imports';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $newName = $file->getRandomName();
        $file->move($dir, $newName);
        $path = $dir . DIRECTORY_SEPARATOR . $newName;

        try {
            $sheets = (new SpreadsheetReader())->sheetNames($path);
        } catch (\Throwable $e) {
            @unlink($path);

            return redirect()->back()->with('error', 'Could not read that file: ' . $e->getMessage());
        }

        $id = $this->batches->insert([
            'kind'        => $kind,
            'filename'    => $file->getClientName(),
            'stored_path' => $path,
            'sheet'       => $sheets[0] ?? null,
            'status'      => 'uploaded',
            'options'     => json_encode(['headerRow' => 1, 'dateFormat' => 'auto', 'map' => []]),
            'created_by'  => auth()->id(),
        ], true);

        return redirect()->to($this->base($kind) . "/{$id}/map");
    }

    // ------------------------------------------------------------------ step: map

    public function map(string $kind, int $id)
    {
        $batch = $this->batchFor($kind, $id);
        if (! $batch) {
            return $this->deny($kind, 'Import not found.');
        }
        $opt      = $this->batches->options($batch);
        $importer = new InvoiceImporter($kind);
        $grid     = $this->gridFor($batch);
        $hr       = (int) ($opt['headerRow'] ?? 1);

        return view('imports/invoice/map', [
            'title'   => ucfirst($kind) . ' Import · Map columns',
            'kind'    => $kind,
            'base'    => $this->base($kind),
            'batch'   => $batch,
            'sheets'  => (new SpreadsheetReader())->sheetNames($batch['stored_path']),
            'opt'     => $opt,
            'headers' => $importer->headers($grid, $hr),
            'sample'  => array_slice($importer->dataRows($grid, $hr), 0, 6),
            'fields'  => InvoiceImporter::FIELDS,
        ]);
    }

    public function saveMap(string $kind, int $id)
    {
        if (! user_can('journal.create')) {
            return $this->deny($kind);
        }
        $batch = $this->batchFor($kind, $id);
        if (! $batch) {
            return $this->deny($kind, 'Import not found.');
        }

        $map = [];
        foreach (array_keys(InvoiceImporter::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }
        $missing = [];
        foreach (InvoiceImporter::FIELDS as $field => [$label, $required]) {
            if ($required && ! isset($map[$field])) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            return redirect()->back()->withInput()->with('error', 'Please map: ' . implode(', ', $missing));
        }

        $this->batches->update($id, ['sheet' => $this->request->getPost('sheet') ?: $batch['sheet'], 'status' => 'mapped']);
        $this->batches->setOptions($id, [
            'headerRow'  => max(1, (int) $this->request->getPost('header_row')),
            'dateFormat' => $this->request->getPost('date_format') ?: 'auto',
            'map'        => $map,
        ]);

        return redirect()->to($this->base($kind) . "/{$id}/accounts");
    }

    // ------------------------------------------------------------------ step: accounts

    public function accounts(string $kind, int $id)
    {
        $batch = $this->batchFor($kind, $id);
        if (! $batch) {
            return $this->deny($kind, 'Import not found.');
        }
        $opt      = $this->batches->options($batch);
        $importer = new InvoiceImporter($kind);
        $labels   = $importer->distinctAccountLabels($this->gridFor($batch), (int) $opt['headerRow'], $opt['map']);

        $aliasBy  = model(AccountAliasModel::class)->map();
        $accounts = model(AccountModel::class)->orderBy('code')->findAll();
        $byCode   = $byName = [];
        foreach ($accounts as $a) {
            if ((int) $a['is_group'] === 1) {
                continue;
            }
            $byCode[AccountAliasModel::normalise($a['code'])] = (int) $a['id'];
            $byName[AccountAliasModel::normalise($a['name'])] = (int) $a['id'];
        }
        $rows = [];
        foreach ($labels as $label) {
            $norm  = AccountAliasModel::normalise($label);
            $alias = $aliasBy[$norm] ?? null;
            $guess = $byCode[$norm] ?? $byName[$norm] ?? null;
            $rows[] = ['label' => $label, 'accountId' => $alias ?? $guess, 'auto' => $alias === null && $guess !== null];
        }

        return view('imports/invoice/accounts', [
            'title'    => ucfirst($kind) . ' Import · Match accounts',
            'kind'     => $kind,
            'base'     => $this->base($kind),
            'batch'    => $batch,
            'rows'     => $rows,
            'accounts' => $accounts,
        ]);
    }

    public function saveAccounts(string $kind, int $id)
    {
        if (! user_can('journal.create')) {
            return $this->deny($kind);
        }
        if (! $this->batchFor($kind, $id)) {
            return $this->deny($kind, 'Import not found.');
        }
        $labels    = (array) $this->request->getPost('label');
        $accountId = (array) $this->request->getPost('account_id');
        $alias     = model(AccountAliasModel::class);
        $saved     = 0;
        foreach ($labels as $i => $label) {
            $aid = (int) ($accountId[$i] ?? 0);
            if ($label !== '' && $aid > 0) {
                $alias->put($label, $aid);
                $saved++;
            }
        }

        return redirect()->to($this->base($kind) . "/{$id}/preview")->with('message', "{$saved} account mapping(s) saved.");
    }

    // ------------------------------------------------------------------ preview + commit

    private function parseBatch(string $kind, array $batch): array
    {
        $opt = $this->batches->options($batch);

        return (new InvoiceImporter($kind))->parse($this->gridFor($batch), $opt['map'], $opt);
    }

    public function preview(string $kind, int $id)
    {
        $batch = $this->batchFor($kind, $id);
        if (! $batch) {
            return $this->deny($kind, 'Import not found.');
        }
        $parsed = $this->parseBatch($kind, $batch);
        $this->batches->update($id, ['row_count' => $parsed['summary']['lines']]);

        return view('imports/invoice/preview', [
            'title'  => ucfirst($kind) . ' Import · Preview',
            'kind'   => $kind,
            'base'   => $this->base($kind),
            'batch'  => $batch,
            'parsed' => $parsed,
        ]);
    }

    public function commit(string $kind, int $id)
    {
        if (! user_can('journal.create')) {
            return $this->deny($kind);
        }
        $batch = $this->batchFor($kind, $id);
        if (! $batch) {
            return $this->deny($kind, 'Import not found.');
        }
        if ($batch['status'] === 'committed') {
            return redirect()->to($this->base($kind) . "/{$id}")->with('error', 'Already committed.');
        }

        $parsed  = $this->parseBatch($kind, $batch);
        $canPost = user_can('journal.post');
        $res     = (new InvoiceImporter($kind))->commit($id, $parsed, (int) auth()->id(), $canPost);

        $party = $kind === 'sales' ? 'customers' : 'suppliers';
        if (! $canPost) {
            $msg = sprintf('%d invoices imported as draft (%d skipped). Created %d %s.',
                $res['invoices'], $res['skipped'], $res['parties'], $party);
        } else {
            $msg = sprintf('%d invoices imported, %d posted%s (%d skipped). Created %d %s.',
                $res['invoices'], $res['posted'],
                $res['post_failed'] ? sprintf(', %d kept as draft (could not post — open them to see why)', $res['post_failed']) : '',
                $res['skipped'], $res['parties'], $party);
        }

        return redirect()->to($this->base($kind) . "/{$id}")->with('message', $msg);
    }

    // ------------------------------------------------------------------ batch detail

    public function show(string $kind, int $id)
    {
        $batch = $this->batchFor($kind, $id);
        if (! $batch) {
            return $this->deny($kind, 'Import not found.');
        }
        $invoices = $this->invoiceModel($kind)->where('import_batch_id', $id)->orderBy('id', 'ASC')->findAll();
        $counts   = ['draft' => 0, 'posted' => 0, 'partial' => 0, 'paid' => 0, 'void' => 0];
        foreach ($invoices as $inv) {
            $counts[$inv['status']] = ($counts[$inv['status']] ?? 0) + 1;
        }

        return view('imports/invoice/batch', [
            'title'    => ucfirst($kind) . ' import batch #' . $id,
            'kind'     => $kind,
            'base'     => $this->base($kind),
            'batch'    => $batch,
            'invoices' => $invoices,
            'counts'   => $counts,
        ]);
    }

    public function postAll(string $kind, int $id)
    {
        if (! user_can('journal.post')) {
            return $this->deny($kind, 'You cannot post.');
        }
        $drafts = $this->invoiceModel($kind)->where('import_batch_id', $id)->where('status', 'draft')->findAll();
        $poster = $kind === 'sales' ? new SalesPoster() : new PurchasePoster();
        $ok     = $fail = 0;
        foreach ($drafts as $inv) {
            $r = $poster->postInvoice((int) $inv['id']);
            $r['ok'] ? $ok++ : $fail++;
        }

        return redirect()->to($this->base($kind) . "/{$id}")->with('message', "Posted {$ok} invoice(s); {$fail} failed (open them to see why).");
    }

    public function revert(string $kind, int $id)
    {
        if (! user_can('journal.delete')) {
            return $this->deny($kind, 'You cannot delete.');
        }
        $model   = $this->invoiceModel($kind);
        $drafts  = $model->where('import_batch_id', $id)->where('status', 'draft')->findAll();
        $poster  = $kind === 'sales' ? new SalesPoster() : new PurchasePoster();
        $deleted = 0;
        foreach ($drafts as $inv) {
            if ($poster->deleteDraft((int) $inv['id'])['ok']) {
                $deleted++;
            }
        }
        $remaining = $model->where('import_batch_id', $id)->countAllResults();
        $this->batches->update($id, ['status' => $remaining === 0 ? 'reverted' : 'committed']);

        return redirect()->to($this->base($kind) . "/{$id}")->with('message', "Deleted {$deleted} draft invoice(s).");
    }
}
