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

        // Editable reporting window
        $to   = $this->request->getGet('to') ?: date('Y-m-d');
        $from = $this->request->getGet('from') ?: date('Y-01-01', strtotime($to));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        // Cash & bank position as of $to
        $cashTotal = 0.0;
        $cashRows  = [];
        $cashIds   = [];
        foreach ($accounts->cashAccounts() as $c) {
            $bal        = $ledger->accountBalance((int) $c['id'], $to);
            $cashTotal += $bal;
            $cashIds[]  = (int) $c['id'];
            $cashRows[] = ['name' => $c['name'], 'balance' => $bal];
        }

        $arIds = array_map(static fn ($a) => (int) $a['id'], $accounts->where('subledger', 'customer')->findAll());
        $apIds = array_map(static fn ($a) => (int) $a['id'], $accounts->where('subledger', 'supplier')->findAll());
        $ar    = 0.0;
        foreach ($arIds as $id) {
            $ar += $ledger->accountBalance($id, $to);
        }
        $ap    = 0.0;
        foreach ($apIds as $id) {
            $ap += $ledger->accountBalance($id, $to);
        }

        $pl = $ledger->incomeStatement($from, $to);

        // Monthly series for the charts (cap at 24 columns)
        $cols   = $ledger->periodColumns('month', $from, $to);
        if (count($cols) > 24) {
            $cols = array_slice($cols, -24);
        }
        $series = $ledger->dashboardSeries($cols, $cashIds, $arIds, $apIds);

        // Expense breakdown for the window (COGS + opex + other expense)
        $breakdown = [];
        foreach (['cogs', 'expense', 'other_expense'] as $g) {
            foreach ($pl['groups'][$g]['rows'] as $r) {
                $breakdown[] = ['label' => $r['name'], 'value' => $r['amount']];
            }
        }
        usort($breakdown, static fn ($a, $b) => $b['value'] <=> $a['value']);
        $breakdown = array_slice($breakdown, 0, 8);

        $recent = $journals
            ->select('journals.*, currencies.code AS currency_code')
            ->join('currencies', 'currencies.id = journals.currency_id', 'left')
            ->orderBy('journals.id', 'DESC')
            ->findAll(10);

        $draftCount = $journals->where('status', 'draft')->countAllResults();

        return view('dashboard/index', [
            'title'      => 'Dashboard',
            'from'       => $from,
            'to'         => $to,
            'cashTotal'  => $cashTotal,
            'cashRows'   => $cashRows,
            'ar'         => $ar,
            'ap'         => $ap,
            'revenue'    => $pl['revenue'],
            'netIncome'  => $pl['net_income'],
            'grossProfit'=> $pl['gross_profit'],
            'series'     => $series,
            'breakdown'  => $breakdown,
            'recent'     => $recent,
            'draftCount' => $draftCount,
        ]);
    }
}
