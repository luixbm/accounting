<?php

namespace App\Controllers;

use App\Libraries\Budget\Budget;
use App\Models\AccountModel;
use App\Models\BudgetLineModel;
use App\Models\BudgetVersionModel;
use Config\Database;

/**
 * Manage budget versions and their per-account monthly figures. Bulk entry is
 * the spreadsheet import (BudgetImportController); this screen covers version
 * CRUD, a read-only grid, and one-account-at-a-time edits.
 */
class BudgetVersionController extends BaseController
{
    private function versions(): BudgetVersionModel
    {
        return model(BudgetVersionModel::class);
    }

    private function lines(): BudgetLineModel
    {
        return model(BudgetLineModel::class);
    }

    public function index()
    {
        $rows = $this->versions()->orderBy('year', 'DESC')->orderBy('is_default', 'DESC')->orderBy('name', 'ASC')->findAll();
        foreach ($rows as &$r) {
            $r['stats'] = Budget::totals((int) $r['id']);
        }
        unset($r);

        return view('budgets/index', ['title' => lang('Nav.budgets'), 'rows' => $rows]);
    }

    public function new()
    {
        return view('budgets/form', ['title' => lang('Budget.new'), 'row' => null]);
    }

    public function edit(int $id)
    {
        $row = $this->versions()->find($id);
        if (! $row) {
            return redirect()->to('budgets')->with('error', lang('Budget.not_found'));
        }

        return view('budgets/form', ['title' => lang('Budget.edit'), 'row' => $row]);
    }

    public function create()
    {
        $data = $this->payload();
        $data['created_by'] = auth()->id();
        if (! $this->versions()->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->versions()->errors());
        }
        $id = (int) $this->versions()->getInsertID();
        if ($data['is_default']) {
            $this->versions()->clearDefault((int) $data['year'], $id);
        }

        return redirect()->to('budgets/' . $id)->with('message', lang('Budget.saved'));
    }

    public function update(int $id)
    {
        $row = $this->versions()->find($id);
        if (! $row) {
            return redirect()->to('budgets')->with('error', lang('Budget.not_found'));
        }
        $data = $this->payload();
        if (! $this->versions()->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->versions()->errors());
        }
        if ($data['is_default']) {
            $this->versions()->clearDefault((int) $data['year'], $id);
        }

        return redirect()->to('budgets/' . $id)->with('message', lang('Budget.saved'));
    }

    public function setDefault(int $id)
    {
        $row = $this->versions()->find($id);
        if ($row) {
            $this->versions()->update($id, ['is_default' => 1]);
            $this->versions()->clearDefault((int) $row['year'], $id);
        }

        return redirect()->to('budgets')->with('message', lang('Budget.default_set'));
    }

    public function delete(int $id)
    {
        if ($this->versions()->find($id)) {
            $this->versions()->delete($id);   // budget_lines cascade
        }

        return redirect()->to('budgets')->with('message', lang('Budget.deleted'));
    }

    public function show(int $id)
    {
        $ver = $this->versions()->find($id);
        if (! $ver) {
            return redirect()->to('budgets')->with('error', lang('Budget.not_found'));
        }

        $accounts = model(AccountModel::class)
            ->where('is_group', 0)->where('is_active', 1)
            ->whereIn('type', AccountModel::PNL_TYPES)
            ->orderBy('code', 'ASC')->findAll();
        $names = model(AccountModel::class)->select('id, code, name')->findAll();
        $nameOf = [];
        foreach ($names as $n) {
            $nameOf[(int) $n['id']] = $n['code'] . ' · ' . $n['name'];
        }

        return view('budgets/show', [
            'title'    => $ver['name'] . ' (' . $ver['year'] . ')',
            'ver'      => $ver,
            'accounts' => $accounts,
            'grid'     => Budget::byAccount($id),
            'nameOf'   => $nameOf,
        ]);
    }

    public function editRow(int $id, int $accountId)
    {
        $ver = $this->versions()->find($id);
        $acc = model(AccountModel::class)->find($accountId);
        if (! $ver || ! $acc) {
            return redirect()->to('budgets')->with('error', lang('Budget.not_found'));
        }

        return view('budgets/row', [
            'title'  => $ver['name'] . ' — ' . $acc['code'] . ' ' . $acc['name'],
            'ver'    => $ver,
            'acc'    => $acc,
            'months' => Budget::byAccount($id)[$accountId] ?? [],
        ]);
    }

    public function saveRow(int $id, int $accountId)
    {
        $ver = $this->versions()->find($id);
        $acc = model(AccountModel::class)->find($accountId);
        if (! $ver || ! $acc) {
            return redirect()->to('budgets')->with('error', lang('Budget.not_found'));
        }

        $db = Database::connect();
        $db->transStart();
        $db->table('budget_lines')->where('version_id', $id)->where('account_id', $accountId)->delete();
        $rows = [];
        foreach ((array) $this->request->getPost('m') as $m => $raw) {
            $m   = (int) $m;
            $amt = round((float) str_replace([',', ' '], '', (string) $raw), 2);
            if ($m >= 1 && $m <= 12 && abs($amt) >= 0.005) {
                $rows[] = ['version_id' => $id, 'account_id' => $accountId, 'period_month' => $m, 'amount' => $amt];
            }
        }
        if ($rows) {
            $db->table('budget_lines')->insertBatch($rows);
        }
        $db->transComplete();

        return redirect()->to('budgets/' . $id)->with('message', lang('Budget.row_saved'));
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'name'       => trim((string) $this->request->getPost('name')),
            'year'       => (int) $this->request->getPost('year') ?: (int) date('Y'),
            'note'       => trim((string) $this->request->getPost('note')) ?: null,
            'is_default' => $this->request->getPost('is_default') !== null ? 1 : 0,
        ];
    }
}
