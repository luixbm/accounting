<?php

namespace App\Controllers;

use App\Libraries\Import\BudgetImporter;
use App\Libraries\Import\SpreadsheetReader;
use App\Models\BudgetVersionModel;
use App\Models\ImportBatchModel;

/**
 * Budget spreadsheet import: upload -> map columns -> preview -> commit into a
 * chosen budget version. Lives at /budgets/import?version=<id>.
 */
class BudgetImportController extends BaseController
{
    private ImportBatchModel $batches;
    private BudgetImporter $importer;

    public function __construct()
    {
        $this->batches  = model(ImportBatchModel::class);
        $this->importer = new BudgetImporter();
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
    }

    private function deny(string $msg = 'Not allowed.')
    {
        return redirect()->to('budgets')->with('error', $msg);
    }

    private function batchOr404(int $id): ?array
    {
        $b = $this->batches->find($id);

        return $b && ($b['kind'] ?? '') === 'budget' ? $b : null;
    }

    private function versionFor(array $batch): ?array
    {
        $vid = (int) ($this->batches->options($batch)['version_id'] ?? 0);

        return $vid ? model(BudgetVersionModel::class)->find($vid) : null;
    }

    private function gridFor(array $batch): array
    {
        return (new SpreadsheetReader())->grid($batch['stored_path'], $batch['sheet'] ?: null);
    }

    // ------------------------------------------------------------------ list + upload

    public function index()
    {
        $vid = (int) $this->request->getGet('version');
        $ver = $vid ? model(BudgetVersionModel::class)->find($vid) : null;
        if (! $ver) {
            return redirect()->to('budgets')->with('error', lang('Budget.imp_need_version'));
        }

        $all = $this->batches->orderBy('id', 'DESC')->findAll(20);

        return view('budgets/import/index', [
            'title'   => lang('Budget.imp_title'),
            'ver'     => $ver,
            'batches' => array_values(array_filter($all, static fn ($b) => ($b['kind'] ?? '') === 'budget')),
        ]);
    }

    public function upload()
    {
        $vid = (int) $this->request->getPost('version_id');
        $ver = $vid ? model(BudgetVersionModel::class)->find($vid) : null;
        if (! $ver) {
            return $this->deny(lang('Budget.imp_need_version'));
        }
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', lang('Budget.imp_choose'));
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
            'kind'        => 'budget',
            'filename'    => $file->getClientName(),
            'stored_path' => $path,
            'sheet'       => $sheets[0] ?? null,
            'status'      => 'uploaded',
            'options'     => json_encode(['headerRow' => 1, 'map' => [], 'version_id' => $vid]),
            'created_by'  => auth()->id(),
        ], true);

        return redirect()->to("budgets/import/{$id}/map");
    }

    // ------------------------------------------------------------------ map

    public function map(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $opt = $this->batches->options($batch);

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

        return view('budgets/import/map', [
            'title'   => lang('Budget.imp_map'),
            'batch'   => $batch,
            'ver'     => $this->versionFor($batch),
            'sheets'  => (new SpreadsheetReader())->sheetNames($batch['stored_path']),
            'opt'     => $opt + ['map' => $map],
            'headers' => $headers,
            'sample'  => array_slice($this->importer->dataRows($grid, $headerRow), 0, 6),
            'fields'  => BudgetImporter::FIELDS,
        ]);
    }

    public function saveMap(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $sheet     = $this->request->getPost('sheet') ?: $batch['sheet'];
        $headerRow = max(1, (int) $this->request->getPost('header_row'));
        $map       = [];
        foreach (array_keys(BudgetImporter::FIELDS) as $field) {
            $col = $this->request->getPost('map_' . $field);
            if ($col !== null && $col !== '') {
                $map[$field] = (int) $col;
            }
        }
        if (! isset($map['account'])) {
            return redirect()->back()->withInput()->with('error', 'Please map the Account column.');
        }

        $opt = $this->batches->options($batch);
        $this->batches->update($id, ['sheet' => $sheet, 'status' => 'mapped']);
        $this->batches->setOptions($id, ['headerRow' => $headerRow, 'map' => $map, 'version_id' => $opt['version_id'] ?? null]);

        return redirect()->to("budgets/import/{$id}/preview");
    }

    // ------------------------------------------------------------------ preview + commit

    private function parseBatch(array $batch): array
    {
        $opt = $this->batches->options($batch);

        return $this->importer->parse($this->gridFor($batch), $opt['map'] ?? [], $opt);
    }

    public function preview(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $parsed = $this->parseBatch($batch);
        $this->batches->update($id, ['row_count' => $parsed['summary']['total']]);

        return view('budgets/import/preview', [
            'title'  => lang('Budget.imp_preview'),
            'batch'  => $batch,
            'ver'    => $this->versionFor($batch),
            'parsed' => $parsed,
        ]);
    }

    public function commit(int $id)
    {
        $batch = $this->batchOr404($id);
        if (! $batch) {
            return $this->deny('Import not found.');
        }
        $ver = $this->versionFor($batch);
        if (! $ver) {
            return $this->deny(lang('Budget.imp_need_version'));
        }

        $parsed = $this->parseBatch($batch);
        $res    = $this->importer->commit((int) $ver['id'], $parsed);

        $this->batches->update($id, [
            'status'        => 'committed',
            'journal_count' => $res['cells'],
            'skipped_count' => $res['skipped'],
            'committed_at'  => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('budgets/' . $ver['id'])->with(
            'message',
            lang('Budget.imp_done', [$res['accounts'], $res['cells'], $ver['name']])
        );
    }
}
