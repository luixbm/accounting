<?php

namespace App\Controllers;

use App\Libraries\Accounting\JournalPoster;
use App\Libraries\Import\JournalImporter;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\AccountAliasModel;
use App\Models\AccountModel;
use App\Models\ImportBatchModel;
use App\Models\JournalModel;

class ImportController extends BaseController
{
    private ImportBatchModel $batches;
    private JournalImporter $importer;

    public function __construct()
    {
        $this->batches  = model(ImportBatchModel::class);
        $this->importer = new JournalImporter();
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
    }

    private function deny(string $msg = 'Not allowed.')
    {
        return redirect()->to('journals/import')->with('error', $msg);
    }

    private function batchOr404(int $id): ?array
    {
        return $this->batches->find($id);
    }

    private function gridFor(array $batch): array
    {
        return (new SpreadsheetReader())->grid($batch['stored_path'], $batch['sheet'] ?: null);
    }

    // ------------------------------------------------------------------ list + upload

    public function index()
    {
        return view('journals/import/index', [
            'title'   => 'Import Journals',
            'batches' => $this->batches->recent(),
        ]);
    }

    public function upload()
    {
        if (! user_can('journal.create')) {
            return $this->deny();
        }
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Choose a .xlsx or .csv file.');
        }
        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
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
            'filename'    => $file->getClientName(),
            'stored_path' => $path,
            'sheet'       => $sheets[0] ?? null,
            'status'      => 'uploaded',
            'options'     => json_encode(['headerRow' => 1, 'dateFormat' => 'auto', 'defaultSource' => 'general', 'map' => []]),
            'created_by'  => auth()->id(),
        ], true);

        return redirect()->to("journals/import/{$id}/map");
    }

    // ------------------------------------------------------------------ step: column mapping

    public function map(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $opt    = $this->batches->options($batch);
        $sheets = (new SpreadsheetReader())->sheetNames($batch['stored_path']);

        try {
            $grid = $this->gridFor($batch);
        } catch (\Throwable $e) {
            return $this->deny('Could not read the sheet: ' . $e->getMessage());
        }

        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $headers   = $this->importer->headers($grid, $headerRow);
        $sample    = array_slice($this->importer->dataRows($grid, $headerRow), 0, 6);

        return view('journals/import/map', [
            'title'     => 'Import · Map columns',
            'batch'     => $batch,
            'sheets'    => $sheets,
            'opt'       => $opt,
            'headers'   => $headers,
            'sample'    => $sample,
            'fields'    => JournalImporter::FIELDS,
            'sources'   => JournalModel::SOURCES,
        ]);
    }

    public function saveMap(int $id)
    {
        if (! user_can('journal.create')) {
            return $this->deny();
        }
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }

        $sheet     = $this->request->getPost('sheet') ?: $batch['sheet'];
        $headerRow = max(1, (int) $this->request->getPost('header_row'));
        $map       = [];
        foreach (array_keys(JournalImporter::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }

        $missing = [];
        foreach (JournalImporter::FIELDS as $field => [$label, $required]) {
            if ($required && ! isset($map[$field])) {
                $missing[] = $label;
            }
        }
        if (! isset($map['debit']) && ! isset($map['credit'])) {
            $missing[] = 'Debit or Credit';
        }
        if ($missing) {
            return redirect()->back()->withInput()->with('error', 'Please map: ' . implode(', ', array_unique($missing)));
        }

        $this->batches->update($id, ['sheet' => $sheet, 'status' => 'mapped']);
        $this->batches->setOptions($id, [
            'headerRow'     => $headerRow,
            'dateFormat'    => $this->request->getPost('date_format') ?: 'auto',
            'defaultSource' => $this->request->getPost('default_source') ?: 'general',
            'map'           => $map,
        ]);

        return redirect()->to("journals/import/{$id}/accounts");
    }

    // ------------------------------------------------------------------ step: account aliases

    public function accounts(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $opt  = $this->batches->options($batch);
        $grid = $this->gridFor($batch);

        $labels  = $this->importer->distinctAccountLabels($grid, (int) $opt['headerRow'], $opt['map']);
        $aliasBy = model(AccountAliasModel::class)->map();
        $accounts = model(AccountModel::class)->orderBy('code')->findAll();
        $byCode   = [];
        $byName   = [];
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
            $rows[] = [
                'label'     => $label,
                'norm'      => $norm,
                'accountId' => $alias ?? $guess,
                'auto'      => $alias === null && $guess !== null,
            ];
        }

        return view('journals/import/accounts', [
            'title'    => 'Import · Match accounts',
            'batch'    => $batch,
            'rows'     => $rows,
            'accounts' => $accounts,
        ]);
    }

    public function saveAccounts(int $id)
    {
        if (! user_can('journal.create')) {
            return $this->deny();
        }
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }

        $labels   = (array) $this->request->getPost('label');
        $accountId = (array) $this->request->getPost('account_id');
        $alias    = model(AccountAliasModel::class);
        $saved    = 0;
        foreach ($labels as $i => $label) {
            $aid = (int) ($accountId[$i] ?? 0);
            if ($label !== '' && $aid > 0) {
                $alias->put($label, $aid);
                $saved++;
            }
        }

        return redirect()->to("journals/import/{$id}/preview")->with('message', "{$saved} account mapping(s) saved.");
    }

    // ------------------------------------------------------------------ step: preview + commit

    private function parseBatch(array $batch): array
    {
        $opt  = $this->batches->options($batch);
        $grid = $this->gridFor($batch);

        return $this->importer->parse($grid, $opt['map'], $opt);
    }

    public function preview(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }

        $parsed = $this->parseBatch($batch);
        $this->batches->update($id, ['row_count' => $parsed['summary']['lines']]);

        return view('journals/import/preview', [
            'title'  => 'Import · Preview',
            'batch'  => $batch,
            'parsed' => $parsed,
        ]);
    }

    public function commit(int $id)
    {
        if (! user_can('journal.create')) {
            return $this->deny();
        }
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        if ($batch['status'] === 'committed') {
            return redirect()->to("journals/import/{$id}")->with('error', 'This batch was already committed.');
        }

        $parsed = $this->parseBatch($batch);
        $result = $this->importer->commit($id, $parsed, (int) auth()->id());

        return redirect()->to("journals/import/{$id}")->with(
            'message',
            sprintf(
                '%d journals imported as draft (%d skipped). Created %d customers, %d suppliers.',
                $result['journals'],
                $result['skipped'],
                $result['customers'],
                $result['suppliers']
            )
        );
    }

    // ------------------------------------------------------------------ batch detail

    public function show(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $journals = model(JournalModel::class)
            ->where('import_batch_id', $id)
            ->orderBy('id', 'ASC')
            ->findAll();

        $counts = ['draft' => 0, 'posted' => 0, 'void' => 0];
        foreach ($journals as $j) {
            $counts[$j['status']] = ($counts[$j['status']] ?? 0) + 1;
        }

        return view('journals/import/batch', [
            'title'    => 'Import batch #' . $id,
            'batch'    => $batch,
            'journals' => $journals,
            'counts'   => $counts,
        ]);
    }

    public function postAll(int $id)
    {
        if (! user_can('journal.post')) {
            return $this->deny('You cannot post journals.');
        }
        $drafts = model(JournalModel::class)->where('import_batch_id', $id)->where('status', 'draft')->findAll();
        $poster = new JournalPoster();
        $ok     = 0;
        $fail   = 0;
        foreach ($drafts as $j) {
            $r = $poster->post((int) $j['id']);
            $r['ok'] ? $ok++ : $fail++;
        }

        return redirect()->to("journals/import/{$id}")->with('message', "Posted {$ok} journal(s); {$fail} could not be posted (open them to see why).");
    }

    public function revert(int $id)
    {
        if (! user_can('journal.delete')) {
            return $this->deny('You cannot delete journals.');
        }
        $model   = model(JournalModel::class);
        $drafts  = $model->where('import_batch_id', $id)->where('status', 'draft')->findAll();
        $deleted = 0;
        foreach ($drafts as $j) {
            $model->delete($j['id']); // lines cascade
            $deleted++;
        }
        $remaining = $model->where('import_batch_id', $id)->countAllResults();
        $this->batches->update($id, ['status' => $remaining === 0 ? 'reverted' : 'committed']);

        return redirect()->to("journals/import/{$id}")->with(
            'message',
            "Deleted {$deleted} draft journal(s)." . ($remaining ? " {$remaining} posted/void journal(s) were kept." : '')
        );
    }
}
