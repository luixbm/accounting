<?php

namespace App\Libraries\Report;

use Config\Database;

/**
 * Resolves the shared report period control (Year + Period + Custom range)
 * from the request into a concrete from / to / as-of window.
 */
class ReportFilter
{
    /**
     * @return array{year:int, period:string, from:string, to:string, asOf:string, label:string, compare:string, zeros:bool}
     */
    public static function resolve(): array
    {
        $req    = service('request');
        $year   = (int) ($req->getGet('year') ?: date('Y'));
        $period = (string) ($req->getGet('period') ?: 'year');
        $cFrom  = $req->getGet('from');
        $cTo    = $req->getGet('to');

        if ($period === 'custom' && $cFrom && $cTo) {
            $from = $cFrom;
            $to   = $cTo;
        } else {
            [$from, $to] = self::span($year, $period);
        }

        $asOf    = $req->getGet('as_of') ?: $to;
        $compare = in_array($req->getGet('compare'), ['month', 'quarter', 'year'], true) ? (string) $req->getGet('compare') : '';

        return [
            'year'    => $year,
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
            'asOf'    => $asOf,
            'compare' => $compare,
            'zeros'   => (bool) $req->getGet('zeros'),
            'label'   => self::label($year, $period, $from, $to),
        ];
    }

    /** @return array{0:string,1:string} */
    private static function span(int $year, string $period): array
    {
        if (preg_match('/^q([1-4])$/', $period, $m)) {
            $startMonth = ((int) $m[1] - 1) * 3 + 1;
            $from       = sprintf('%04d-%02d-01', $year, $startMonth);

            return [$from, date('Y-m-t', strtotime('+2 months', strtotime($from)))];
        }
        if (preg_match('/^m(\d{1,2})$/', $period, $m)) {
            $from = sprintf('%04d-%02d-01', $year, max(1, min(12, (int) $m[1])));

            return [$from, date('Y-m-t', strtotime($from))];
        }

        return [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)];
    }

    private static function label(int $year, string $period, string $from, string $to): string
    {
        if ($period === 'custom') {
            return date_id($from) . ' – ' . date_id($to);
        }
        if (preg_match('/^q([1-4])$/', $period, $m)) {
            return 'Q' . $m[1] . ' ' . $year;
        }
        if (preg_match('/^m(\d{1,2})$/', $period, $m)) {
            return date('F', mktime(0, 0, 0, (int) $m[1], 1)) . ' ' . $year;
        }

        return (string) $year;
    }

    /**
     * Selectable years: earliest journal year .. next year.
     *
     * @return list<int>
     */
    public static function years(): array
    {
        $row = Database::connect()->table('journals')
            ->select('MIN(YEAR(entry_date)) AS y')->where('company_id', active_company_id())->get()->getRowArray();
        $min = (int) ($row['y'] ?? date('Y')) ?: (int) date('Y');
        $max = (int) date('Y') + 1;

        return range($max, min($min, $max));
    }
}
