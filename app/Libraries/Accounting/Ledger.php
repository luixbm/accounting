<?php

namespace App\Libraries\Accounting;

use App\Models\AccountModel;
use Config\Database;

/**
 * Read-side reporting: every figure is derived from POSTED journal lines,
 * always in base currency (debit_base / credit_base).
 *
 * Sign convention for returned "balance" values: debit-normal accounts are
 * positive when debit > credit; credit-normal accounts are positive when
 * credit > debit. i.e. a positive balance always means "normal side".
 */
class Ledger
{
    private $db;
    private AccountModel $accounts;

    /** @var list<int> companies this ledger reports on */
    private array $companies;

    /**
     * @param list<int>|null $companies  defaults to the active company; pass
     *                                   several ids for a consolidated view
     */
    public function __construct(?array $companies = null)
    {
        $this->db        = Database::connect();
        $this->accounts  = model(AccountModel::class);
        $this->companies = $companies !== null && $companies !== []
            ? array_values(array_map('intval', $companies))
            : [active_company_id()];
    }

    private function baseQuery(?string $from, ?string $to)
    {
        // A voided journal keeps its original postings in the ledger; its
        // paired reversing entry (also posted) is what cancels it out. Only
        // drafts are excluded.
        $b = $this->db->table('journal_lines jl')
            ->join('journals j', 'j.id = jl.journal_id')
            ->whereIn('j.company_id', $this->companies)
            ->whereIn('j.status', ['posted', 'void']);
        if ($from !== null) {
            $b->where('j.entry_date >=', $from);
        }
        if ($to !== null) {
            $b->where('j.entry_date <=', $to);
        }

        return $b;
    }

