<?php

namespace App\Controllers;

use App\Libraries\Accounting\Ledger;
use App\Libraries\Budget\Budget;
use App\Libraries\Report\ReportExporter;
use App\Libraries\Report\ReportFilter;

/**
 * P&L vs Budget: the grouped income statement for a period with Actual, Budget,
 * Variance and Achieved % columns, budget sourced from a budget version.
 */
class BudgetReportController extends BaseController
{
    private function rlang(string $key, string $fallback): string
    {
        $s = lang('Report.' . $key);

        return $s === 'Report.' . $key ? $fallback : $s;
    }

    public function pnlBudget()
    {
        $f    = ReportFilter::resolve();
        $year = (int) substr($f['from'], 0, 4);
        $vid  = Budget::resolveVersionId(((int) $this->request->getGet('version')) ?: null, $year);

        $versions = Budget::versionsForYear($year);
        $extra    = $this->versionSelect($versions, $vid);
        $title    = $this->rlang('pnl-budget', 'P&L vs Budget');

        $cols = [
            ['key' => 'name', 'label' => $this->rlang('col_account', 'Account')],
            ['key' => 'actual', 'label' => $this->rlang('col_actual', 'Actual') . ' (' . base_code() . ')', 'money' => true, 'blankZero' => true],
            ['key' => 'budget', 'label' => $this->rlang('col_budget', 'Budget'), 'money' => true, 'blankZero' => true],
            ['key' => 'variance', 'label' => $this->rlang('col_variance', 'Variance'), 'money' => true, 'blankZero' => true],
            ['key' => 'achieved', 'label' => $this->rlang('col_achieved', 'Achieved %'), 'align' => 'right'],
        ];

        if ($vid === null) {
            return $this->respond($title, $f, $cols, [
                ['_style' => 'section', '_label' => $this->rlang('pnlb_none', 'No budget for ' . $year . ' — add one under Setup → Budgets.')],
            ], $extra);
        }

        // Force every P&L leaf so accounts that have a budget but no actual yet
        // (or vice versa) still appear; each line is then hidden only when both
        // its actual and its budget are ~zero.
        $act = (new Ledger())->incomeStatement($f['from'], $f['to'], true);
        $bud = Budget::byCodeForRange($vid, $f['from'], $f['to']);

        $pct = static fn (float $a, float $b): string => abs($b) < 0.005
            ? (abs($a) >= 0.005 ? '—' : '')
            : number_format($a / $b * 100, 1) . '%';
        // For the computed profit lines a % only makes sense against a positive
        // budgeted profit; a <=0 base usually means the budget is only partly loaded.
        $pctProfit = static fn (float $a, float $b): string => $b > 0.005 ? number_format($a / $b * 100, 1) . '%' : '—';

        // budget total per P&L group, for the computed GP / OP / NET lines
        $gBud = ['revenue' => 0.0, 'cogs' => 0.0, 'expense' => 0.0, 'other_income' => 0.0, 'other_expense' => 0.0];

        $nz   = static fn (float $x): bool => abs($x) >= 0.005;
        $rows = [];
        $sec  = static function (array $g, string $type) use (&$rows, &$gBud, $bud, $pct, $nz) {
            $gGrp  = 0.0;
            $blocksOut = [];
            foreach ($g['blocks'] as $blk) {
                $named = $blk['code'] !== '';
                $leaves = [];
                $bSub   = 0.0;
                foreach ($blk['rows'] as $r) {
                    $a = (float) $r['amount'];
                    $b = (float) ($bud[$r['code']] ?? 0);
                    $bSub += $b;
                    if (! $nz($a) && ! $nz($b)) {
                        continue;
                    }
                    $leaves[] = [
                        'name'     => trim(($named ? '  ' : '') . $r['code'] . ' ' . $r['name']),
                        'actual'   => $a,
                        'budget'   => $b,
                        'variance' => $a - $b,
                        'achieved' => $pct($a, $b),
                    ];
                }
                $gGrp += $bSub;
                if (! $leaves && ! $nz((float) $blk['subtotal']) && ! $nz($bSub)) {
                    continue;   // empty header — skip it
                }
                $blocksOut[] = ['blk' => $blk, 'named' => $named, 'leaves' => $leaves, 'bSub' => $bSub];
            }

            $gBud[$type] = $gGrp;
            $gAct        = (float) $g['total'];
            if (! $blocksOut && ! $nz($gAct) && ! $nz($gGrp)) {
                return;   // whole group has no activity and no budget
            }

            $rows[] = ['_style' => 'section', '_label' => $g['label']];
            foreach ($blocksOut as $bo) {
                if ($bo['named']) {
                    $rows[] = ['_style' => 'section', '_label' => trim($bo['blk']['code'] . ' ' . $bo['blk']['name'])];
                }
                foreach ($bo['leaves'] as $lr) {
                    $rows[] = $lr;
                }
                if ($bo['named']) {
                    $s = (float) $bo['blk']['subtotal'];
                    $rows[] = [
                        '_style' => 'subtotal', 'name' => 'Subtotal ' . $bo['blk']['name'],
                        'actual' => $s, 'budget' => $bo['bSub'],
                        'variance' => $s - $bo['bSub'], 'achieved' => $pct($s, $bo['bSub']),
                    ];
                }
            }
            $rows[] = [
                '_style' => 'subtotal', 'name' => 'Total',
                'actual' => $gAct, 'budget' => $gGrp, 'variance' => $gAct - $gGrp, 'achieved' => $pct($gAct, $gGrp),
            ];
        };

        $sec($act['groups']['revenue'], 'revenue');
        $sec($act['groups']['cogs'], 'cogs');
        $gp = $gBud['revenue'] - $gBud['cogs'];
        $rows[] = ['_style' => 'subtotal', 'name' => 'GROSS PROFIT',
            'actual' => $act['gross_profit'], 'budget' => $gp, 'variance' => $act['gross_profit'] - $gp, 'achieved' => $pctProfit($act['gross_profit'], $gp)];
        $sec($act['groups']['expense'], 'expense');
        $op = $gp - $gBud['expense'];
        $rows[] = ['_style' => 'subtotal', 'name' => 'OPERATING PROFIT',
            'actual' => $act['operating'], 'budget' => $op, 'variance' => $act['operating'] - $op, 'achieved' => $pctProfit($act['operating'], $op)];
        $sec($act['groups']['other_income'], 'other_income');
        $sec($act['groups']['other_expense'], 'other_expense');
        $net = $op + $gBud['other_income'] - $gBud['other_expense'];
        $rows[] = ['_style' => 'total', 'name' => 'NET INCOME',
            'actual' => $act['net_income'], 'budget' => $net, 'variance' => $act['net_income'] - $net, 'achieved' => $pctProfit($act['net_income'], $net)];

        return $this->respond($title, $f, $cols, $rows, $extra);
    }

