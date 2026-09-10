<?php

namespace App\Libraries\Import;

use App\Libraries\CustomFields;
use CodeIgniter\Model;
use Config\Database;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export / import for the Customer and Supplier master lists. The export is a
 * plain header-row + data .xlsx; feeding that same file (or a CSV with the same
 * headers) back through the import upserts by Code - existing rows are updated,
 * new codes are created. Custom-field columns round-trip by their label.
 */
class PartyPorter
{
    private const MAX_ROWS = 5000;

    /** Base columns for a kind: logical key => spreadsheet header. */
    public static function columns(string $kind): array
    {
        $cols = ['code' => 'Code', 'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'];
        if ($kind === 'customer') {
            $cols += ['client_group' => 'Client group', 'country' => 'Country'];
        }
        $cols += ['npwp' => 'Tax ID / NPWP', 'address' => 'Address', 'is_active' => 'Active'];

        return $cols;
    }

    // ---------------------------------------------------------------- export

    /**
     * @param list<array<string,mixed>>       $rows   party rows (with id)
     * @param list<array<string,mixed>>       $cfDefs custom-field defs
     * @param array<int,array<string,string>> $cfVals record_id => [field_key => value]
     */
    public static function export(string $kind, array $rows, array $cfDefs, array $cfVals): string
    {
        $cols = self::columns($kind);

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle(ucfirst($kind) . 's');

        $c = 1;
        foreach (array_values($cols) as $label) {
            $sheet->setCellValue([$c++, 1], $label);
        }
        foreach ($cfDefs as $d) {
            $sheet->setCellValue([$c++, 1], $d['label']);
        }
        $sheet->getStyle([1, 1, $c - 1, 1])->getFont()->setBold(true);

        $r = 2;
        foreach ($rows as $row) {
            $c = 1;
            foreach (array_keys($cols) as $k) {
                $v = $k === 'is_active' ? (! empty($row['is_active']) ? 'Yes' : 'No') : (string) ($row[$k] ?? '');
                $sheet->setCellValueExplicit([$c++, $r], $v, DataType::TYPE_STRING);
            }
            foreach ($cfDefs as $d) {
                $sheet->setCellValueExplicit([$c++, $r], (string) ($cfVals[$row['id']][$d['field_key']] ?? ''), DataType::TYPE_STRING);
            }
            $r++;
        }
        for ($i = 1; $i < $c; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        ob_start();
        (new Xlsx($ss))->save('php://output');
        $out = (string) ob_get_clean();
        $ss->disconnectWorksheets();

        return $out;
    }

    // ---------------------------------------------------------------- parse

    /**
     * Read a spreadsheet and classify each row against the existing list.
     *
     * @return array{
     *   items: list<array{n:int,action:string,data:array<string,mixed>,cf:array<string,string>,id:?int,msg:?string}>,
     *   counts: array{create:int,update:int,error:int}
     * }
     */
    public static function parse(string $path, string $kind, array $cfDefs, Model $model): array
    {
        $grid = (new SpreadsheetReader())->grid($path, null, self::MAX_ROWS + 50);

        // header row = first of the first 15 rows that has both a "code" and a "name" cell
        $headerRow = -1;
        foreach (array_slice($grid, 0, 15) as $i => $cells) {
            $norm = array_map(static fn ($v) => self::norm((string) $v), $cells);
            if (in_array('code', $norm, true) && in_array('name', $norm, true)) {
                $headerRow = $i;
                break;
            }
        }
        if ($headerRow < 0) {
            return ['items' => [], 'counts' => ['create' => 0, 'update' => 0, 'error' => 0]];
        }

        // map each of our fields to a column index by matching the header text
        $cols   = self::columns($kind);
        $header = array_map(static fn ($v) => self::norm((string) $v), $grid[$headerRow]);
        $colIx  = [];
        foreach ($cols as $key => $label) {
            $want = [self::norm($label), $key];
            foreach ($header as $ix => $h) {
                if ($h !== '' && in_array($h, $want, true)) {
                    $colIx[$key] = $ix;
                    break;
                }
            }
        }
        $cfIx = [];
        foreach ($cfDefs as $d) {
            $want = [self::norm($d['label']), self::norm($d['field_key'])];
            foreach ($header as $ix => $h) {
                if ($h !== '' && in_array($h, $want, true)) {
                    $cfIx[$d['field_key']] = $ix;
                    break;
                }
            }
        }

        // existing codes -> id
        $byCode = [];
        foreach ($model->select('id, code')->findAll() as $e) {
            $byCode[mb_strtolower(trim((string) $e['code']))] = (int) $e['id'];
        }

        $get   = static fn (array $cells, ?int $ix): string => $ix === null ? '' : trim((string) ($cells[$ix] ?? ''));
        $items = [];
        $n     = ['create' => 0, 'update' => 0, 'error' => 0];

        foreach (array_slice($grid, $headerRow + 1) as $offset => $cells) {
            $rowNo = $headerRow + 2 + $offset;
            if (count($items) >= self::MAX_ROWS) {
                break;
            }

            $code = $get($cells, $colIx['code'] ?? null);
            $name = $get($cells, $colIx['name'] ?? null);
            if ($code === '' && $name === '' && trim(implode('', array_map('strval', $cells))) === '') {
                continue; // blank line
            }

            $data = ['name' => $name];
            foreach (['email', 'phone', 'npwp', 'address', 'client_group', 'country'] as $k) {
                if (isset($colIx[$k])) {
                    $v = $get($cells, $colIx[$k]);
                    $data[$k] = $v !== '' ? $v : null;
                }
            }
            if (isset($colIx['is_active'])) {
                $raw           = mb_strtolower($get($cells, $colIx['is_active']));
                $data['is_active'] = in_array($raw, ['no', 'n', '0', 'false', 'inactive', 'tidak'], true) ? 0 : 1;
            }

            $cf = [];
            foreach ($cfIx as $fk => $ix) {
                $v = $get($cells, $ix);
                if ($v !== '') {
                    $cf[$fk] = $v;
                }
            }

            if ($code === '') {
                $items[] = ['n' => $rowNo, 'action' => 'error', 'data' => $data + ['code' => ''], 'cf' => $cf, 'id' => null, 'msg' => 'Code is blank'];
                $n['error']++;
                continue;
            }
            if ($name === '') {
                $items[] = ['n' => $rowNo, 'action' => 'error', 'data' => $data + ['code' => $code], 'cf' => $cf, 'id' => null, 'msg' => 'Name is blank'];
                $n['error']++;
                continue;
            }

            $data['code'] = $code;
            $existingId   = $byCode[mb_strtolower($code)] ?? null;
            if ($existingId) {
                $items[] = ['n' => $rowNo, 'action' => 'update', 'data' => $data, 'cf' => $cf, 'id' => $existingId, 'msg' => null];
                $n['update']++;
            } else {
                $data['is_active'] ??= 1;
                $items[] = ['n' => $rowNo, 'action' => 'create', 'data' => $data, 'cf' => $cf, 'id' => null, 'msg' => null];
                $n['create']++;
            }
        }

        return ['items' => $items, 'counts' => $n];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param list<array<string,mixed>> $items from parse() (may be round-tripped through JSON)
     *
     * @return array{created:int,updated:int,failed:int,errors:list<string>}
     */
    public static function commit(string $kind, Model $model, CustomFields $cf, array $items): array
    {
        $db = Database::connect();
        $out = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

        $db->transStart();
        foreach ($items as $it) {
            $action = $it['action'] ?? 'error';
            if ($action === 'error') {
                continue;
            }
            $data = $it['data'] ?? [];
            $id   = null;

            if ($action === 'create') {
                if (! $model->insert($data)) {
                    $out['failed']++;
                    $out['errors'][] = 'Row ' . ($it['n'] ?? '?') . ': ' . implode('; ', $model->errors() ?: ['insert failed']);
                    continue;
                }
                $id = (int) $model->getInsertID();
                $out['created']++;
            } else {
                $id = (int) ($it['id'] ?? 0);
                if ($id <= 0 || ! $model->update($id, $data)) {
                    $out['failed']++;
                    $out['errors'][] = 'Row ' . ($it['n'] ?? '?') . ': ' . implode('; ', $model->errors() ?: ['update failed']);
                    continue;
                }
                $out['updated']++;
            }

            $incoming = array_filter((array) ($it['cf'] ?? []), static fn ($v) => (string) $v !== '');
            if ($incoming) {
                // don't wipe fields the file didn't include - merge over what's stored
                $merged = $action === 'update' ? array_merge($cf->valuesFor($kind, $id), $incoming) : $incoming;
                $cf->save($kind, $id, $merged);
            }
        }
        $db->transComplete();

        if ($db->transStatus() === false) {
            $out['errors'][] = 'The database rolled the import back.';
        }

        return $out;
    }

    private static function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($s)));
    }
}
