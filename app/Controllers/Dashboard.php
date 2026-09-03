<?php

namespace App\Controllers;

use App\Libraries\Accounting\Ledger;
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

        $prev     = $year - 1;
        $colsPrev = $ledger->periodColumns('month', $prev . '-01-01', $prev . '-12-31');
        $ismPrev  = $ledger->incomeStatementMulti($colsPrev);
        $revPrevM  = $ismPrev['subtotals']['revenue'];
        $ebitPrevM = $ismPrev['subtotals']['operating'];
        $hasPrev   = (bool) array_filter($revPrevM, static fn ($v) => abs($v) > 0.005)
                  || (bool) array_filter($ebitPrevM, static fn ($v) => abs($v) > 0.005);

        $cashMoveM  = $ledger->cashMovementByPeriod($cols);
        $topClients = $ledger->topCustomers($from, $ytdTo, 10);

        // YTD KPIs (+ same window last year for the revenue-vs-LY figure)
        $ytd     = $ledger->incomeStatement($from, $ytdTo);
        $ytdPrev = $ledger->incomeStatement($prev . '-01-01', $prev . substr($ytdTo, 4));
        $revLyPct = abs($ytdPrev['revenue']) > 0.005 ? $ytd['revenue'] / $ytdPrev['revenue'] : null;
        $arIds = array_map(static fn ($a) => (int) $a['id'], $accounts->where('subledger', 'customer')->findAll());
        $apIds = array_map(static fn ($a) => (int) $a['id'], $accounts->where('subledger', 'supplier')->findAll());
        $ar    = 0.0;
        foreach ($arIds as $id) {
            $ar += $ledger->accountBalance($id, $ytdTo);
        }
        $ap = 0.0;
        foreach ($apIds as $id) {
            $ap += $ledger->accountBalance($id, $ytdTo);
        }

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
            'hasPrev'    => $hasPrev,
            'years'      => range((int) date('Y') + 1, (int) date('Y') - 4),
            'ytdTo'      => $ytdTo,
            'labels'     => $labels,
            'revM'       => $revM,
            'revPrevM'   => $revPrevM,
            'cosM'       => $cosM,
            'gopPctM'    => $gopPct,
            'ebitdaM'    => $ebitM,
            'ebitdaPrevM' => $ebitPrevM,
            'cashMoveM'  => $cashMoveM,
            'topClients' => $topClients,
            'k'          => [
                'revenue'  => $ytd['revenue'],
                'revLyPct' => $revLyPct,
                'gopPct'   => abs($ytd['revenue']) > 0.005 ? $ytd['gross_profit'] / $ytd['revenue'] : 0.0,
                'ebitda'   => $ytd['operating'],
                'net'      => $ytd['net_income'],
                'ar'       => $ar,
                'ap'       => $ap,
            ],
            'recent'     => $recent,
            'draftCount' => $draftCount,
        ]);
    }
}
