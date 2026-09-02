<?php

namespace App\Controllers;

use App\Libraries\Import\CoaImporter;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\AccountModel;
use App\Models\ImportBatchModel;

/**
 * Chart-of-accounts import wizard: upload -> map columns + account types ->
 * preview -> commit (upsert by code). Lives at /accounts/import.
 */
class CoaImportController extends BaseController
{
    private ImportBatchModel $batches;
    private CoaImporter $importer;

    public function __construct()
    {
        $this->batches  = model(ImportBatchModel::class);
        $this->importer = new CoaImporter();
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
    }

    private function deny(string $msg = 'Not allowed.')
    {
        return redirect()->to('accounts/import')->with('error', $msg);
    }

    private function guard(): bool
    {
        return user_can('masterdata.manage');
    }

    private function batchOr404(int $id): ?array
    {
        $b = $this->batches->find($id);

        return $b && ($b['kind'] ?? '') === 'coa' ? $b : null;
    }

    private function gridFor(array $batch): array
    {
        return (new SpreadsheetReader())->grid($batch['stored_path'], $batch['sheet'] ?: null);
    }

    // ------------------------------------------------------------------ list + upload

    public function index()
    {
        $all = $this->batches->orderBy('id', 'DESC')->findAll(20);

        return view('accounts/import/index', [
            'title'   => 'Import Chart of Accounts',
            'batches' => array_values(array_filter($all, static fn ($b) => ($b['kind'] ?? '') === 'coa')),
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
            'kind'        => 'coa',
            'filename'    => $file->getClientName(),
            'stored_path' => $path,
            'sheet'       => $sheets[0] ?? null,
            'status'      => 'uploaded',
            'options'     => json_encode(['headerRow' => 1, 'map' => [], 'typeMap' => []]),
            'created_by'  => auth()->id(),
        ], true);

        return redirect()->to("accounts/import/{$id}/map");
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
        $map       = (array) ($opt['map'] ?? []);
        $rawTypes  = isset($map['type']) ? $this->importer->distinctTypes($grid, $headerRow, $map) : [];
        $typeMap   = (array) ($opt['typeMap'] ?? []);
        if ($rawTypes && ! $typeMap) {
            $typeMap = $this->importer->guessTypeMap($rawTypes);
        }

        return view('accounts/import/map', [
            'title'      => 'Import COA · Map columns',
            'batch'      => $batch,
            'sheets'     => $sheets,
            'opt'        => $opt,
            'headers'    => $this->importer->headers($grid, $headerRow),
            'sample'     => array_slice($this->importer->dataRows($grid, $headerRow), 0, 6),
            'fields'     => CoaImporter::FIELDS,
            'rawTypes'   => $rawTypes,
            'typeMap'    => $typeMap,
            'appTypes'   => AccountModel::TYPES,
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
        foreach (array_keys(CoaImporter::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }

        $missing = [];
        foreach (CoaImporter::FIELDS as $field => [$label, $required]) {
            if ($required && ! isset($map[$field])) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            return redirect()->back()->withInput()->with('error', 'Please map: ' . implode(', ', $missing));
        }

        // collect the account-type map, if the type column was mapped and its
        // distinct values were shown on this submit
        $typeMap = [];
        foreach ((array) $this->request->getPost('type_raw') as $i => $raw) {
            $raw = (string) $raw;
            if ($raw === '') {
                continue;
            }
            $appType = (string) ($this->request->getPost('type_app')[$i] ?? '');
            $isCash  = ! empty($this->request->getPost('type_cash')[$i]);
            if (isset(AccountModel::TYPES[$appType])) {
                $typeMap[$raw] = [$appType, $isCash ? 1 : 0];
            }
        }

        $this->batches->update($id, ['sheet' => $sheet, 'status' => 'mapped']);
        $this->batches->setOptions($id, ['headerRow' => $headerRow, 'map' => $map, 'typeMap' => $typeMap]);

        // If we don't yet have a type map (type column just got mapped), loop back
        // so the type-mapping controls render.
        $grid     = $this->gridFor($this->batchOr404($id));
        $rawTypes = $this->importer->distinctTypes($grid, $headerRow, $map);
        if ($rawTypes && ! $typeMap) {
            return redirect()->to("accounts/import/{$id}/map")->with('message', 'Now map each account type to an app type.');
        }

        return redirect()->to("accounts/import/{$id}/preview");
    }

    // ------------------------------------------------------------------ step: preview + commit

    private function parseBatch(array $batch): array
    {
        return $this->importer->parse($this->gridFor($batch), $this->batches->options($batch)['map'], $this->batches->options($batch));
    }

    public function preview(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $parsed = $this->parseBatch($batch);
        $this->batches->update($id, ['row_count' => $parsed['summary']['total']]);

        return view('accounts/import/preview', [
            'title'  => 'Import COA · Preview',
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

        $parsed = $this->parseBatch($batch);
        $res    = $this->importer->commit($parsed);

        $this->batches->update($id, [
            'status'        => 'committed',
            'journal_count' => $res['inserted'] + $res['updated'],
            'skipped_count' => $res['skipped'],
            'committed_at'  => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('accounts')->with(
            'message',
            sprintf('Chart of accounts imported: %d added, %d updated, %d skipped.', $res['inserted'], $res['updated'], $res['skipped'])
        );
    }
}
