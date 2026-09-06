<?php

namespace App\Libraries\Report;

use App\Libraries\Accounting\Ledger;

/**
 * Builds the one-page executive summary — a condensed P&L (this period vs the
 * same period last year, with % of sales and YoY %) plus a Balance Sheet
 * position snapshot. Sub-lines come straight from the chart's header (group)
 * accounts, i.e. the `blocks` that Ledger::incomeStatement() / balanceSheet()
 * already return — no hard-coded account mapping.
 *
 * Consumed by the dashboard summary card (Dashboard controller) and the
 * Executive Summary report (ReportController::executiveSummary).
 */
class ExecutiveSummary
{
    /**
     * @param array{
     *   from:string, to:string, asOf:string,
     *   prevFrom:string, prevTo:string, prevAsOf:string,
     *   mtdFrom?:string, mtdTo?:string, mtdPrevFrom?:string, mtdPrevTo?:string
     * } $win
     *
     * @return array{pnl:list<array<string,mixed>>, bs:list<array<string,mixed>>, meta:array<string,mixed>}
     */
    public static function build(Ledger $ledger, array $win): array
    {
        $hasMtd = ! empty($win['mtdFrom']) && ! empty($win['mtdTo']);

        $cur  = $ledger->incomeStatement($win['from'], $win['to']);
        $prev = $ledger->incomeStatement($win['prevFrom'], $win['prevTo']);
        $mtd  = $hasMtd ? $ledger->incomeStatement($win['mtdFrom'], $win['mtdTo']) : null;
        $mtdP = $hasMtd && ! empty($win['mtdPrevFrom'])
            ? $ledger->incomeStatement($win['mtdPrevFrom'], $win['mtdPrevTo'])
            : null;

        $bsCur  = $ledger->balanceSheet($win['asOf']);
        $bsPrev = $ledger->balanceSheet($win['prevAsOf']);

        return [
            'pnl'  => self::pnl($cur, $prev, $mtd, $mtdP),
            'bs'   => self::bs($bsCur, $bsPrev),
            'meta' => [
                'from'     => $win['from'],
                'to'       => $win['to'],
                'asOf'     => $win['asOf'],
                'prevFrom' => $win['prevFrom'],
                'prevTo'   => $win['prevTo'],
                'prevAsOf' => $win['prevAsOf'],
                'hasMtd'   => $hasMtd,
                'mtdFrom'  => $win['mtdFrom'] ?? null,
                'mtdTo'    => $win['mtdTo'] ?? null,
            ],
        ];
    }

