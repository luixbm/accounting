<?php

namespace App\Controllers;

use App\Libraries\Import\ReceiptImporter;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\AccountModel;
use App\Models\ImportBatchModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Customer settlement import wizard: upload a worked-back list -> map the
 * invoice number + amount columns, choose the mode (bank receipt, or apply an
 * existing customer deposit) -> preview -> commit. Lives at /sales/receipts/import.
 */
class ReceiptImportController extends BaseController
{
    private ImportBatchModel $batches;
    private ReceiptImporter $importer;

    public function __construct()
    {
        $this->batches  = model(ImportBatchModel::class);
        $this->importer = new ReceiptImporter();
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);
    }

    private function guard(): bool
    {
        return user_can('journal.post');
    }

    private function deny(string $msg = 'Not allowed.')
    {
        return redirect()->to('sales/receipts/import')->with('error', $msg);
    }

    private function batchOr404(int $id): ?array
    {
        $b = $this->batches->find($id);

        return $b && ($b['kind'] ?? '') === 'rcpt-import' ? $b : null;
    }

    private function gridFor(array $batch): array
    {
        return (new SpreadsheetReader())->grid($batch['stored_path'], $batch['sheet'] ?: null);
    }

    // ------------------------------------------------------------------ list + upload

    public function index()
    {
        $all = $this->batches->orderBy('id', 'DESC')->findAll(30);

        return view('sales/receipts/import/index', [
            'title'   => 'Import receipts',
            'batches' => array_values(array_filter($all, static fn ($b) => ($b['kind'] ?? '') === 'rcpt-import')),
        ]);
    }

    /** Blank .xlsx with the columns the import expects. */
    public function template()
    {
        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Receipts');
        $sh->fromArray([['Number', 'Amount', 'Customer (ignored)', 'Note (ignored)']], null, 'A1');
        $sh->fromArray([
            ['SI-2609-0001', 1500000, 'example customer', 'full receipt'],
            ['SI-2609-0002', 500000, 'example customer', 'partial'],
        ], null, 'A2');
        $sh->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (range('A', 'D') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }

        $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="receipt-import-template.xlsx"');
        ob_start();
        (new Xlsx($ss))->save('php://output');

        return $this->response->setBody(ob_get_clean());
    }

    public function upload()
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Choose a .xlsx, .xls or .csv file.');
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
            'kind'        => 'rcpt-import',
            'filename'    => $file->getClientName(),
            'stored_path' => $path,
            'sheet'       => $sheets[0] ?? null,
            'status'      => 'uploaded',
            'options'     => json_encode(['headerRow' => 1, 'map' => [], 'header' => ['mode' => 'receipt']]),
            'created_by'  => auth()->id(),
        ], true);

        return redirect()->to("sales/receipts/import/{$id}/map");
    }

    // ------------------------------------------------------------------ step: mapping + batch header

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
        $map       = (array) ($opt['map'] ?? []);
        if (! $map) {
            $map = $this->importer->guessMap($headers);
        }

        return view('sales/receipts/import/map', [
            'title'   => 'Import receipts · Map columns',
            'batch'   => $batch,
            'sheets'  => $sheets,
            'opt'     => $opt,
            'headers' => $headers,
            'map'     => $map,
            'sample'  => array_slice($this->importer->dataRows($grid, $headerRow), 0, 6),
            'fields'  => ReceiptImporter::FIELDS,
            'banks'   => model(AccountModel::class)->cashAccounts(),
            'header'  => (array) ($opt['header'] ?? ['mode' => 'receipt']),
        ]);
    }

    public function saveMap(int $id)
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }

        $sheet     = $this->request->getPost('sheet') ?: $batch['sheet'];
        $headerRow = max(1, (int) $this->request->getPost('header_row'));
        $map       = [];
        foreach (array_keys(ReceiptImporter::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }
        $missing = [];
        foreach (ReceiptImporter::FIELDS as $field => [$label, $required]) {
            if ($required && ! isset($map[$field])) {
                $missing[] = $label;
            }
        }

        $mode   = $this->request->getPost('mode') === 'deposit' ? 'deposit' : 'receipt';
        $header = [
            'mode'            => $mode,
            'bank_account_id' => (int) $this->request->getPost('bank_account_id'),
            'date'            => $this->request->getPost('date') ?: date('Y-m-d'),
            'reference'       => trim((string) $this->request->getPost('reference')) ?: null,
        ];
        if ($mode === 'receipt' && ! $header['bank_account_id']) {
            $missing[] = 'Receive into account';
        }
        if ($missing) {
            return redirect()->back()->withInput()->with('error', 'Please set: ' . implode(', ', $missing));
        }

        $this->batches->update($id, ['sheet' => $sheet, 'status' => 'mapped']);
        $this->batches->setOptions($id, ['headerRow' => $headerRow, 'map' => $map, 'header' => $header]);

        return redirect()->to("sales/receipts/import/{$id}/preview");
    }

    // ------------------------------------------------------------------ step: preview + commit

    private function parseBatch(array $batch): array
    {
        $opt = $this->batches->options($batch);

        return $this->importer->parse($this->gridFor($batch), (array) ($opt['map'] ?? []), $opt);
    }

    public function preview(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $opt    = $this->batches->options($batch);
        $parsed = $this->parseBatch($batch);
        $this->batches->update($id, ['row_count' => $parsed['summary']['rows']]);

        $bank = model(AccountModel::class)->find((int) ($opt['header']['bank_account_id'] ?? 0));

        return view('sales/receipts/import/preview', [
            'title'  => 'Import receipts · Preview',
            'batch'  => $batch,
            'parsed' => $parsed,
            'header' => (array) ($opt['header'] ?? []),
            'bank'   => $bank ? $bank['code'] . ' · ' . $bank['name'] : '(not set)',
        ]);
    }

    public function commit(int $id)
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        if (($batch['status'] ?? '') === 'committed') {
            return redirect()->to('sales/receipts')->with('error', 'This batch is already committed. Use Revert first to re-run it.');
        }

        $opt    = $this->batches->options($batch);
        $parsed = $this->parseBatch($batch);
        $res    = $this->importer->commit($parsed, $id, (array) ($opt['header'] ?? []));

        $this->batches->update($id, [
            'status'        => 'committed',
            'journal_count' => $res['posts'],
            'skipped_count' => $parsed['summary']['unmatched'],
            'committed_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->batches->setOptions($id, $opt + ['receipt_ids' => $res['receipt_ids'], 'commit_errors' => $res['errors']]);

        $mode = ($opt['header']['mode'] ?? 'receipt');
        $msg  = sprintf(
            '%d %s posted for %d invoice(s), total %s. %d row(s) unmatched.',
            $res['posts'],
            $mode === 'deposit' ? 'deposit application(s)' : 'receipt(s)',
            $res['invoices'],
            number_format($res['amount'], 2),
            $parsed['summary']['unmatched']
        );

        return redirect()->to('sales/receipts')->with($res['errors'] ? 'error' : 'message', $msg
            . ($res['errors'] ? ' Some groups failed — see the import batch.' : ''));
    }

    public function revert(int $id)
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $opt = $this->batches->options($batch);
        if (($opt['header']['mode'] ?? 'receipt') === 'deposit') {
            return redirect()->to('sales/receipts/import')->with('error',
                'Deposit-application batches are reverted from the deposit’s own Unapply screen, not here.');
        }
        $res = $this->importer->revert($id, (array) ($opt['receipt_ids'] ?? []));
        $this->batches->update($id, ['status' => $res['kept'] > 0 ? 'committed' : 'reverted']);

        return redirect()->to('sales/receipts/import')->with(
            'message',
            sprintf('Reverted: %d receipt(s) voided, %d kept (already voided or locked).', $res['voided'], $res['kept'])
        );
    }
}
