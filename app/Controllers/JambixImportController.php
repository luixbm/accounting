<?php

namespace App\Controllers;

use App\Libraries\Import\JambixImporter;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\AccountModel;
use App\Models\ImportBatchModel;

/**
 * Jambix booking import wizard: upload -> map columns -> preview -> commit.
 * Groups the export into budget-cost purchase invoices. Lives at /purchases/jambix.
 */
class JambixImportController extends BaseController
{
    private ImportBatchModel $batches;
    private JambixImporter $importer;

    public function __construct()
    {
        $this->batches  = model(ImportBatchModel::class);
        $this->importer = new JambixImporter();
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
    }

    private function guard(): bool
    {
        return user_can('journal.create');
    }

    private function deny(string $msg = 'Not allowed.')
    {
        return redirect()->to('purchases/jambix')->with('error', $msg);
    }

    private function batchOr404(int $id): ?array
    {
        $b = $this->batches->find($id);

        return $b && ($b['kind'] ?? '') === 'jambix' ? $b : null;
    }

    private function gridFor(array $batch): array
    {
        return (new SpreadsheetReader())->grid($batch['stored_path'], $batch['sheet'] ?: null);
    }

    private function fallbackCode(): string
    {
        return (string) (acc_setting('jambixFallbackAcct') ?: JambixImporter::DEFAULT_FALLBACK_CODE);
    }

    // ------------------------------------------------------------------ list + upload

    public function index()
    {
        $all = $this->batches->orderBy('id', 'DESC')->findAll(30);

        return view('purchases/jambix/index', [
            'title'   => 'Import Jambix bookings',
            'batches' => array_values(array_filter($all, static fn ($b) => ($b['kind'] ?? '') === 'jambix')),
        ]);
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
            'kind'        => 'jambix',
            'filename'    => $file->getClientName(),
            'stored_path' => $path,
            'sheet'       => $sheets[0] ?? null,
            'status'      => 'uploaded',
            'options'     => json_encode(['headerRow' => 1, 'map' => [], 'fallback' => $this->fallbackCode()]),
            'created_by'  => auth()->id(),
        ], true);

        return redirect()->to("purchases/jambix/{$id}/map");
    }

    // ------------------------------------------------------------------ step: mapping

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

        return view('purchases/jambix/map', [
            'title'    => 'Import Jambix · Map columns',
            'batch'    => $batch,
            'sheets'   => $sheets,
            'opt'      => $opt,
            'headers'  => $headers,
            'map'      => $map,
            'sample'   => array_slice($this->importer->dataRows($grid, $headerRow), 0, 6),
            'fields'   => JambixImporter::FIELDS,
            'accounts' => model(AccountModel::class)->where('is_group', 0)->where('is_active', 1)->orderBy('code')->findAll(),
            'fallback' => (string) ($opt['fallback'] ?? $this->fallbackCode()),
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
        foreach (array_keys(JambixImporter::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }
        $missing = [];
        foreach (JambixImporter::FIELDS as $field => [$label, $required]) {
            if ($required && ! isset($map[$field])) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            return redirect()->back()->withInput()->with('error', 'Please map: ' . implode(', ', $missing));
        }

        $fallback = trim((string) $this->request->getPost('fallback')) ?: JambixImporter::DEFAULT_FALLBACK_CODE;
        acc_setting_set('jambixFallbackAcct', $fallback);

        $this->batches->update($id, ['sheet' => $sheet, 'status' => 'mapped']);
        $this->batches->setOptions($id, ['headerRow' => $headerRow, 'map' => $map, 'fallback' => $fallback]);

        return redirect()->to("purchases/jambix/{$id}/preview");
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
        $this->batches->update($id, ['row_count' => $parsed['summary']['lines_total']]);

        $fallback = (string) ($opt['fallback'] ?? $this->fallbackCode());
        $acct     = model(AccountModel::class)->where('code', $fallback)->first();

        return view('purchases/jambix/preview', [
            'title'     => 'Import Jambix · Preview',
            'batch'     => $batch,
            'parsed'    => $parsed,
            'fallback'  => $fallback,
            'acctLabel' => $acct ? $acct['code'] . ' · ' . $acct['name'] : $fallback . ' (not found!)',
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
            return redirect()->to('purchases')->with('error', 'This batch is already committed. Use Revert first to re-run it.');
        }

        $opt    = $this->batches->options($batch);
        $parsed = $this->parseBatch($batch);
        $res    = $this->importer->commit($parsed, $id, (string) ($opt['fallback'] ?? $this->fallbackCode()));

        $this->batches->update($id, [
            'status'        => 'committed',
            'journal_count' => $res['invoices_posted'] + $res['invoices_draft'],
            'skipped_count' => $res['invoices_skipped'],
            'committed_at'  => date('Y-m-d H:i:s'),
        ]);
        if ($res['post_errors']) {
            $this->batches->setOptions($id, $opt + ['commit_errors' => $res['post_errors']]);
        }

        $msg = sprintf(
            'Jambix import done: %d invoices posted, %d saved as draft, %d skipped. %d supplier(s), %d customer(s), %d job(s) created, %d lines written.',
            $res['invoices_posted'],
            $res['invoices_draft'],
            $res['invoices_skipped'],
            $res['suppliers'],
            $res['customers'],
            $res['jobs'],
            $res['lines_written']
        );

        return redirect()->to('purchases')->with($res['post_errors'] ? 'error' : 'message', $msg
            . ($res['post_errors'] ? ' Some rows had issues — see the import batch for details.' : ''));
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
        $res = $this->importer->revert($id);
        $this->batches->update($id, ['status' => $res['kept'] > 0 ? 'committed' : 'reverted']);

        return redirect()->to('purchases/jambix')->with(
            'message',
            sprintf('Reverted: %d invoice(s) deleted, %d kept (paid or locked).', $res['deleted'], $res['kept'])
        );
    }
}
