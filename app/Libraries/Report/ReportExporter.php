<?php

namespace App\Libraries\Report;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Turns a report's typed data into a downloadable .xlsx.
 *
 * Spec:
 *   [
 *     'title'   => 'Trial Balance',
 *     'meta'    => ['Company' => '...', 'Period' => '...'],   // printed above the table
 *     'columns' => [ ['key'=>'code','label'=>'Code'], ['key'=>'debit','label'=>'Debit','money'=>true,'align'=>'right'], ... ],
 *     'rows'    => [
 *        ['code'=>'1000','name'=>'Cash', ...],                 // normal row
 *        ['_style'=>'section','_label'=>'REVENUE'],            // full-width heading
 *        ['_style'=>'subtotal','name'=>'Total', 'debit'=>123], // emphasised row
 *        ['_style'=>'total',    'name'=>'GRAND TOTAL', ...],
 *     ],
 *   ]
 */
class ReportExporter
{
    public static function download(array $spec)
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[^A-Za-z0-9 _-]/', '', $spec['title'] ?? 'Report'), 0, 30) ?: 'Report');

        $cols  = $spec['columns'] ?? [];
        $nCols = max(count($cols), 2);
        $r     = 1;

        // title
        $sheet->setCellValue([1, $r], $spec['title'] ?? 'Report');
        $sheet->getStyle([1, $r])->getFont()->setBold(true)->setSize(14);
        $r += 2;

        // meta block
        foreach (($spec['meta'] ?? []) as $k => $v) {
            $sheet->setCellValue([1, $r], $k);
            $sheet->setCellValue([2, $r], $v);
            $sheet->getStyle([1, $r])->getFont()->setBold(true);
            $r++;
        }
        if (! empty($spec['meta'])) {
            $r++;
        }

        // header row
        $headerRow = $r;
        foreach ($cols as $i => $c) {
            $sheet->setCellValue([$i + 1, $r], $c['label'] ?? $c['key']);
        }
        // Header row look follows Setup -> Appearance -> Table header.
        $accent = function_exists('table_header_style') && table_header_style() === 'accent';
        $brand  = ['green' => '1A7F45', 'blue' => '1F5F8B', 'dark' => '2C6E9B'][function_exists('app_theme') ? app_theme() : 'light'] ?? '1F5F8B';
        $sheet->getStyle([1, $r, $nCols, $r])->getFont()->setBold(true)
            ->getColor()->setRGB($accent ? 'FFFFFF' : '000000');
        $sheet->getStyle([1, $r, $nCols, $r])->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($accent ? $brand : 'EEF2F6');
        $r++;

        // body
        foreach (($spec['rows'] ?? []) as $row) {
            $style = $row['_style'] ?? '';
            if ($style === 'section') {
                $sheet->setCellValue([1, $r], $row['_label'] ?? '');
                $sheet->mergeCells([1, $r, $nCols, $r]);
                $sheet->getStyle([1, $r])->getFont()->setBold(true);
                $sheet->getStyle([1, $r, $nCols, $r])->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F5F7');
                $r++;

                continue;
            }
            foreach ($cols as $i => $c) {
                $key = $c['key'];
                $val = $row[$key] ?? '';
                $cell = [$i + 1, $r];
                if (! empty($c['money']) && $val !== '' && $val !== null) {
                    $sheet->setCellValue($cell, (float) $val);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00;(#,##0.00)');
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                if (($c['align'] ?? '') === 'right' || ! empty($c['money'])) {
                    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            if (in_array($style, ['subtotal', 'total'], true)) {
                $sheet->getStyle([1, $r, $nCols, $r])->getFont()->setBold(true);
                if ($style === 'total') {
                    $sheet->getStyle([1, $r, $nCols, $r])->getBorders()->getTop()->setBorderStyle('thin');
                }
            }
            $r++;
        }

        foreach ($cols as $i => $c) {
            $sheet->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
        }
        $sheet->freezePane([1, $headerRow + 1]);

        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $spec['title'] ?? 'report') . '-' . date('Ymd') . '.xlsx';

        $resp = service('response');
        $resp->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $resp->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
        $resp->setHeader('Cache-Control', 'max-age=0');

        ob_start();
        (new Xlsx($ss))->save('php://output');
        $resp->setBody(ob_get_clean());
        $ss->disconnectWorksheets();

        return $resp;
    }
}
