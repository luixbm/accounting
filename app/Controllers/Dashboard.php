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

        // Monthly P&L series (one pass)
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

        $cashMoveM  = $ledger->cashMovementByPeriod($cols);
        $topClients = $ledger->topCustomers($from, $ytdTo, 10);

        // YTD KPIs
        $ytd   = $ledger->incomeStatement($from, $ytdTo);
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
            'years'      => range((int) date('Y') + 1, (int) date('Y') - 4),
            'ytdTo'      => $ytdTo,
            'labels'     => $labels,
            'revM'       => $revM,
            'cosM'       => $cosM,
            'gopPctM'    => $gopPct,
            'ebitdaM'    => $ebitM,
            'cashMoveM'  => $cashMoveM,
            'topClients' => $topClients,
            'k'          => [
                'revenue' => $ytd['revenue'],
                'gopPct'  => abs($ytd['revenue']) > 0.005 ? $ytd['gross_profit'] / $ytd['revenue'] : 0.0,
                'ebitda'  => $ytd['operating'],
                'net'     => $ytd['net_income'],
                'ar'      => $ar,
                'ap'      => $ap,
            ],
            'recent'     => $recent,
            'draftCount' => $draftCount,
        ]);
    }
}