    private function versionSelect(array $versions, ?int $current): string
    {
        if (! $versions) {
            return '';
        }
        $opts = '';
        foreach ($versions as $v) {
            $sel = (int) $v['id'] === $current ? ' selected' : '';
            $opts .= '<option value="' . (int) $v['id'] . '"' . $sel . '>' . esc($v['name'] . ' (' . $v['year'] . ')') . '</option>';
        }

        return '<div class="field" style="max-width:220px"><label>' . esc($this->rlang('col_budget', 'Budget')) . '</label>'
            . '<select name="version">' . $opts . '</select></div>';
    }

    private function respond(string $title, array $f, array $columns, array $rows, string $extra = '')
    {
        if ($this->request->getGet('format') === 'xlsx') {
            return ReportExporter::download([
                'title'   => $title,
                'meta'    => ['Company' => company_legal_name(), 'Period' => $f['label'] . '  (' . $f['from'] . ' — ' . $f['to'] . ')'],
                'columns' => $columns,
                'rows'    => $rows,
            ]);
        }

        return view('reports/_list', [
            'title' => $title, 'f' => $f, 'columns' => $columns, 'rows' => $rows,
            'periodOpts' => ['showCompare' => false, 'showZeros' => true, 'extra' => $extra], 'subtitle' => '',
        ]);
    }
}