    /** Subtotal of one header block (by code) within a P&L group; 0 if absent. */
    private static function blockVal(array $is, string $group, string $code, string $name): float
    {
        $sum = 0.0;
        foreach ($is['groups'][$group]['blocks'] as $b) {
            $match = $code !== '' ? $b['code'] === $code : ($b['code'] === '' && $b['name'] === $name);
            if ($match) {
                $sum += (float) $b['subtotal'];
            }
        }

        return $sum;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function pnl(array $cur, array $prev, ?array $mtd, ?array $mtdP): array
    {
        $salesCur  = (float) $cur['revenue'];
        $salesPrev = (float) $prev['revenue'];

        $mk = static function (string $key, string $label, string $level, callable $pick) use ($cur, $prev, $mtd, $mtdP, $salesCur, $salesPrev): array {
            $c = (float) $pick($cur);
            $p = (float) $pick($prev);

            return [
                'key'     => $key,
                'label'   => $label,
                'level'   => $level, // line | child | total
                'cur'     => $c,
                'prev'    => $p,
                'mtd'     => $mtd !== null ? (float) $pick($mtd) : null,
                'mtdPrev' => $mtdP !== null ? (float) $pick($mtdP) : null,
                'pctCur'  => abs($salesCur) > 0.005 ? $c / $salesCur : null,
                'pctPrev' => abs($salesPrev) > 0.005 ? $p / $salesPrev : null,
                'yoy'     => abs($p) > 0.005 ? ($c - $p) / abs($p) : null,
            ];
        };

        $lines   = [];
        $lines[] = $mk('sales', lang('Report.es_sales'), 'line', static fn ($is) => $is['revenue']);
        $lines[] = $mk('cost_of_sales', lang('Report.es_cost_of_sales'), 'line', static fn ($is) => $is['groups']['cogs']['total']);
        $lines[] = $mk('gross_margin', lang('Report.es_gross_margin'), 'total', static fn ($is) => $is['gross_profit']);

        $lines[] = $mk('operating_expenses', lang('Report.es_operating_expenses'), 'line', static fn ($is) => $is['groups']['expense']['total']);
        foreach (self::childCodes($cur, $prev, 'expense') as [$code, $name]) {
            $lines[] = $mk('opex:' . ($code ?: $name), trim($code . ' ' . $name), 'child', static fn ($is) => self::blockVal($is, 'expense', $code, $name));
        }

        $lines[] = $mk('ebitda', lang('Report.es_ebitda'), 'total', static fn ($is) => $is['operating']);

        // Non-operational = other income − other expense (a net addition to EBITDA)
        $lines[] = $mk('non_operational', lang('Report.es_non_operational'), 'line', static fn ($is) => $is['groups']['other_income']['total'] - $is['groups']['other_expense']['total']);
        foreach (self::childCodes($cur, $prev, 'other_income') as [$code, $name]) {
            $lines[] = $mk('oi:' . ($code ?: $name), trim($code . ' ' . ($name ?: lang('Report.es_other_income'))), 'child', static fn ($is) => self::blockVal($is, 'other_income', $code, $name));
        }
        foreach (self::childCodes($cur, $prev, 'other_expense') as [$code, $name]) {
            $lines[] = $mk('oe:' . ($code ?: $name), trim($code . ' ' . ($name ?: lang('Report.es_other_expenses'))), 'child', static fn ($is) => - self::blockVal($is, 'other_expense', $code, $name));
        }

        $lines[] = $mk('net_profit', lang('Report.es_net_profit'), 'total', static fn ($is) => $is['net_income']);

        return $lines;
    }

    /**
     * Header blocks in a P&L group that carry a non-trivial figure in either
     * period, as [code, name] pairs in chart order.
     *
     * @return list<array{0:string,1:string}>
     */
    private static function childCodes(array $cur, array $prev, string $group): array
    {
        $out = [];
        foreach ($cur['groups'][$group]['blocks'] as $b) {
            $code = (string) $b['code'];
            $name = (string) $b['name'];
            $c    = self::blockVal($cur, $group, $code, $name);
            $p    = self::blockVal($prev, $group, $code, $name);
            if (abs($c) >= 0.005 || abs($p) >= 0.005) {
                $out[] = [$code, $name];
            }
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function bs(array $cur, array $prev): array
    {
        $section = static function (string $bucket, string $headLabel, string $totalLabel) use ($cur, $prev): array {
            $rows   = [['label' => $headLabel, 'level' => 'head']];
            $prevBy = [];
            foreach ($prev['groups'][$bucket]['blocks'] as $b) {
                $prevBy[$b['code'] !== '' ? 'c:' . $b['code'] : 'n:' . $b['name']] = (float) $b['subtotal'];
            }
            foreach ($cur['groups'][$bucket]['blocks'] as $b) {
                $k = $b['code'] !== '' ? 'c:' . $b['code'] : 'n:' . $b['name'];
                $c = (float) $b['subtotal'];
                $p = $prevBy[$k] ?? 0.0;
                unset($prevBy[$k]);
                if (abs($c) < 0.005 && abs($p) < 0.005) {
                    continue;
                }
                $label = trim(($b['code'] !== '' ? $b['code'] . ' ' : '') . $b['name']);
                if ($label === '') {
                    // balanceSheet() appends the current-year earnings as a nameless block
                    $label = lang('Report.es_result_for_period');
                }
                $rows[] = [
                    'label' => $label,
                    'level' => 'child',
                    'cur'   => $c,
                    'prev'  => $p,
                ];
            }
            $rows[] = [
                'label' => $totalLabel,
                'level' => 'total',
                'cur'   => (float) $cur[$bucket === 'asset' ? 'assets' : ($bucket === 'liability' ? 'liabilities' : 'equity')],
                'prev'  => (float) $prev[$bucket === 'asset' ? 'assets' : ($bucket === 'liability' ? 'liabilities' : 'equity')],
            ];

            return $rows;
        };

        $rows = array_merge(
            $section('asset', lang('Report.grp_assets'), lang('Report.es_total_assets')),
            $section('liability', lang('Report.grp_liabilities'), lang('Report.es_total_liabilities')),
            $section('equity', lang('Report.grp_equity'), lang('Report.es_total_equity')),
        );

        $rows[] = [
            'label' => lang('Report.es_liab_plus_equity'),
            'level' => 'total',
            'cur'   => (float) $cur['liab_equity'],
            'prev'  => (float) $prev['liab_equity'],
        ];

        return $rows;
    }
}
