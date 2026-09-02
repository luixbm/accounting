<?php

namespace App\Controllers;

use App\Libraries\Import\JobImporter;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\ImportBatchModel;

/**
 * Jambix job / dossier import wizard: upload -> map columns -> preview -> commit.
 * Upserts rows into `jobs` (keyed on the dossier number). Lives at /jobs/import.
 *
 * These rows carry reference figures only (Jambix quoted sales / buy, pax,
 * category). The job P&L reports keep reading the posted journal transactions -
 * nothing here feeds the ledger.
 */
class JobImportController extends BaseController
{
    private ImportBatchModel $batches;
    private JobImporter $importer;

    public function __construct()
    {
        $this->batches  = model(ImportBatchModel::class);
        $this->importer = new JobImporter();
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
    }

    private function guard(): bool
    {
        return user_can('masterdata.manage') || user_can('journal.create');
    }

    private function deny(string $msg = 'Not allowed.')
    {
        return redirect()->to('jobs/import')->with('error', $msg);
    }

    private function batchOr404(int $id): ?array
    {
        $b = $this->batches->find($id);

        return $b && ($b['kind'] ?? '') === 'jobs' ? $b : null;
    }

    private function gridFor(array $batch): array
    {
        return (new SpreadsheetReader())->grid($batch['stored_path'], $batch['sheet'] ?: null);
    }

    // ------------------------------------------------------------------ list + upload

    public function index()
    {
        $all = $this->batches->orderBy('id', 'DESC')->findAll(30);

        return view('jobs/import/index', [
            'title'   => 'Import Jambix jobs',
            'batches' => array_values(array_filter($all, static fn ($b) => ($b['kind'] ?? '') === 'jobs')),
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
            'kind'        => 'jobs',
            'filename'    => $file->getClientName(),
            'stored_path' => $path,
            'sheet'       => $sheets[0] ?? null,
            'status'      => 'uploaded',
            'options'     => json_encode(['headerRow' => 1, 'map' => []]),
            'created_by'  => auth()->id(),
        ], true);

        return redirect()->to("jobs/import/{$id}/map");
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

        return view('jobs/import/map', [
            'title'   => 'Import Jambix jobs · Map columns',
            'batch'   => $batch,
            'sheets'  => $sheets,
            'opt'     => $opt,
            'headers' => $headers,
            'map'     => $map,
            'sample'  => array_slice($this->importer->dataRows($grid, $headerRow), 0, 6),
            'fields'  => JobImporter::FIELDS,
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
        foreach (array_keys(JobImporter::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }
        $missing = [];
        foreach (JobImporter::FIELDS as $field => [$label, $required]) {
            if ($required && ! isset($map[$field])) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            return redirect()->back()->withInput()->with('error', 'Please map: ' . implode(', ', $missing));
        }

        $this->batches->update($id, ['sheet' => $sheet, 'status' => 'mapped']);
        $this->batches->setOptions($id, ['headerRow' => $headerRow, 'map' => $map]);

        return redirect()->to("jobs/import/{$id}/preview");
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
        $parsed = $this->parseBatch($batch);
        $this->batches->update($id, ['row_count' => $parsed['summary']['rows']]);

        return view('jobs/import/preview', [
            'title'  => 'Import Jambix jobs · Preview',
            'batch'  => $batch,
            'parsed' => $parsed,
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
            return redirect()->to('jobs')->with('error', 'This batch is already committed. Use Revert first to re-run it.');
        }

        $parsed = $this->parseBatch($batch);
        $res    = $this->importer->commit($parsed, $id);

        $this->batches->update($id, [
            'status'        => 'committed',
            'journal_count' => $res['created'] + $res['updated'],
            'skipped_count' => $res['skipped'],
            'committed_at'  => date('Y-m-d H:i:s'),
        ]);

        $msg = sprintf(
            'Job import done: %d job(s) created, %d updated, %d skipped. %d customer(s) created.',
            $res['created'],
            $res['updated'],
            $res['skipped'],
            $res['customers']
        );

        return redirect()->to('jobs')->with('message', $msg);
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

        return redirect()->to('jobs/import')->with(
            'message',
            sprintf('Reverted: %d job(s) deleted, %d kept (already used on a transaction). Updated jobs are left as-is.', $res['deleted'], $res['kept'])
        );
    }
}
