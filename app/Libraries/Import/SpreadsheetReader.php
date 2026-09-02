<?php

namespace App\Libraries\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Thin wrapper over PhpSpreadsheet: list sheets and pull a sheet back as a
 * plain 0-indexed grid of raw cell values (no formatting applied, so dates
 * arrive as Excel serial numbers we can convert deterministically).
 */
class SpreadsheetReader
{
    /** @return list<string> */
    public function sheetNames(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unrecognised spreadsheet file: ' . $e->getMessage());
        }
        $reader->setReadDataOnly(true);

        if (method_exists($reader, 'listWorksheetNames')) {
            return $reader->listWorksheetNames($path);
        }

        return ['Sheet1'];
    }

    /**
     * @return list<list<mixed>> 0-indexed rows, each a 0-indexed list of cell values
     */
    public function grid(string $path, ?string $sheet = null, int $maxRows = 60000): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        if ($sheet !== null && method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$sheet]);
        }

        $spreadsheet = $reader->load($path);
        $ws          = $sheet !== null ? $spreadsheet->getSheetByName($sheet) : null;
        // CSV/single-sheet files: the stored sheet name ("Sheet1") often does not
        // match the real title, so fall back to the active sheet.
        $ws ??= $spreadsheet->getActiveSheet();
        if ($ws === null) {
            throw new RuntimeException("Sheet '{$sheet}' not found.");
        }

        $rows = [];
        $r    = 0;
        foreach ($ws->getRowIterator() as $row) {
            if ($r++ >= $maxRows) {
                break;
            }
            $cells = [];
            $it    = $row->getCellIterator();
            $it->setIterateOnlyExistingCells(false);
            foreach ($it as $cell) {
                $cells[] = $cell->getValue();
            }
            $rows[] = $cells;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * Best-effort conversion of a cell value to a Y-m-d string.
     *
     * @param 'auto'|'dmy'|'mdy'|'ymd' $format
     */
    public static function toDate($value, string $format = 'auto'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Excel serial date (also covers PhpSpreadsheet numeric dates)
        if (is_numeric($value) && (float) $value > 20 && (float) $value < 90000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                // fall through to string parsing
            }
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        // Explicit numeric formats
        if (preg_match('#^(\d{1,4})[/.\-](\d{1,2})[/.\-](\d{1,4})#', $s, $m)) {
            [$a, $b, $c] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($format === 'ymd' || ($format === 'auto' && $m[1] > 31)) {
                [$y, $mo, $d] = [$a, $b, $c];
            } elseif ($format === 'mdy') {
                [$mo, $d, $y] = [$a, $b, $c];
            } else { // dmy or auto
                [$d, $mo, $y] = [$a, $b, $c];
                if ($format === 'auto' && $a <= 12 && $b > 12) {
                    [$mo, $d] = [$a, $b]; // clearly m/d
                }
            }
            $y = $y < 100 ? 2000 + $y : $y;
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }

        $ts = strtotime($s);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * Parse a numeric cell: blanks -> 0, "(1.234,00)" negatives, thousands
     * separators stripped. Assumes '.' decimal / ',' thousands.
     */
    public static function toNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $s   = trim((string) $value);
        $neg = false;
        if (preg_match('/^\((.*)\)$/', $s, $m)) {
            $neg = true;
            $s   = $m[1];
        }
        $s = preg_replace('/[^0-9,.\-]/', '', $s);
        // If both separators present, assume ',' = thousands
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace(',', '', $s);
        } elseif (substr_count($s, ',') === 1 && ! str_contains($s, '.')) {
            $s = str_replace(',', '.', $s); // lone comma = decimal
        } else {
            $s = str_replace(',', '', $s);
        }
        $n = (float) $s;

        return $neg ? -$n : $n;
    }
}
