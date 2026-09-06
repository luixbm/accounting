<?php

namespace App\Controllers;

use App\Libraries\Accounting\Ledger;
use App\Libraries\Report\ExecutiveSummary;
use App\Libraries\Report\ReportExporter;
use App\Libraries\Report\ReportFilter;
use App\Models\AccountModel;

class ReportController extends BaseController
{
    private Ledger $ledger;

    public function __construct()
    {
        $this->ledger = new Ledger();
    }

    private function wantsXlsx(): bool
    {
        return $this->request->getGet('format') === 'xlsx';
    }

    private function metaFor(array $f): array
    {
        return [
            'Company' => company_name(),
            'Period'  => $f['label'] . '  (' . $f['from'] . ' — ' . $f['to'] . ')',
        ];
    }

    /**
     * Export a multi-period report (one amount column per period).
     *
     * @param list<string>              $columns period labels
     * @param list<array<string,mixed>> $blocks  ordered blocks:
     *   ['type'=>'group','g'=>['label','rows'=>[{code,name,amounts:[]}],'totals'=>[]]]
     *   ['type'=>'row','label'=>string,'series'=>list<float>,'style'=>'subtotal'|'total']
     */
    private function exportComparative(string $title, array $f, array $columns, array $blocks, bool $rowTotal = false)
    {
        $cols = [['key' => 'name', 'label' => 'Account']];
        foreach ($columns as $i => $lbl) {
            $cols[] = ['key' => 'c' . $i, 'label' => $lbl, 'money' => true];
        }
        if ($rowTotal) {
            $cols[] = ['key' => 'cT', 'label' => 'Total', 'money' => true];
        }

        $series = static function (array $vals, string $name, string $style = '') use ($rowTotal): array {
            $row = $style !== '' ? ['_style' => $style, 'name' => $name] : ['name' => $name];
            foreach ($vals as $i => $v) {
                $row['c' . $i] = $v;
            }
            if ($rowTotal) {
                $row['cT'] = array_sum($vals);
            }

            return $row;
        };

        $rows = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'group') {
                $g = $b['g'];
                if (! empty($g['blocks'])) {
                    // grouped statement: header sub-sections + per-column subtotals
                    $rows[] = ['_style' => 'section', '_label' => $g['label']];
                    foreach ($g['blocks'] as $blk) {
                        $named = $blk['code'] !== '';
                        if ($named) {
                            $rows[] = ['_style' => 'section', '_label' => trim($blk['code'] . ' ' . $blk['name'])];
                        }
                        foreach ($blk['rows'] as $r) {
                            $rows[] = $series($r['amounts'], trim(($named ? '  ' : '') . ($r['code'] ?? '') . ' ' . $r['name']));
                        }
                        if ($named) {
                            $rows[] = $series($blk['subtotals'], 'Subtotal ' . $blk['name'], 'subtotal');
                        }
                    }
                    $rows[] = $series($g['totals'], 'Total', 'subtotal');

                    continue;
                }
                if (! $g['rows']) {
                    continue;
                }
                $rows[] = ['_style' => 'section', '_label' => $g['label']];
                foreach ($g['rows'] as $r) {
                    $rows[] = $series($r['amounts'], trim(($r['code'] ?? '') . ' ' . $r['name']));
                }
                $rows[] = $series($g['totals'], 'Total', 'subtotal');
            } else {
                $rows[] = $series($b['series'], $b['label'], $b['style'] ?? 'subtotal');
            }
        }

        return ReportExporter::download([
            'title'   => $title,
            'meta'    => $this->metaFor($f),
            'columns' => $cols,
            'rows'    => $rows,
        ]);
    }

    // ------------------------------------------------------------------ hub

    /** Localised report string, falling back to the catalogue's English. */
    private function rlang(string $key, string $fallback): string
    {
        $s = lang('Report.' . $key);

        return $s === 'Report.' . $key ? $fallback : $s;
    }

    public function index()
    {
        $cfg          = config(\Config\Reports::class);
        $multiCompany = model(\App\Models\CompanyModel::class)->where('is_active', 1)->countAllResults() > 1;

        $byCat = [];
        foreach ($cfg->items as $key => [$cat, $ttl, $desc, $icon, $route, $exists]) {
            if ($key === 'consolidation' && ! $multiCompany) {
                continue;
            }
            $perm = $cfg->permFor($key);
            if ($perm !== 'reports.view' && ! auth()->user()->can($perm)) {
                continue;
            }
            $ttl  = $this->rlang($key, $ttl);
            $desc = $this->rlang($key . '_d', $desc);
            $byCat[$cat][] = compact('key', 'ttl', 'desc', 'icon', 'route', 'exists');
        }

        $categories = [];
        foreach ($cfg->categories as $ck => $c) {
            $categories[$ck] = ['label' => $this->rlang('cat_' . $ck, $c['label']), 'icon' => $c['icon']];
        }

        return view('reports/index', [
            'title'      => $this->rlang('title', 'Reports'),
            'categories' => $categories,
            'byCat'      => $byCat,
        ]);
    }

    // ------------------------------------------------------------------ Trial Balance

    public function trialBalance()
    {
        $f    = ReportFilter::resolve();
        $data = $this->ledger->trialBalance($f['from'], $f['to'], $f['zeros']);

        if ($this->wantsXlsx()) {
            $rows = [];
            $six  = static fn (array $r): array => [
                'od' => $r['open_d'], 'oc' => $r['open_c'], 'md' => $r['mv_d'], 'mc' => $r['mv_c'],
                'ed' => $r['end_d'], 'ec' => $r['end_c'],
            ];
            foreach ($data['blocks'] as $blk) {
                $named = $blk['code'] !== '';
                if ($named) {
                    $rows[] = ['_style' => 'section', '_label' => trim($blk['code'] . ' ' . $blk['name'])];
                }
                foreach ($blk['rows'] as $r) {
                    $rows[] = ['code' => ($named ? '  ' : '') . $r['code'], 'name' => $r['name']] + $six($r);
                }
                if ($named) {
                    $rows[] = ['_style' => 'subtotal', 'name' => 'Subtotal ' . $blk['name']] + $six($blk['subtotal']);
                }
            }
            $rows[] = ['_style' => 'total', 'name' => 'TOTAL'] + $six($data['totals']);

            return ReportExporter::download([
                'title'   => $this->rlang('trial-balance', 'Trial Balance'),
                'meta'    => $this->metaFor($f),
                'columns' => [
                    ['key' => 'code', 'label' => 'Code'], ['key' => 'name', 'label' => 'Account'],
                    ['key' => 'od', 'label' => 'Opening Debit', 'money' => true], ['key' => 'oc', 'label' => 'Opening Credit', 'money' => true],
                    ['key' => 'md', 'label' => 'Movement Debit', 'money' => true], ['key' => 'mc', 'label' => 'Movement Credit', 'money' => true],
                    ['key' => 'ed', 'label' => 'Ending Debit', 'money' => true], ['key' => 'ec', 'label' => 'Ending Credit', 'money' => true],
                ],
                'rows' => $rows,
            ]);
        }

        return view('reports/trial_balance', [
            'title' => $this->rlang('trial-balance', 'Trial Balance'), 'f' => $f,
            'from'  => $f['from'], 'to' => $f['to'], 'data' => $data,
        ]);
    }

    // ------------------------------------------------------------------ General Ledger / Bank Book

    public function generalLedger()
    {
        return $this->ledgerLike('general_ledger', $this->rlang('gl-account', 'General Ledger'), model(AccountModel::class)->postable());
    }

    public function bankBook()
    {
        return $this->ledgerLike('bank_book', $this->rlang('bank-history', 'Bank Book'), model(AccountModel::class)->cashAccounts());
    }

    private function ledgerLike(string $view, string $title, array $accounts)
    {
        $f         = ReportFilter::resolve();
        $accountId = (int) ($this->request->getGet('account_id') ?: ($accounts[0]['id'] ?? 0));
        $account   = $accountId ? model(AccountModel::class)->find($accountId) : null;
        $data      = $account ? $this->ledger->generalLedger($accountId, $f['from'], $f['to']) : null;

        if ($this->wantsXlsx() && $data) {
            $rows = [['_style' => 'subtotal', 'memo' => 'Opening balance', 'bal' => $data['opening']]];
            foreach ($data['lines'] as $l) {
                $rows[] = ['date' => date_id($l['entry_date']), 'no' => $l['journal_no'],
                    'memo' => $l['memo'], 'party' => $l['customer_name'] ?? $l['supplier_name'] ?? '',
                    'in' => $l['debit'], 'out' => $l['credit'], 'bal' => $l['balance']];
            }
            $rows[] = ['_style' => 'total', 'memo' => 'Closing balance', 'bal' => $data['closing']];

            return ReportExporter::download([
                'title'   => $title . ' - ' . $account['code'],
                'meta'    => $this->metaFor($f) + ['Account' => $account['code'] . ' ' . $account['name']],
                'columns' => [
                    ['key' => 'date', 'label' => 'Date'], ['key' => 'no', 'label' => 'Journal'],
                    ['key' => 'memo', 'label' => 'Memo'], ['key' => 'party', 'label' => 'Party'],
                    ['key' => 'in', 'label' => 'Debit', 'money' => true], ['key' => 'out', 'label' => 'Credit', 'money' => true],
                    ['key' => 'bal', 'label' => 'Balance', 'money' => true],
                ],
                'rows' => $rows,
            ]);
        }

        return view('reports/' . $view, [
            'title'     => $title, 'f' => $f, 'from' => $f['from'], 'to' => $f['to'],
            'accounts'  => $accounts, 'banks' => $accounts,
            'accountId' => $accountId, 'account' => $account, 'data' => $data,
        ]);
    }

    // ------------------------------------------------------------------ Balance Sheet

    public function balanceSheet()
    {
        $f = ReportFilter::resolve();

        if ($f['compare'] !== '') {
            $cols = $this->ledger->periodColumns($f['compare'], $f['from'], $f['to']);
            $data = $this->ledger->balanceSheetMulti($cols, $f['zeros']);

            if ($this->wantsXlsx()) {
                return $this->exportComparative('Balance Sheet (comparative)', $f, $data['columns'], [
                    ['type' => 'group', 'g' => $data['groups']['asset']],
                    ['type' => 'row', 'label' => 'TOTAL ASSETS', 'series' => $data['assets'], 'style' => 'subtotal'],
                    ['type' => 'group', 'g' => $data['groups']['liability']],
                    ['type' => 'group', 'g' => $data['groups']['equity']],
                    ['type' => 'row', 'label' => 'TOTAL LIABILITIES + EQUITY', 'series' => $data['liab_equity'], 'style' => 'total'],
                ]);
            }

            return view('reports/balance_sheet_multi', [
                'title' => $this->rlang('bs_comparative', 'Balance Sheet — comparative'), 'f' => $f,
                'from'  => $f['from'], 'to' => $f['to'], 'asOf' => $f['asOf'], 'compare' => $f['compare'],
                'data'  => $data,
            ]);
        }

        $data = $this->ledger->balanceSheet($f['asOf'], null, $f['zeros']);

        if ($this->wantsXlsx()) {
            $rows = [];
            foreach (['asset', 'liability', 'equity'] as $g) {
                $rows[] = ['_style' => 'section', '_label' => $data['groups'][$g]['label']];
                foreach ($data['groups'][$g]['blocks'] as $blk) {
                    $named = $blk['code'] !== '';
                    if ($named) {
                        $rows[] = ['_style' => 'section', '_label' => trim($blk['code'] . ' ' . $blk['name'])];
                    }
                    foreach ($blk['rows'] as $r) {
                        $rows[] = ['name' => trim(($named ? '  ' : '') . $r['code'] . ' ' . $r['name']), 'amt' => $r['amount']];
                    }
                    if ($named) {
                        $rows[] = ['_style' => 'subtotal', 'name' => 'Subtotal ' . $blk['name'], 'amt' => $blk['subtotal']];
                    }
                }
                $rows[] = ['_style' => 'subtotal', 'name' => 'Total', 'amt' => $data['groups'][$g]['total']];
            }
            $rows[] = ['_style' => 'total', 'name' => 'ASSETS', 'amt' => $data['assets']];
            $rows[] = ['_style' => 'total', 'name' => 'LIABILITIES + EQUITY', 'amt' => $data['liab_equity']];

            return ReportExporter::download([
                'title'   => $this->rlang('bs', 'Balance Sheet'),
                'meta'    => ['Company' => company_name(), 'As of' => date_id($f['asOf'])],
                'columns' => [['key' => 'name', 'label' => 'Account'], ['key' => 'amt', 'label' => 'Amount (Rp)', 'money' => true]],
                'rows'    => $rows,
            ]);
        }

        return view('reports/balance_sheet', [
            'title'   => $this->rlang('bs', 'Balance Sheet'), 'f' => $f,
            'asOf'    => $f['asOf'], 'from' => $f['from'], 'to' => $f['to'], 'compare' => '',
            'data'    => $data,
        ]);
    }

    // ------------------------------------------------------------------ Income Statement

    public function incomeStatement()
    {
        $f = ReportFilter::resolve();

        if ($f['compare'] !== '') {
            $cols = $this->ledger->periodColumns($f['compare'], $f['from'], $f['to']);
            $data = $this->ledger->incomeStatementMulti($cols, $f['zeros']);

            if ($this->wantsXlsx()) {
                $st = $data['subtotals'];

                return $this->exportComparative('Profit and Loss (comparative)', $f, $data['columns'], [
                    ['type' => 'group', 'g' => $data['groups']['revenue']],
                    ['type' => 'group', 'g' => $data['groups']['cogs']],
                    ['type' => 'row', 'label' => 'GROSS PROFIT', 'series' => $st['gross_profit'], 'style' => 'subtotal'],
                    ['type' => 'group', 'g' => $data['groups']['expense']],
                    ['type' => 'row', 'label' => 'OPERATING PROFIT', 'series' => $st['operating'], 'style' => 'subtotal'],
                    ['type' => 'group', 'g' => $data['groups']['other_income']],
                    ['type' => 'group', 'g' => $data['groups']['other_expense']],
                    ['type' => 'row', 'label' => 'NET INCOME', 'series' => $st['net_income'], 'style' => 'total'],
                ], true);
            }

            return view('reports/income_statement_multi', [
                'title' => $this->rlang('pnl_comparative', 'Income Statement — comparative'), 'f' => $f,
                'from'  => $f['from'], 'to' => $f['to'], 'compare' => $f['compare'],
                'data'  => $data,
            ]);
        }

        $data = $this->ledger->incomeStatement($f['from'], $f['to'], $f['zeros']);

        if ($this->wantsXlsx()) {
            $rows = [];
            $sec  = static function (array $g) use (&$rows) {
                if (! $g['blocks']) {
                    return;
                }
                $rows[] = ['_style' => 'section', '_label' => $g['label']];
                foreach ($g['blocks'] as $blk) {
                    $named = $blk['code'] !== '';
                    if ($named) {
                        $rows[] = ['_style' => 'section', '_label' => trim($blk['code'] . ' ' . $blk['name'])];
                    }
                    foreach ($blk['rows'] as $r) {
                        $rows[] = ['name' => trim(($named ? '  ' : '') . $r['code'] . ' ' . $r['name']), 'amt' => $r['amount']];
                    }
                    if ($named) {
                        $rows[] = ['_style' => 'subtotal', 'name' => 'Subtotal ' . $blk['name'], 'amt' => $blk['subtotal']];
                    }
                }
                $rows[] = ['_style' => 'subtotal', 'name' => 'Total', 'amt' => $g['total']];
            };
            $sec($data['groups']['revenue']);
            $sec($data['groups']['cogs']);
            $rows[] = ['_style' => 'subtotal', 'name' => 'GROSS PROFIT', 'amt' => $data['gross_profit']];
            $sec($data['groups']['expense']);
            $rows[] = ['_style' => 'subtotal', 'name' => 'OPERATING PROFIT', 'amt' => $data['operating']];
            $sec($data['groups']['other_income']);
            $sec($data['groups']['other_expense']);
            $rows[] = ['_style' => 'total', 'name' => 'NET INCOME', 'amt' => $data['net_income']];

            return ReportExporter::download([
                'title'   => $this->rlang('pnl', 'Profit & Loss'),
                'meta'    => $this->metaFor($f),
                'columns' => [['key' => 'name', 'label' => 'Account'], ['key' => 'amt', 'label' => 'Amount (Rp)', 'money' => true]],
                'rows'    => $rows,
            ]);
        }

        return view('reports/income_statement', [
            'title' => $this->rlang('pnl', 'Profit & Loss'), 'f' => $f,
            'from'  => $f['from'], 'to' => $f['to'], 'compare' => '', 'data' => $data,
        ]);
    }

    // ------------------------------------------------------------------ Executive Summary

    public function executiveSummary()
    {
        $f = ReportFilter::resolve();

        // The report is "selected period vs the same period one year earlier".
        $shift = static fn (string $d): string => (((int) substr($d, 0, 4)) - 1) . substr($d, 4);
        $win   = [
            'from'     => $f['from'],
            'to'       => $f['to'],
            'asOf'     => $f['to'],
            'prevFrom' => $shift($f['from']),
            'prevTo'   => $shift($f['to']),
            'prevAsOf' => $shift($f['to']),
        ];
        $summary = ExecutiveSummary::build($this->ledger, $win);
        $title   = $this->rlang('exec-summary', 'Executive Summary');

        if ($this->wantsXlsx()) {
            $rows = [];
            foreach ($summary['pnl'] as $row) {
                $rows[] = [
                    '_style' => $row['level'] === 'total' ? 'subtotal' : '',
                    'name'   => ($row['level'] === 'child' ? '   ' : '') . $row['label'],
                    'cur'    => $row['cur'],
                    'prev'   => $row['prev'],
                    'pct'    => $row['pctCur'] !== null ? number_format($row['pctCur'] * 100, 1) . '%' : '',
                    'yoy'    => $row['yoy'] !== null ? number_format($row['yoy'] * 100, 1) . '%' : '',
                ];
            }
            $rows[] = ['_style' => 'section', '_label' => ''];
            foreach ($summary['bs'] as $row) {
                if ($row['level'] === 'head') {
                    $rows[] = ['_style' => 'section', '_label' => $row['label']];

                    continue;
                }
                $rows[] = [
                    '_style' => $row['level'] === 'total' ? 'subtotal' : '',
                    'name'   => ($row['level'] === 'child' ? '   ' : '') . $row['label'],
                    'cur'    => $row['cur'],
                    'prev'   => $row['prev'],
                ];
            }

            return ReportExporter::download([
                'title'   => $title,
                'meta'    => $this->metaFor($f),
                'columns' => [
                    ['key' => 'name', 'label' => lang('Report.es_line')],
                    ['key' => 'cur', 'label' => lang('Report.es_this_period'), 'money' => true],
                    ['key' => 'prev', 'label' => lang('Report.es_last_period'), 'money' => true],
                    ['key' => 'pct', 'label' => lang('Report.es_pct_sales'), 'align' => 'right'],
                    ['key' => 'yoy', 'label' => lang('Report.es_yoy'), 'align' => 'right'],
                ],
                'rows'    => $rows,
            ]);
        }

        return view('reports/executive_summary', [
            'title'   => $title,
            'f'       => $f,
            'summary' => $summary,
            'from'    => $f['from'],
            'to'      => $f['to'],
        ]);
    }

    // ------------------------------------------------------------------ Cash Flow

    public function cashFlow()
    {
        $f = ReportFilter::resolve();

        if ($f['compare'] !== '') {
            $cols = $this->ledger->periodColumns($f['compare'], $f['from'], $f['to']);
            $data = $this->ledger->cashFlowMulti($cols);

            if ($this->wantsXlsx()) {
                return $this->exportComparative('Cash Flow (comparative)', $f, $data['columns'], [
                    ['type' => 'group', 'g' => $data['groups']['operating']],
                    ['type' => 'group', 'g' => $data['groups']['investing']],
                    ['type' => 'group', 'g' => $data['groups']['financing']],
                    ['type' => 'row', 'label' => 'NET CHANGE IN CASH', 'series' => $data['net_change'], 'style' => 'subtotal'],
                    ['type' => 'row', 'label' => 'CASH — BEGINNING', 'series' => $data['opening'], 'style' => 'subtotal'],
                    ['type' => 'row', 'label' => 'CASH — END', 'series' => $data['closing'], 'style' => 'total'],
                ]);
            }

            return view('reports/cash_flow_multi', ['title' => $this->rlang('cashflow_comparative', 'Cash Flow — comparative'), 'f' => $f, 'data' => $data]);
        }

        $data = $this->ledger->cashFlow($f['from'], $f['to']);

        if ($this->wantsXlsx()) {
            $rows = [];
            foreach (['operating', 'investing', 'financing'] as $g) {
                $rows[] = ['_style' => 'section', '_label' => $data['groups'][$g]['label']];
                foreach ($data['groups'][$g]['rows'] as $r) {
                    $rows[] = ['name' => trim($r['code'] . ' ' . $r['name']), 'amt' => $r['amount']];
                }
                $rows[] = ['_style' => 'subtotal', 'name' => 'Total', 'amt' => $data['groups'][$g]['total']];
            }
            $rows[] = ['_style' => 'subtotal', 'name' => 'NET CHANGE IN CASH', 'amt' => $data['net_change']];
            $rows[] = ['_style' => 'subtotal', 'name' => 'CASH — BEGINNING', 'amt' => $data['opening']];
            $rows[] = ['_style' => 'total', 'name' => 'CASH — END', 'amt' => $data['closing']];

            return ReportExporter::download([
                'title'   => $this->rlang('cashflow', 'Cash Flow'),
                'meta'    => $this->metaFor($f),
                'columns' => [['key' => 'name', 'label' => 'Account'], ['key' => 'amt', 'label' => 'Inflow / (Outflow) Rp', 'money' => true]],
                'rows'    => $rows,
            ]);
        }

        return view('reports/cash_flow', ['title' => $this->rlang('cashflow', 'Cash Flow'), 'f' => $f, 'data' => $data]);
    }

    // ------------------------------------------------------------------ Aging

    public function arAging()
    {
        return $this->aging('customer', $this->rlang('s-aging-sum', 'AR Aging (summary)'), 'Piutang Usaha / Accounts Receivable');
    }

    public function apAging()
    {
        return $this->aging('supplier', $this->rlang('p-aging-sum', 'AP Aging (summary)'), 'Hutang Usaha / Accounts Payable');
    }

    private function aging(string $kind, string $title, string $heading)
    {
        $f    = ReportFilter::resolve();
        $data = $this->ledger->aging($kind, $f['asOf']);

        if ($this->wantsXlsx()) {
            $rows = [];
            foreach ($data['rows'] as $r) {
                $rows[] = ['name' => $r['name'], 'cur' => $r['current'], 'b30' => $r['b30'],
                    'b60' => $r['b60'], 'b90' => $r['b90'], 'b90p' => $r['b90p'], 'tot' => $r['total']];
            }
            $t = $data['totals'];
            $rows[] = ['_style' => 'total', 'name' => 'TOTAL', 'cur' => $t['current'], 'b30' => $t['b30'],
                'b60' => $t['b60'], 'b90' => $t['b90'], 'b90p' => $t['b90p'], 'tot' => $t['total']];

            return ReportExporter::download([
                'title'   => $title,
                'meta'    => ['Company' => company_name(), 'As of' => date_id($f['asOf'])],
                'columns' => [
                    ['key' => 'name', 'label' => $kind === 'customer' ? 'Customer' : 'Supplier'],
                    ['key' => 'cur', 'label' => 'Current', 'money' => true], ['key' => 'b30', 'label' => '1-30', 'money' => true],
                    ['key' => 'b60', 'label' => '31-60', 'money' => true], ['key' => 'b90', 'label' => '61-90', 'money' => true],
                    ['key' => 'b90p', 'label' => '> 90', 'money' => true], ['key' => 'tot', 'label' => 'Total', 'money' => true],
                ],
                'rows' => $rows,
            ]);
        }

        return view('reports/aging', [
            'title' => $title, 'f' => $f, 'asOf' => $f['asOf'],
            'kind'  => $kind, 'heading' => $heading, 'data' => $data,
        ]);
    }

    // ------------------------------------------------------------------ Consolidation

    public function consolidation()
    {
        // Consolidation sums by account code and assumes a shared base currency,
        // so only companies with the same base as the active one are eligible.
        $base      = base_code();
        $companies = model(\App\Models\CompanyModel::class)
            ->where('is_active', 1)->where('base_currency', $base)
            ->orderBy('code')->findAll();
        $picked = array_map('intval', (array) $this->request->getGet('c'));
        $picked = array_values(array_intersect($picked, array_map(static fn ($c) => (int) $c['id'], $companies)));
        if (! $picked) {
            $picked = array_map(static fn ($c) => (int) $c['id'], $companies);
        }
        $f    = ReportFilter::resolve();
        $data = $this->ledger->consolidated($picked, $f['from'], $f['to'], $f['asOf']);

        if ($this->wantsXlsx()) {
            $rows = [];
            $pl   = static function (array $g) use (&$rows) {
                if (! $g['rows']) {
                    return;
                }
                $rows[] = ['_style' => 'section', '_label' => $g['label']];
                foreach ($g['rows'] as $r) {
                    $rows[] = ['name' => trim($r['code'] . ' ' . $r['name']), 'amt' => $r['amount']];
                }
                $rows[] = ['_style' => 'subtotal', 'name' => 'Total', 'amt' => $g['total']];
            };
            $rows[] = ['_style' => 'section', '_label' => 'INCOME STATEMENT'];
            foreach (['revenue', 'cogs', 'expense', 'other_income', 'other_expense'] as $g) {
                $pl($data['pl']['groups'][$g]);
            }
            $rows[] = ['_style' => 'total', 'name' => 'NET INCOME', 'amt' => $data['pl']['net_income']];
            $rows[] = ['_style' => 'section', '_label' => 'BALANCE SHEET (per ' . date_id($f['asOf']) . ')'];
            foreach (['asset', 'liability', 'equity'] as $g) {
                $pl($data['bs']['groups'][$g]);
            }
            $rows[] = ['_style' => 'total', 'name' => 'TOTAL LIAB + EQUITY', 'amt' => $data['bs']['liab_equity']];

            $codes = array_map(static fn ($c) => $c['code'], array_filter($companies, static fn ($c) => in_array((int) $c['id'], $picked, true)));

            return ReportExporter::download([
                'title'   => $this->rlang('consolidated', 'Consolidated Report'),
                'meta'    => ['Companies' => implode(' + ', $codes), 'Period' => $f['label']],
                'columns' => [['key' => 'name', 'label' => 'Account'], ['key' => 'amt', 'label' => 'Amount (Rp)', 'money' => true]],
                'rows'    => $rows,
            ]);
        }

        return view('reports/consolidation', [
            'title'     => $this->rlang('consolidated', 'Consolidated Report'), 'f' => $f,
            'companies' => $companies, 'picked' => $picked,
            'from'      => $f['from'], 'to' => $f['to'], 'asOf' => $f['asOf'],
            'data'      => $data,
        ]);
    }
}
