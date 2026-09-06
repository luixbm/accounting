<?php

namespace App\Controllers;

use App\Libraries\Accounting\Ledger;
use App\Libraries\Report\ExecutiveSummary;
use App\Models\AccountModel;
use App\Models\JournalModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $ledger   = new Ledger();
        $accounts = model(AccountModel::class);
        $journals = model(JournalModel::class);

        // The dashboard is year-oriented (monthly charts + YTD KPIs).
        $today = date('Y-m-d');
        $year  = (int) ($this->request->getGet('year') ?: date('Y'));
        $from  = $year . '-01-01';
        $ytdTo = min($today, $year . '-12-31');
        if ($ytdTo < $from) {
            $ytdTo = $year . '-12-31';   // viewing a past year → whole year
        }

        // Optional month filter: when set, KPIs and the summary card are read
        // MTD (that month) *and* YTD (Jan → that month-end). Charts stay 12-month.
        $month = (int) $this->request->getGet('month');
        $month = ($month >= 1 && $month <= 12) ? $month : 0;
        $prev  = $year - 1;
        if ($month > 0) {
            $mFrom = sprintf('%04d-%02d-01', $year, $month);
            $mTo   = date('Y-m-t', strtotime($mFrom));
            $winTo = min($ytdTo, $mTo);
        } else {
            $mFrom = $mTo = null;
            $winTo = $ytdTo;
        }
        $winPrevTo = $prev . substr($winTo, 4);

        // 12 monthly columns
        $cols   = $ledger->periodColumns('month', $from, $year . '-12-31');
        $labels = array_map(static fn ($c) => substr((string) $c['label'], 0, 3), $cols);

        // Monthly P&L series (one pass) + the same for the prior year
        $ism    = $ledger->incomeStatementMulti($cols);
        $revM   = $ism['subtotals']['revenue'];
        $gopM   = $ism['subtotals']['gross_profit'];
        $ebitM  = $ism['subtotals']['operating'];               // EBITDA = Revenue − COGS − Opex
        $cosM   = $ism['groups']['cogs']['totals'];
        $gopPct = array_map(
            static fn ($g, $r) => abs($r) > 0.005 ? $g / $r : 0.0,
            $gopM,
            $revM
        );

        $colsPrev = $ledger->periodColumns('month', $prev . '-01-01', $prev . '-12-31');
        $ismPrev  = $ledger->incomeStatementMulti($colsPrev);
        $revPrevM  = $ismPrev['subtotals']['revenue'];
        $ebitPrevM = $ismPrev['subtotals']['operating'];
        $hasPrev   = (bool) array_filter($revPrevM, static fn ($v) => abs($v) > 0.005)
                  || (bool) array_filter($ebitPrevM, static fn ($v) => abs($v) > 0.005);

        $cashMoveM  = $ledger->cashMovementByPeriod($cols);
        $topClients = $ledger->topCustomers($from, $winTo, 10);

        // YTD KPIs through the window end (+ same window last year for revenue-vs-LY)
        $ytd     = $ledger->incomeStatement($from, $winTo);
        $ytdPrev = $ledger->incomeStatement($prev . '-01-01', $winPrevTo);
        $revLyPct = abs($ytdPrev['revenue']) > 0.005 ? $ytd['revenue'] / $ytdPrev['revenue'] : null;
        $pct      = static fn (float $part, float $base): float => abs($base) > 0.005 ? $part / $base : 0.0;
        $arIds = array_map(static fn ($a) => (int) $a['id'], $accounts->where('subledger', 'customer')->findAll());
        $apIds = array_map(static fn ($a) => (int) $a['id'], $accounts->where('subledger', 'supplier')->findAll());
        $ar    = 0.0;
        foreach ($arIds as $id) {
            $ar += $ledger->accountBalance($id, $winTo);
        }
        $ap = 0.0;
        foreach ($apIds as $id) {
            $ap += $ledger->accountBalance($id, $winTo);
        }

        // MTD KPI figures (only when a month is picked)
        $mtd  = $month > 0 ? $ledger->incomeStatement($mFrom, $mTo) : null;
        $kMtd = $mtd !== null ? [
            'revenue'   => $mtd['revenue'],
            'gopPct'    => $pct($mtd['gross_profit'], $mtd['revenue']),
            'ebitdaPct' => $pct($mtd['operating'], $mtd['revenue']),
            'net'       => $mtd['net_income'],
        ] : null;

        // One-page P&L + Balance Sheet summary (this window vs the same window last year)
        $win = [
            'from'     => $from,
            'to'       => $winTo,
            'asOf'     => $winTo,
            'prevFrom' => $prev . '-01-01',
            'prevTo'   => $winPrevTo,
            'prevAsOf' => $winPrevTo,
        ];
        if ($month > 0) {
            $win['mtdFrom']     = $mFrom;
            $win['mtdTo']       = $mTo;
            $win['mtdPrevFrom'] = $prev . substr($mFrom, 4);
            $win['mtdPrevTo']   = $prev . substr($mTo, 4);
        }
        $summary = ExecutiveSummary::build($ledger, $win);

        $recent = $journals
            ->select('journals.*, currencies.code AS currency_code')
            ->join('currencies', 'currencies.id = journals.currency_id', 'left')
            ->orderBy('journals.id', 'DESC')
            ->findAll(10);
        $draftCount = $journals->where('status', 'draft')->countAllResults();

        return view('dashboard/index', [
            'title'      => 'Dashboard',
            'year'       => $year,
            'prev'       => $prev,
            'month'      => $month,
            'hasPrev'    => $hasPrev,
            'years'      => range((int) date('Y') + 1, (int) date('Y') - 4),
            'ytdTo'      => $ytdTo,
            'winTo'      => $winTo,
            'labels'     => $labels,
            'revM'       => $revM,
            'revPrevM'   => $revPrevM,
            'cosM'       => $cosM,
            'gopPctM'    => $gopPct,
            'ebitdaM'    => $ebitM,
            'ebitdaPrevM' => $ebitPrevM,
            'cashMoveM'  => $cashMoveM,
            'topClients' => $topClients,
            'summary'    => $summary,
            'k'          => [
                'revenue'   => $ytd['revenue'],
                'revLyPct'  => $revLyPct,
                'gopPct'    => $pct($ytd['gross_profit'], $ytd['revenue']),
                'ebitda'    => $ytd['operating'],
                'ebitdaPct' => $pct($ytd['operating'], $ytd['revenue']),
                'net'       => $ytd['net_income'],
                'ar'        => $ar,
                'ap'        => $ap,
            ],
            'kMtd'       => $kMtd,
            'recent'     => $recent,
            'draftCount' => $draftCount,
        ]);
    }
}