    /**
     * Raw debit/credit movement per account within a date window.
     *
     * @return array<int,array{debit: float, credit: float}> keyed by account_id
     */
    public function movements(?string $from, ?string $to): array
    {
        $rows = $this->baseQuery($from, $to)
            ->select('jl.account_id, SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c')
            ->groupBy('jl.account_id')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['account_id']] = ['debit' => (float) $r['d'], 'credit' => (float) $r['c']];
        }

        return $out;
    }

    /** Net signed balance (normal-side positive) for one account as of a date. */
    public function accountBalance(int $accountId, ?string $asOf = null): float
    {
        $row = $this->baseQuery(null, $asOf)
            ->select('SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c')
            ->where('jl.account_id', $accountId)
            ->get()->getRowArray();

        $d   = (float) ($row['d'] ?? 0);
        $c   = (float) ($row['c'] ?? 0);
        $acc = $this->accounts->find($accountId);

        return ($acc && $acc['normal_balance'] === 'K') ? $c - $d : $d - $c;
    }

    /**
     * Trial balance rows for a period.
     *
     * @return array{rows: list<array<string,mixed>>, totals: array<string,float>}
     */
    public function trialBalance(string $from, string $to, bool $includeZeros = false): array
    {
        $opening = $this->movements(null, $this->dayBefore($from));
        $period  = $this->movements($from, $to);
        $accts   = $this->accounts->orderBy('code', 'ASC')->findAll();

        $headerMeta = [];
        foreach ($accts as $a) {
            if ((int) $a['is_group'] === 1) {
                $headerMeta[(int) $a['id']] = ['code' => $a['code'], 'name' => $a['name']];
            }
        }
        $zero6 = static fn (): array => ['open_d' => 0.0, 'open_c' => 0.0, 'mv_d' => 0.0, 'mv_c' => 0.0, 'end_d' => 0.0, 'end_c' => 0.0];

        $rows   = [];
        $totals = $zero6();
        $blocks = []; // headerCode => ['code','name','rows'=>[], 'subtotal'=>zero6]

        foreach ($accts as $a) {
            if ((int) $a['is_group'] === 1) {
                continue;
            }
            $id      = (int) $a['id'];
            $oNet    = ($opening[$id]['debit'] ?? 0) - ($opening[$id]['credit'] ?? 0);
            $mvD     = $period[$id]['debit'] ?? 0;
            $mvC     = $period[$id]['credit'] ?? 0;
            $eNet    = $oNet + $mvD - $mvC;

            if (! $includeZeros && abs($oNet) < 0.005 && abs($mvD) < 0.005 && abs($mvC) < 0.005 && abs($eNet) < 0.005) {
                continue;
            }

            $row = [
                'code'      => $a['code'],
                'name'      => $a['name'],
                'type'      => $a['type'],
                'open_d'    => $oNet > 0 ? $oNet : 0,
                'open_c'    => $oNet < 0 ? -$oNet : 0,
                'mv_d'      => $mvD,
                'mv_c'      => $mvC,
                'end_d'     => $eNet > 0 ? $eNet : 0,
                'end_c'     => $eNet < 0 ? -$eNet : 0,
            ];
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k];
            }
            $rows[] = $row;

            $g   = $headerMeta[(int) ($a['parent_id'] ?? 0)] ?? ['code' => '', 'name' => 'Lainnya / Other'];
            $key = $g['code'] !== '' ? $g['code'] : '~';
            $blocks[$key] ??= ['code' => $g['code'], 'name' => $g['name'], 'rows' => [], 'subtotal' => $zero6()];
            $blocks[$key]['rows'][] = $row;
            foreach ($blocks[$key]['subtotal'] as $k => $_) {
                $blocks[$key]['subtotal'][$k] += $row[$k];
            }
        }
        uksort($blocks, static function ($x, $y) {
            if ($x === '~') { return 1; }
            if ($y === '~') { return -1; }

            return strcmp((string) $x, (string) $y);
        });

        return ['rows' => $rows, 'totals' => $totals, 'blocks' => array_values($blocks)];
    }

    /**
     * General ledger for one account.
     *
     * @return array{opening: float, closing: float, lines: list<array<string,mixed>>}
     */
    public function generalLedger(int $accountId, string $from, string $to): array
    {
        $acc     = $this->accounts->find($accountId);
        $isCredit = $acc && $acc['normal_balance'] === 'K';

        $openRow = $this->baseQuery(null, $this->dayBefore($from))
            ->select('SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c')
            ->where('jl.account_id', $accountId)
            ->get()->getRowArray();
        $openNet = (float) ($openRow['d'] ?? 0) - (float) ($openRow['c'] ?? 0);
        $opening = $isCredit ? -$openNet : $openNet;

        $lines = $this->baseQuery($from, $to)
            ->select('j.id AS journal_id, j.journal_no, j.entry_date, j.reference, jl.memo,
                      jl.debit_base AS debit, jl.credit_base AS credit,
                      customers.name AS customer_name, suppliers.name AS supplier_name')
            ->join('customers', 'customers.id = jl.customer_id', 'left')
            ->join('suppliers', 'suppliers.id = jl.supplier_id', 'left')
            ->where('jl.account_id', $accountId)
            ->orderBy('j.entry_date', 'ASC')
            ->orderBy('j.id', 'ASC')
            ->get()->getResultArray();

        $running = $opening;
        foreach ($lines as &$l) {
            $delta   = $isCredit ? ((float) $l['credit'] - (float) $l['debit']) : ((float) $l['debit'] - (float) $l['credit']);
            $running += $delta;
            $l['balance'] = $running;
        }
        unset($l);

        return ['opening' => $opening, 'closing' => $running, 'lines' => $lines];
    }

    /**
     * Header (group) accounts indexed for statement grouping.
     *
     * @param list<array<string,mixed>> $accts
     * @param list<string>              $types
     *
     * @return array{0: array<string,list<array{id:int,code:string,name:string}>>, 1: array<int,bool>}
     *         [ type => [headers sorted by code], headerId => true ]
     */
    private function headerIndex(array $accts, array $types): array
    {
        $byType = [];
        $byId   = [];
        foreach ($accts as $a) {
            if ((int) $a['is_group'] !== 1 || ! in_array($a['type'], $types, true)) {
                continue;
            }
            $bucket                       = $a['type'] === 'contra_asset' ? 'asset' : $a['type'];
            $byType[$bucket][]            = ['id' => (int) $a['id'], 'code' => $a['code'], 'name' => $a['name']];
            $byId[(int) $a['id']]         = true;
        }
        foreach ($byType as &$hs) {
            usort($hs, static fn ($x, $y) => strcmp($x['code'], $y['code']));
        }
        unset($hs);

        return [$byType, $byId];
    }

    /**
     * Income statement for a period, grouped by account type.
     *
     * @return array<string,mixed>
     */
    public function incomeStatement(string $from, string $to, bool $includeZeros = false): array
    {
        $mv    = $this->movements($from, $to);
        $accts = $this->accounts->orderBy('code', 'ASC')->findAll();

        [$headersByType, $headerName] = $this->headerIndex($accts, AccountModel::PNL_TYPES);

        $groups = [
            'revenue'       => ['label' => 'Pendapatan / Revenue', 'rows' => [], 'blocks' => [], 'total' => 0.0],
            'cogs'          => ['label' => 'Beban Pokok / Cost of Sales', 'rows' => [], 'blocks' => [], 'total' => 0.0],
            'expense'       => ['label' => 'Beban Operasional / Operating Expenses', 'rows' => [], 'blocks' => [], 'total' => 0.0],
            'other_income'  => ['label' => 'Pendapatan Lain-lain / Other Income', 'rows' => [], 'blocks' => [], 'total' => 0.0],
            'other_expense' => ['label' => 'Beban Lain-lain / Other Expenses', 'rows' => [], 'blocks' => [], 'total' => 0.0],
        ];
        $leafRows = []; // parent id => shown rows
        $leafSub  = []; // parent id => subtotal (every leaf, shown or not)
        $ungrp    = []; // type => ['rows'=>[], 'subtotal'=>0.0]  (leaves with no header)

        foreach ($accts as $a) {
            if (! in_array($a['type'], AccountModel::PNL_TYPES, true) || (int) $a['is_group'] === 1) {
                continue;
            }
            $id   = (int) $a['id'];
            $d    = $mv[$id]['debit'] ?? 0;
            $c    = $mv[$id]['credit'] ?? 0;
            $amt  = ($a['normal_balance'] === 'K') ? $c - $d : $d - $c; // normal-side positive
            $type = $a['type'];
            $pid  = (int) ($a['parent_id'] ?? 0);
            $show = $includeZeros || abs($amt) >= 0.005;
            $row  = ['code' => $a['code'], 'name' => $a['name'], 'amount' => $amt];

            $groups[$type]['total'] += $amt;
            if ($show) {
                $groups[$type]['rows'][] = $row;
            }
            if (isset($headerName[$pid])) {
                $leafSub[$pid] = ($leafSub[$pid] ?? 0.0) + $amt;
                if ($show) {
                    $leafRows[$pid][] = $row;
                }
            } else {
                $ungrp[$type] ??= ['rows' => [], 'subtotal' => 0.0];
                $ungrp[$type]['subtotal'] += $amt;
                if ($show) {
                    $ungrp[$type]['rows'][] = $row;
                }
            }
        }

        // every header always shows (the statement skeleton), then any strays
        foreach ($headersByType as $type => $hs) {
            foreach ($hs as $h) {
                $groups[$type]['blocks'][] = [
                    'code'     => $h['code'],
                    'name'     => $h['name'],
                    'rows'     => $leafRows[$h['id']] ?? [],
                    'subtotal' => $leafSub[$h['id']] ?? 0.0,
                ];
            }
            if (isset($ungrp[$type]) && ($ungrp[$type]['rows'] || abs($ungrp[$type]['subtotal']) >= 0.005)) {
                $groups[$type]['blocks'][] = ['code' => '', 'name' => 'Lainnya / Other', 'rows' => $ungrp[$type]['rows'], 'subtotal' => $ungrp[$type]['subtotal']];
            }
        }

        $revenue     = $groups['revenue']['total'];
        $grossProfit = $revenue - $groups['cogs']['total'];
        $operating   = $grossProfit - $groups['expense']['total'];
        $netIncome   = $operating + $groups['other_income']['total'] - $groups['other_expense']['total'];

        return [
            'groups'       => $groups,
            'revenue'      => $revenue,
            'gross_profit' => $grossProfit,
            'operating'    => $operating,
            'net_income'   => $netIncome,
        ];
    }

    /**
     * Net income for any date range (used by the balance sheet & dashboard).
     */
    public function netIncome(string $from, string $to): float
    {
        return $this->incomeStatement($from, $to)['net_income'];
    }

    /**
     * Balance sheet as of a date. Current-year earnings are computed from
     * P&L movement between the fiscal-year start and $asOf.
     *
     * @return array<string,mixed>
     */
    public function balanceSheet(string $asOf, ?string $fyStart = null, bool $includeZeros = false): array
    {
        $fyStart ??= date('Y-01-01', strtotime($asOf));
        $cumulative = $this->movements(null, $asOf);
        $accts      = $this->accounts->orderBy('code', 'ASC')->findAll();

        [$headersByType, $headerName] = $this->headerIndex($accts, AccountModel::BS_TYPES);

        $groups = [
            'asset'     => ['label' => 'AKTIVA / Assets', 'rows' => [], 'blocks' => [], 'total' => 0.0],
            'liability' => ['label' => 'KEWAJIBAN / Liabilities', 'rows' => [], 'blocks' => [], 'total' => 0.0],
            'equity'    => ['label' => 'EKUITAS / Equity', 'rows' => [], 'blocks' => [], 'total' => 0.0],
        ];
        $leafRows = [];
        $leafSub  = [];
        $ungrp    = [];

        foreach ($accts as $a) {
            if (! in_array($a['type'], AccountModel::BS_TYPES, true) || (int) $a['is_group'] === 1) {
                continue;
            }
            $id     = (int) $a['id'];
            $d      = $cumulative[$id]['debit'] ?? 0;
            $c      = $cumulative[$id]['credit'] ?? 0;
            $net    = $d - $c; // debit-positive
            $bucket = $a['type'] === 'contra_asset' ? 'asset' : $a['type'];
            $signed = $bucket === 'asset' ? $net : -$net; // present each side as its normal sign
            $pid    = (int) ($a['parent_id'] ?? 0);
            $show   = $includeZeros || abs($signed) >= 0.005;
            $row    = ['code' => $a['code'], 'name' => $a['name'], 'amount' => $signed];

            $groups[$bucket]['total'] += $signed;
            if ($show) {
                $groups[$bucket]['rows'][] = $row;
            }
            if (isset($headerName[$pid])) {
                $leafSub[$pid] = ($leafSub[$pid] ?? 0.0) + $signed;
                if ($show) {
                    $leafRows[$pid][] = $row;
                }
            } else {
                $ungrp[$bucket] ??= ['rows' => [], 'subtotal' => 0.0];
                $ungrp[$bucket]['subtotal'] += $signed;
                if ($show) {
                    $ungrp[$bucket]['rows'][] = $row;
                }
            }
        }

        foreach ($headersByType as $bucket => $hs) {
            foreach ($hs as $h) {
                $groups[$bucket]['blocks'][] = [
                    'code'     => $h['code'],
                    'name'     => $h['name'],
                    'rows'     => $leafRows[$h['id']] ?? [],
                    'subtotal' => $leafSub[$h['id']] ?? 0.0,
                ];
            }
            if (isset($ungrp[$bucket]) && ($ungrp[$bucket]['rows'] || abs($ungrp[$bucket]['subtotal']) >= 0.005)) {
                $groups[$bucket]['blocks'][] = ['code' => '', 'name' => 'Lainnya / Other', 'rows' => $ungrp[$bucket]['rows'], 'subtotal' => $ungrp[$bucket]['subtotal']];
            }
        }

        $currentEarnings = $this->netIncome($fyStart, $asOf);
        $earnRow = ['code' => '', 'name' => 'Laba (Rugi) Periode Berjalan / Current-Year Earnings', 'amount' => $currentEarnings];
        $groups['equity']['rows'][]   = $earnRow;
        $groups['equity']['total']   += $currentEarnings;
        $groups['equity']['blocks'][] = ['code' => '', 'name' => '', 'rows' => [$earnRow], 'subtotal' => $currentEarnings];

        $assets      = $groups['asset']['total'];
        $liabilities = $groups['liability']['total'];
        $equity      = $groups['equity']['total'];

        return [
            'groups'       => $groups,
            'assets'       => $assets,
            'liabilities'  => $liabilities,
            'equity'       => $equity,
            'liab_equity'  => $liabilities + $equity,
            'balanced'     => abs($assets - ($liabilities + $equity)) < 0.5,
        ];
    }

    // ------------------------------------------------------------- multi-period

    /**
     * Merge per-column `blocks` (from incomeStatement/balanceSheet) into one
     * comparative structure: one entry per header, each with per-column child
     * rows and per-column subtotals.
     *
     * @param array<int,list<array<string,mixed>>> $perColBlocks
     *
     * @return list<array{code:string,name:string,rows:list<array<string,mixed>>,subtotals:list<float>}>
     */
    private function mergeBlocks(array $perColBlocks, int $n): array
    {
        $headers = [];
        $subs    = [];
        $leaves  = [];
        foreach ($perColBlocks as $i => $blocks) {
            foreach ($blocks as $blk) {
                $hc            = $blk['code'];
                $headers[$hc] ??= $blk['name'];
                $subs[$hc]    ??= array_fill(0, $n, 0.0);
                $subs[$hc][$i] = $blk['subtotal'];
                foreach ($blk['rows'] as $r) {
                    $k = $r['code'] !== '' ? $r['code'] : '~' . $r['name'];
                    $leaves[$hc][$k] ??= ['code' => $r['code'], 'name' => $r['name'], 'amounts' => array_fill(0, $n, 0.0)];
                    $leaves[$hc][$k]['amounts'][$i] = $r['amount'];
                }
            }
        }
        uksort($headers, static function ($a, $b) {
            if ($a === '') { return 1; }
            if ($b === '') { return -1; }

            return strcmp((string) $a, (string) $b);
        });

        $out = [];
        foreach ($headers as $hc => $hn) {
            $rows = $leaves[$hc] ?? [];
            uksort($rows, static fn ($a, $b) => strcmp((string) $a, (string) $b));
            $out[] = [
                'code'      => $hc,
                'name'      => $hn,
                'rows'      => array_values($rows),
                'subtotals' => $subs[$hc] ?? array_fill(0, $n, 0.0),
            ];
        }

        return $out;
    }

    /**
     * Split a date span into reporting columns.
     *
     * @param 'month'|'quarter'|'year' $mode
     *
     * @return list<array{label:string, from:string, to:string}>
     */
    public function periodColumns(string $mode, string $from, string $to): array
    {
        $endTs  = strtotime($to);
        $cursor = strtotime($from);

        if ($mode === 'year') {
            $cursor = strtotime(date('Y-01-01', $cursor));
        } elseif ($mode === 'quarter') {
            $q      = intdiv((int) date('n', $cursor) - 1, 3) * 3 + 1;
            $cursor = strtotime(date('Y', $cursor) . '-' . sprintf('%02d', $q) . '-01');
        } else {
            $cursor = strtotime(date('Y-m-01', $cursor));
        }

        $cols = [];
        $guard = 0;
        while ($cursor <= $endTs && $guard++ < 240) {
            if ($mode === 'year') {
                $periodEnd = strtotime(date('Y-12-31', $cursor));
                $label     = date('Y', $cursor);
                $next      = strtotime('+1 year', $cursor);
            } elseif ($mode === 'quarter') {
                $periodEnd = strtotime('+3 months -1 day', $cursor);
                $label     = 'Q' . (intdiv((int) date('n', $cursor) - 1, 3) + 1) . ' ' . date('Y', $cursor);
                $next      = strtotime('+3 months', $cursor);
            } else {
                $periodEnd = strtotime(date('Y-m-t', $cursor));
                $label     = date('M Y', $cursor);
                $next      = strtotime('+1 month', $cursor);
            }
            $cols[] = [
                'label' => $label,
                'from'  => date('Y-m-d', $cursor),
                'to'    => date('Y-m-d', min($periodEnd, $endTs)),
            ];
            $cursor = $next;
        }

        return $cols;
    }

    /**
     * Comparative income statement: one amount column per period.
     *
     * @param list<array{label:string, from:string, to:string}> $cols
     *
     * @return array<string,mixed>
     */
    public function incomeStatementMulti(array $cols, bool $includeZeros = false): array
    {
        $perCol = array_map(fn ($c) => $this->incomeStatement($c['from'], $c['to'], $includeZeros), $cols);
        $n      = count($cols);
        $types  = ['revenue', 'cogs', 'expense', 'other_income', 'other_expense'];

        $groups = [];
        foreach ($types as $type) {
            $rows = [];
            foreach ($perCol as $i => $is) {
                foreach ($is['groups'][$type]['rows'] as $r) {
                    $rows[$r['code']] ??= ['code' => $r['code'], 'name' => $r['name'], 'amounts' => array_fill(0, $n, 0.0)];
                    $rows[$r['code']]['amounts'][$i] = $r['amount'];
                }
            }
            ksort($rows, SORT_STRING);
            $groups[$type] = [
                'label'  => $perCol[0]['groups'][$type]['label'],
                'rows'   => array_values($rows),
                'blocks' => $this->mergeBlocks(array_map(static fn ($is) => $is['groups'][$type]['blocks'], $perCol), $n),
                'totals' => array_map(static fn ($is) => $is['groups'][$type]['total'], $perCol),
            ];
        }

        return [
            'columns'   => array_column($cols, 'label'),
            'groups'    => $groups,
            'subtotals' => [
                'revenue'      => array_map(static fn ($is) => $is['revenue'], $perCol),
                'gross_profit' => array_map(static fn ($is) => $is['gross_profit'], $perCol),
                'operating'    => array_map(static fn ($is) => $is['operating'], $perCol),
                'net_income'   => array_map(static fn ($is) => $is['net_income'], $perCol),
            ],
        ];
    }

    /**
     * Comparative balance sheet: snapshot as of each column's period end.
     *
     * @param list<array{label:string, from:string, to:string}> $cols
     *
     * @return array<string,mixed>
     */
    public function balanceSheetMulti(array $cols, bool $includeZeros = false): array
    {
        $perCol = array_map(fn ($c) => $this->balanceSheet($c['to'], null, $includeZeros), $cols);
        $n      = count($cols);

        $groups = [];
        foreach (['asset', 'liability', 'equity'] as $type) {
            $rows = [];
            foreach ($perCol as $i => $bs) {
                foreach ($bs['groups'][$type]['rows'] as $r) {
                    $code = $r['code'] !== '' ? $r['code'] : '~' . $r['name'];
                    $rows[$code] ??= ['code' => $r['code'], 'name' => $r['name'], 'amounts' => array_fill(0, $n, 0.0)];
                    $rows[$code]['amounts'][$i] = $r['amount'];
                }
            }
            ksort($rows, SORT_STRING);
            $groups[$type] = [
                'label'  => $perCol[0]['groups'][$type]['label'],
                'rows'   => array_values($rows),
                'blocks' => $this->mergeBlocks(array_map(static fn ($bs) => $bs['groups'][$type]['blocks'], $perCol), $n),
                'totals' => array_map(static fn ($bs) => $bs['groups'][$type]['total'], $perCol),
            ];
        }

        return [
            'columns'     => array_column($cols, 'label'),
            'groups'      => $groups,
            'assets'      => array_map(static fn ($bs) => $bs['assets'], $perCol),
            'liabilities' => array_map(static fn ($bs) => $bs['liabilities'], $perCol),
            'equity'      => array_map(static fn ($bs) => $bs['equity'], $perCol),
            'liab_equity' => array_map(static fn ($bs) => $bs['liab_equity'], $perCol),
            'balanced'    => array_map(static fn ($bs) => $bs['balanced'], $perCol),
        ];
    }

    /**
     * Per-period series for the dashboard charts.
     *
     * @param list<array{label:string, from:string, to:string}> $cols
     * @param list<int>                                         $cashIds
     * @param list<int>                                         $arIds  receivable subledger accounts
     * @param list<int>                                         $apIds  payable subledger accounts
     *
     * @return list<array{label:string, revenue:float, expense:float, net:float, cash:float, ar:float, ap:float}>
     */
    public function dashboardSeries(array $cols, array $cashIds, array $arIds, array $apIds): array
    {
        $out = [];
        foreach ($cols as $c) {
            $pl  = $this->incomeStatement($c['from'], $c['to']);
            $cum = $this->movements(null, $c['to']);

            $cash = 0.0;
            foreach ($cashIds as $id) {
                $cash += ($cum[$id]['debit'] ?? 0) - ($cum[$id]['credit'] ?? 0);
            }
            $ar = 0.0;
            foreach ($arIds as $id) {
                $ar += ($cum[$id]['debit'] ?? 0) - ($cum[$id]['credit'] ?? 0);
            }
            $ap = 0.0;
            foreach ($apIds as $id) {
                $ap += ($cum[$id]['credit'] ?? 0) - ($cum[$id]['debit'] ?? 0);
            }

            $out[] = [
                'label'   => $c['label'],
                'revenue' => $pl['revenue'],
                'expense' => $pl['groups']['cogs']['total'] + $pl['groups']['expense']['total'] + $pl['groups']['other_expense']['total'],
                'net'     => $pl['net_income'],
                'cash'    => $cash,
                'ar'      => $ar,
                'ap'      => $ap,
            ];
        }

        return $out;
    }

    /**
     * Net cash & bank movement (debit - credit on is_cash accounts) for each
     * period column. Positive = cash came in.
     *
     * @param list<array{label:string, from:string, to:string}> $cols
     *
     * @return list<float>  aligned to $cols
     */
    public function cashMovementByPeriod(array $cols): array
    {
        $out = [];
        foreach ($cols as $c) {
            $row = $this->baseQuery($c['from'], $c['to'])
                ->select('SUM(jl.debit_base - jl.credit_base) AS net')
                ->join('accounts a', 'a.id = jl.account_id')
                ->where('a.is_cash', 1)
                ->get()->getRowArray();
            $out[] = (float) ($row['net'] ?? 0);
        }

        return $out;
    }

    /**
     * Top customers by invoiced sales (line amounts, base currency) in a window.
     * Voided invoices excluded; unnamed customers bucketed as "—".
     *
     * @return list<array{label:string, value:float}>  descending, max $limit
     */
    public function topCustomers(string $from, string $to, int $limit = 10): array
    {
        $rows = $this->db->table('sales_invoice_lines sil')
            ->select('COALESCE(c.name, "—") AS name, SUM(sil.amount_base) AS total')
            ->join('sales_invoices si', 'si.id = sil.invoice_id')
            ->join('customers c', 'c.id = si.customer_id', 'left')
            ->whereIn('si.company_id', $this->companies)
            ->where('si.status !=', 'void')
            ->where('si.invoice_date >=', $from)
            ->where('si.invoice_date <=', $to)
            ->groupBy('si.customer_id')
            ->orderBy('total', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        return array_map(
            static fn ($r) => ['label' => (string) $r['name'], 'value' => (float) $r['total']],
            $rows
        );
    }

    // --------------------------------------------------------- consolidation

    /**
     * Movement per account CODE across several companies (COAs share numbering).
     *
     * @return array<string,array{name:string,type:string,nb:string,is_group:int,debit:float,credit:float}>
     */
    private function movementsByCode(array $companyIds, ?string $from, ?string $to): array
    {
        $b = $this->db->table('journal_lines jl')
            ->join('journals j', 'j.id = jl.journal_id')
            ->join('accounts a', 'a.id = jl.account_id')
            ->whereIn('j.company_id', $companyIds)
            ->whereIn('j.status', ['posted', 'void']);
        if ($from !== null) {
            $b->where('j.entry_date >=', $from);
        }
        if ($to !== null) {
            $b->where('j.entry_date <=', $to);
        }
        $rows = $b->select('a.code, MIN(a.name) AS name, MIN(a.type) AS type, MIN(a.normal_balance) AS nb,
                            MIN(a.is_group) AS is_group, SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c')
            ->groupBy('a.code')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['code']] = [
                'name' => $r['name'], 'type' => $r['type'], 'nb' => $r['nb'], 'is_group' => (int) $r['is_group'],
                'debit' => (float) $r['d'], 'credit' => (float) $r['c'],
            ];
        }

        return $out;
    }

    /**
     * Consolidated Trial Balance + Income Statement + Balance Sheet for a set
     * of companies, aggregated by account code (straight sum, no eliminations).
     *
     * @param list<int> $companyIds
     *
     * @return array<string,mixed>
     */
    public function consolidated(array $companyIds, string $from, string $to, string $asOf): array
    {
        $companyIds = array_values(array_map('intval', $companyIds)) ?: [0];
        $fyStart    = date('Y-01-01', strtotime($asOf));

        $open   = $this->movementsByCode($companyIds, null, $this->dayBefore($from));
        $period = $this->movementsByCode($companyIds, $from, $to);
        $cum    = $this->movementsByCode($companyIds, null, $asOf);
        $fyMv   = $this->movementsByCode($companyIds, $fyStart, $asOf);

        // --- trial balance
        $codes = array_unique(array_merge(array_keys($open), array_keys($period)));
        sort($codes);
        $tbRows = [];
        $tbTot  = ['open_d' => 0, 'open_c' => 0, 'mv_d' => 0, 'mv_c' => 0, 'end_d' => 0, 'end_c' => 0];
        foreach ($codes as $code) {
            $meta = $period[$code] ?? $open[$code];
            if ($meta['is_group'] === 1) {
                continue;
            }
            $oNet = ($open[$code]['debit'] ?? 0) - ($open[$code]['credit'] ?? 0);
            $mvD  = $period[$code]['debit'] ?? 0;
            $mvC  = $period[$code]['credit'] ?? 0;
            $eNet = $oNet + $mvD - $mvC;
            if (abs($oNet) < 0.005 && abs($mvD) < 0.005 && abs($mvC) < 0.005 && abs($eNet) < 0.005) {
                continue;
            }
            $row = [
                'code' => $code, 'name' => $meta['name'],
                'open_d' => max($oNet, 0), 'open_c' => max(-$oNet, 0),
                'mv_d' => $mvD, 'mv_c' => $mvC,
                'end_d' => max($eNet, 0), 'end_c' => max(-$eNet, 0),
            ];
            foreach ($tbTot as $k => $_) {
                $tbTot[$k] += $row[$k];
            }
            $tbRows[] = $row;
        }

        // --- income statement (period movement)
        $plGroups = [
            'revenue'       => ['label' => 'Pendapatan / Revenue', 'rows' => [], 'total' => 0.0],
            'cogs'          => ['label' => 'Beban Pokok / Cost of Sales', 'rows' => [], 'total' => 0.0],
            'expense'       => ['label' => 'Beban Operasional / Operating Expenses', 'rows' => [], 'total' => 0.0],
            'other_income'  => ['label' => 'Pendapatan Lain-lain / Other Income', 'rows' => [], 'total' => 0.0],
            'other_expense' => ['label' => 'Beban Lain-lain / Other Expenses', 'rows' => [], 'total' => 0.0],
        ];
        foreach ($period as $code => $m) {
            if ($m['is_group'] === 1 || ! isset($plGroups[$m['type']])) {
                continue;
            }
            $amt = $m['nb'] === 'K' ? ($m['credit'] - $m['debit']) : ($m['debit'] - $m['credit']);
            if (abs($amt) < 0.005) {
                continue;
            }
            $plGroups[$m['type']]['rows'][]  = ['code' => $code, 'name' => $m['name'], 'amount' => $amt];
            $plGroups[$m['type']]['total']  += $amt;
        }
        foreach ($plGroups as &$g) {
            usort($g['rows'], static fn ($a, $b) => strcmp($a['code'], $b['code']));
        }
        unset($g);
        $revenue = $plGroups['revenue']['total'];
        $gross   = $revenue - $plGroups['cogs']['total'];
        $operate = $gross - $plGroups['expense']['total'];
        $net     = $operate + $plGroups['other_income']['total'] - $plGroups['other_expense']['total'];

        // --- balance sheet (cumulative as of date)
        $bsGroups = [
            'asset'     => ['label' => 'AKTIVA / Assets', 'rows' => [], 'total' => 0.0],
            'liability' => ['label' => 'KEWAJIBAN / Liabilities', 'rows' => [], 'total' => 0.0],
            'equity'    => ['label' => 'EKUITAS / Equity', 'rows' => [], 'total' => 0.0],
        ];
        foreach ($cum as $code => $m) {
            if ($m['is_group'] === 1 || ! in_array($m['type'], AccountModel::BS_TYPES, true)) {
                continue;
            }
            $bucket = $m['type'] === 'contra_asset' ? 'asset' : $m['type'];
            $netv   = $m['debit'] - $m['credit'];
            if (abs($netv) < 0.005) {
                continue;
            }
            $signed = $bucket === 'asset' ? $netv : -$netv;
            $bsGroups[$bucket]['rows'][]  = ['code' => $code, 'name' => $m['name'], 'amount' => $signed];
            $bsGroups[$bucket]['total']  += $signed;
        }
        $fyNet = 0.0;
        foreach ($fyMv as $m) {
            if ($m['is_group'] === 1 || ! isset($plGroups[$m['type']])) {
                continue;
            }
            $sign  = in_array($m['type'], ['revenue', 'other_income'], true) ? 1 : -1;
            $fyNet += $sign * (($m['nb'] === 'K' ? $m['credit'] - $m['debit'] : $m['debit'] - $m['credit']));
        }
        $bsGroups['equity']['rows'][] = ['code' => '', 'name' => 'Laba (Rugi) Periode Berjalan / Current-Year Earnings', 'amount' => $fyNet];
        $bsGroups['equity']['total'] += $fyNet;

        $assets = $bsGroups['asset']['total'];
        $liab   = $bsGroups['liability']['total'];
        $equity = $bsGroups['equity']['total'];

        return [
            'tb' => ['rows' => $tbRows, 'totals' => $tbTot],
            'pl' => ['groups' => $plGroups, 'revenue' => $revenue, 'gross_profit' => $gross, 'operating' => $operate, 'net_income' => $net],
            'bs' => [
                'groups' => $bsGroups, 'assets' => $assets, 'liabilities' => $liab, 'equity' => $equity,
                'liab_equity' => $liab + $equity, 'balanced' => abs($assets - ($liab + $equity)) < 0.5,
            ],
        ];
    }

    // ------------------------------------------------------------------ cash flow

    /**
     * Total signed balance across every cash/bank account as of a date
     * (positive = money on hand).
     */
    public function cashPosition(?string $asOf): float
    {
        $row = $this->baseQuery(null, $asOf)
            ->select('SUM(jl.debit_base - jl.credit_base) AS net')
            ->join('accounts a', 'a.id = jl.account_id')
            ->where('a.is_cash', 1)
            ->get()->getRowArray();

        return (float) ($row['net'] ?? 0);
    }

    /**
     * Direct-method cash flow for a period. Every non-cash line on a journal
     * that also touches a cash account contributes (credit - debit) to cash
     * inflow, grouped by that account and classified operating / investing /
     * financing via accounts.cashflow.
     *
     * @return array<string,mixed>
     */
    public function cashFlow(string $from, string $to): array
    {
        $rows = $this->baseQuery($from, $to)
            ->select('a.code, a.name, a.cashflow, SUM(jl.credit_base - jl.debit_base) AS cash_in')
            ->join('accounts a', 'a.id = jl.account_id')
            ->where('a.is_cash', 0)
            ->where("jl.journal_id IN (
                SELECT DISTINCT jl2.journal_id FROM journal_lines jl2
                JOIN accounts a2 ON a2.id = jl2.account_id WHERE a2.is_cash = 1
            )", null, false)
            ->groupBy('a.code')
            ->orderBy('a.code', 'ASC')
            ->get()->getResultArray();

        $groups = [
            'operating' => ['label' => 'Aktivitas Operasi / Operating Activities', 'rows' => [], 'total' => 0.0],
            'investing' => ['label' => 'Aktivitas Investasi / Investing Activities', 'rows' => [], 'total' => 0.0],
            'financing' => ['label' => 'Aktivitas Pendanaan / Financing Activities', 'rows' => [], 'total' => 0.0],
        ];
        foreach ($rows as $r) {
            $cls = isset($groups[$r['cashflow']]) ? $r['cashflow'] : 'operating';
            $amt = (float) $r['cash_in'];
            if (abs($amt) < 0.005) {
                continue;
            }
            $groups[$cls]['rows'][]  = ['code' => $r['code'], 'name' => $r['name'], 'amount' => $amt];
            $groups[$cls]['total']  += $amt;
        }

        $opening   = $this->cashPosition($this->dayBefore($from));
        $netChange = $groups['operating']['total'] + $groups['investing']['total'] + $groups['financing']['total'];
        $closing   = $this->cashPosition($to);

        return [
            'groups'     => $groups,
            'operating'  => $groups['operating']['total'],
            'investing'  => $groups['investing']['total'],
            'financing'  => $groups['financing']['total'],
            'net_change' => $netChange,
            'opening'    => $opening,
            'closing'    => $closing,
            'reconciles' => abs(($opening + $netChange) - $closing) < 0.5,
        ];
    }

    /**
     * Comparative cash flow: one column per period.
     *
     * @param list<array{label:string, from:string, to:string}> $cols
     *
     * @return array<string,mixed>
     */
    public function cashFlowMulti(array $cols): array
    {
        $perCol = array_map(fn ($c) => $this->cashFlow($c['from'], $c['to']), $cols);
        $n      = count($cols);

        $groups = [];
        foreach (['operating', 'investing', 'financing'] as $type) {
            $rows = [];
            foreach ($perCol as $i => $cf) {
                foreach ($cf['groups'][$type]['rows'] as $r) {
                    $rows[$r['code']] ??= ['code' => $r['code'], 'name' => $r['name'], 'amounts' => array_fill(0, $n, 0.0)];
                    $rows[$r['code']]['amounts'][$i] = $r['amount'];
                }
            }
            ksort($rows);
            $groups[$type] = [
                'label'  => $perCol[0]['groups'][$type]['label'],
                'rows'   => array_values($rows),
                'totals' => array_map(static fn ($cf) => $cf['groups'][$type]['total'], $perCol),
            ];
        }

        return [
            'columns'    => array_column($cols, 'label'),
            'groups'     => $groups,
            'net_change' => array_map(static fn ($cf) => $cf['net_change'], $perCol),
            'opening'    => array_map(static fn ($cf) => $cf['opening'], $perCol),
            'closing'    => array_map(static fn ($cf) => $cf['closing'], $perCol),
        ];
    }

    // ------------------------------------------------------------------ jobs

    /**
     * Profit &amp; loss for one job, from posted journal lines carrying job_id.
     *
     * @return array<string,mixed>
     */
    public function jobProfitLoss(int $jobId, ?string $from = null, ?string $to = null): array
    {
        $rows = $this->baseQuery($from, $to)
            ->select('a.id, a.code, a.name, a.type, a.normal_balance,
                      SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c')
            ->join('accounts a', 'a.id = jl.account_id')
            ->where('jl.job_id', $jobId)
            ->groupBy('a.id')
            ->orderBy('a.code', 'ASC')
            ->get()->getResultArray();

        $groups = [
            'revenue'       => ['label' => 'Pendapatan / Revenue', 'rows' => [], 'total' => 0.0],
            'cogs'          => ['label' => 'Beban Pokok / Direct Cost', 'rows' => [], 'total' => 0.0],
            'expense'       => ['label' => 'Beban Operasional / Expense', 'rows' => [], 'total' => 0.0],
            'other_income'  => ['label' => 'Pendapatan Lain / Other Income', 'rows' => [], 'total' => 0.0],
            'other_expense' => ['label' => 'Beban Lain / Other Expense', 'rows' => [], 'total' => 0.0],
        ];

        foreach ($rows as $r) {
            if (! isset($groups[$r['type']])) {
                continue; // balance-sheet account tagged to a job — ignore in P&L
            }
            $amt = $r['normal_balance'] === 'K' ? ((float) $r['c'] - (float) $r['d']) : ((float) $r['d'] - (float) $r['c']);
            if (abs($amt) < 0.005) {
                continue;
            }
            $groups[$r['type']]['rows'][] = ['code' => $r['code'], 'name' => $r['name'], 'amount' => $amt];
            $groups[$r['type']]['total'] += $amt;
        }

        $revenue = $groups['revenue']['total'];
        $direct  = $groups['cogs']['total'];
        $gross   = $revenue - $direct;
        $net     = $gross - $groups['expense']['total'] + $groups['other_income']['total'] - $groups['other_expense']['total'];

        return [
            'groups'       => $groups,
            'revenue'      => $revenue,
            'direct_cost'  => $direct,
            'gross_profit' => $gross,
            'net'          => $net,
            'margin'       => $revenue != 0.0 ? $net / $revenue * 100 : 0.0,
        ];
    }

    /**
     * One-line P&amp;L totals for every job (for the jobs index).
     *
     * @return array<int,array{revenue:float,cost:float,net:float}>  keyed by job_id
     */
    public function jobSummaries(): array
    {
        $rows = $this->baseQuery(null, null)
            ->select("jl.job_id,
                SUM(CASE WHEN a.type IN ('revenue','other_income') THEN jl.credit_base - jl.debit_base ELSE 0 END) AS revenue,
                SUM(CASE WHEN a.type IN ('cogs','expense','other_expense') THEN jl.debit_base - jl.credit_base ELSE 0 END) AS cost")
            ->join('accounts a', 'a.id = jl.account_id')
            ->where('jl.job_id IS NOT NULL')
            ->groupBy('jl.job_id')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $rev = (float) $r['revenue'];
            $cst = (float) $r['cost'];
            $out[(int) $r['job_id']] = ['revenue' => $rev, 'cost' => $cst, 'net' => $rev - $cst];
        }

        return $out;
    }

    /**
     * AR or AP aging by party, bucketed on the journal entry date.
     *
     * @param 'customer'|'supplier' $kind
     *
     * @return array{rows: list<array<string,mixed>>, totals: array<string,float>}
     */
    public function aging(string $kind, string $asOf): array
    {
        $partyCol = $kind === 'customer' ? 'jl.customer_id' : 'jl.supplier_id';
        $partyTbl = $kind === 'customer' ? 'customers' : 'suppliers';

        $rows = $this->baseQuery(null, $asOf)
            ->select("{$partyCol} AS party_id, p.code AS party_code, p.name AS party_name,
                      j.entry_date,
                      SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c")
            ->join('accounts a', 'a.id = jl.account_id')
            ->join("{$partyTbl} p", "p.id = {$partyCol}")
            ->where('a.subledger', $kind)
            ->where("{$partyCol} IS NOT NULL")
            ->groupBy(["{$partyCol}", 'j.entry_date'])
            ->get()->getResultArray();

        // For customers (AR) outstanding = debit - credit; for suppliers (AP) = credit - debit.
        $sign    = $kind === 'customer' ? 1 : -1;
        $buckets = ['current' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0];
        $byParty = [];
        $asOfTs  = strtotime($asOf);

        foreach ($rows as $r) {
            $pid = (int) $r['party_id'];
            $out = $sign * ((float) $r['d'] - (float) $r['c']);
            if (abs($out) < 0.005) {
                continue;
            }
            $age = (int) floor(($asOfTs - strtotime($r['entry_date'])) / 86400);
            if ($age <= 0) {
                $bk = 'current';
            } elseif ($age <= 30) {
                $bk = 'b30';
            } elseif ($age <= 60) {
                $bk = 'b60';
            } elseif ($age <= 90) {
                $bk = 'b90';
            } else {
                $bk = 'b90p';
            }

            $byParty[$pid] ??= [
                'code' => $r['party_code'], 'name' => $r['party_name'],
                'current' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0, 'total' => 0,
            ];
            $byParty[$pid][$bk]     += $out;
            $byParty[$pid]['total'] += $out;
            $buckets[$bk]           += $out;
        }

        $byParty = array_values(array_filter($byParty, static fn ($p) => abs($p['total']) >= 0.005));
        usort($byParty, static fn ($a, $b) => strcmp($a['name'], $b['name']));

        $buckets['total'] = array_sum($buckets);

        return ['rows' => $byParty, 'totals' => $buckets];
    }

    /**
     * Statement (ledger) for a single customer or supplier across their
     * subledger control accounts.
     */
    public function partyStatement(string $kind, int $partyId, string $from, string $to): array
    {
        $partyCol = $kind === 'customer' ? 'jl.customer_id' : 'jl.supplier_id';
        $sign     = $kind === 'customer' ? 1 : -1;

        $openRow = $this->baseQuery(null, $this->dayBefore($from))
            ->select('SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c')
            ->join('accounts a', 'a.id = jl.account_id')
            ->where('a.subledger', $kind)
            ->where("{$partyCol}", $partyId)
            ->get()->getRowArray();
        $opening = $sign * ((float) ($openRow['d'] ?? 0) - (float) ($openRow['c'] ?? 0));

        $lines = $this->baseQuery($from, $to)
            ->select('j.journal_no, j.entry_date, j.reference, jl.memo, jl.debit_base AS debit, jl.credit_base AS credit')
            ->join('accounts a', 'a.id = jl.account_id')
            ->where('a.subledger', $kind)
            ->where("{$partyCol}", $partyId)
            ->orderBy('j.entry_date', 'ASC')->orderBy('j.id', 'ASC')
            ->get()->getResultArray();

        $running = $opening;
        foreach ($lines as &$l) {
            $running += $sign * ((float) $l['debit'] - (float) $l['credit']);
            $l['balance'] = $running;
        }
        unset($l);

        return ['opening' => $opening, 'closing' => $running, 'lines' => $lines];
    }

    private function dayBefore(string $date): string
    {
        return date('Y-m-d', strtotime($date . ' -1 day'));
    }
}
