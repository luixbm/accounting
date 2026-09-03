<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\CompanyModel;
use App\Models\CurrencyModel;

class AccountController extends BaseController
{
    private function guard(): bool
    {
        return user_can('masterdata.manage');
    }

    public function index()
    {
        $model = model(AccountModel::class);
        $all   = $model->orderBy('code', 'ASC')->findAll();

        // parent code lookup + group list for the filter bar
        $byId   = [];
        $groups = [];
        foreach ($all as $a) {
            $byId[(int) $a['id']] = $a;
        }
        foreach ($all as $a) {
            if ((int) $a['is_group'] === 1) {
                $groups[] = $a;
            }
        }

        $filters = sticky_filters('accounts', ['q', 'type', 'group', 'status']);
        if ($filters instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $filters;
        }
        $filters = [
            'q'      => trim((string) ($filters['q'] ?? '')),
            'type'   => (string) ($filters['type'] ?? ''),
            'group'  => (int) ($filters['group'] ?? 0),
            'status' => (string) ($filters['status'] ?? ''),
        ];

        // resolve the chosen group's whole subtree (id + descendants)
        $subtree = null;
        if ($filters['group'] > 0 && isset($byId[$filters['group']])) {
            $subtree  = [$filters['group'] => true];
            $changed  = true;
            while ($changed) {
                $changed = false;
                foreach ($all as $a) {
                    $pid = (int) ($a['parent_id'] ?? 0);
                    if ($pid > 0 && isset($subtree[$pid]) && ! isset($subtree[(int) $a['id']])) {
                        $subtree[(int) $a['id']] = true;
                        $changed                 = true;
                    }
                }
            }
        }

        $accounts = array_values(array_filter($all, static function ($a) use ($filters, $byId, $subtree) {
            if ($filters['q'] !== '') {
                $hay = mb_strtolower($a['code'] . ' ' . $a['name']);
                if (! str_contains($hay, mb_strtolower($filters['q']))) {
                    return false;
                }
            }
            if ($filters['type'] !== '' && $a['type'] !== $filters['type']) {
                return false;
            }
            if ($filters['status'] === 'active' && ! $a['is_active']) {
                return false;
            }
            if ($filters['status'] === 'inactive' && $a['is_active']) {
                return false;
            }
            if ($subtree !== null && ! isset($subtree[(int) $a['id']])) {
                return false;
            }

            return true;
        }));

        foreach ($accounts as &$a) {
            $pid            = (int) ($a['parent_id'] ?? 0);
            $a['parent_code'] = $pid > 0 && isset($byId[$pid]) ? $byId[$pid]['code'] : null;
            $a['parent_name'] = $pid > 0 && isset($byId[$pid]) ? $byId[$pid]['name'] : null;
        }
        unset($a);

        $otherCompanies = [];
        if (! $all) {
            $otherCompanies = model(CompanyModel::class)
                ->where('id !=', active_company_id())->where('is_active', 1)->orderBy('code')->findAll();
        }

        return view('accounts/index', [
            'title'          => 'Chart of Accounts',
            'accounts'       => $accounts,
            'totalCount'     => count($all),
            'filters'        => $filters,
            'groupList'      => $groups,
            'types'          => AccountModel::TYPES,
            'otherCompanies' => $otherCompanies,
        ]);
    }

    /**
     * Copy the whole chart of accounts from another company into the active one
     * (only when the active company has no accounts yet).
     */
    public function copyFrom()
    {
        if (! $this->guard()) {
            return redirect()->to('accounts')->with('error', 'Not allowed.');
        }
        $model = model(AccountModel::class);
        if ($model->countAllResults() > 0) {
            return redirect()->to('accounts')->with('error', 'This company already has accounts.');
        }
        $sourceId = (int) $this->request->getPost('source_company_id');
        if (! model(CompanyModel::class)->find($sourceId)) {
            return redirect()->to('accounts')->with('error', 'Pick a source company.');
        }

        $db     = db_connect();
        $source = $db->table('accounts')->where('company_id', $sourceId)->orderBy('id', 'ASC')->get()->getResultArray();
        if (! $source) {
            return redirect()->to('accounts')->with('error', 'That company has no accounts to copy.');
        }

        $target = active_company_id();
        $now    = date('Y-m-d H:i:s');
        $db->transStart();

        // pass 1: insert without parent links, remember old id -> new id
        $map = [];
        foreach ($source as $a) {
            $db->table('accounts')->insert([
                'company_id'     => $target,
                'code'           => $a['code'],
                'name'           => $a['name'],
                'type'           => $a['type'],
                'normal_balance' => $a['normal_balance'],
                'parent_id'      => null,
                'is_group'       => $a['is_group'],
                'is_cash'        => $a['is_cash'],
                'subledger'      => $a['subledger'],
                'currency_id'    => $a['currency_id'],
                'is_active'      => $a['is_active'],
                'description'    => $a['description'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $map[(int) $a['id']] = (int) $db->insertID();
        }
        // pass 2: wire parents
        foreach ($source as $a) {
            if ($a['parent_id'] && isset($map[(int) $a['parent_id']])) {
                $db->table('accounts')->where('id', $map[(int) $a['id']])
                    ->update(['parent_id' => $map[(int) $a['parent_id']]]);
            }
        }

        $db->transComplete();

        return redirect()->to('accounts')->with('message', count($source) . ' accounts copied.');
    }

    public function new()
    {
        if (! $this->guard()) {
            return redirect()->to('accounts')->with('error', 'Not allowed.');
        }

        return view('accounts/form', [
            'title'      => 'New Account',
            'account'    => null,
            'types'      => AccountModel::TYPES,
            'parents'    => model(AccountModel::class)->where('is_group', 1)->orderBy('code')->findAll(),
            'currencies' => model(CurrencyModel::class)->active(),
        ]);
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to('accounts')->with('error', 'Not allowed.');
        }
        $model = model(AccountModel::class);
        $data  = $this->payload();

        if ($model->codeTaken($data['code'])) {
            return redirect()->back()->withInput()->with('errors', ['Account code ' . $data['code'] . ' is already used in this company.']);
        }
        if (! $model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('accounts')->with('message', 'Account ' . $data['code'] . ' created.');
    }

    public function edit(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('accounts')->with('error', 'Not allowed.');
        }
        $model   = model(AccountModel::class);
        $account = $model->find($id);
        if (! $account) {
            return redirect()->to('accounts')->with('error', 'Account not found.');
        }

        return view('accounts/form', [
            'title'      => 'Edit ' . $account['code'],
            'account'    => $account,
            'types'      => AccountModel::TYPES,
            'parents'    => $model->where('is_group', 1)->where('id !=', $id)->orderBy('code')->findAll(),
            'currencies' => model(CurrencyModel::class)->active(),
        ]);
    }

    public function update(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('accounts')->with('error', 'Not allowed.');
        }
        $model = model(AccountModel::class);
        if (! $model->find($id)) {
            return redirect()->to('accounts')->with('error', 'Account not found.');
        }
        $data = $this->payload();

        if ($model->codeTaken($data['code'], $id)) {
            return redirect()->back()->withInput()->with('errors', ['Account code ' . $data['code'] . ' is already used in this company.']);
        }
        if (! $model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('accounts')->with('message', 'Account ' . $data['code'] . ' updated.');
    }

    public function delete(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('accounts')->with('error', 'Not allowed.');
        }
        $model = model(AccountModel::class);
        $acc   = $model->find($id);
        if (! $acc) {
            return redirect()->to('accounts')->with('error', 'Account not found.');
        }

        $db = db_connect();
        if ($db->table('journal_lines')->where('account_id', $id)->countAllResults() > 0) {
            return redirect()->to('accounts/' . $id . '/edit')->with('error', 'This account has journal entries — deactivate it instead of deleting.');
        }
        if ($model->where('parent_id', $id)->countAllResults() > 0) {
            return redirect()->to('accounts/' . $id . '/edit')->with('error', 'This is a header for other accounts — reassign or remove them first.');
        }
        $role = \App\Libraries\Accounting\ControlAccounts::roleUsing($id);
        if ($role !== null) {
            return redirect()->to('accounts/' . $id . '/edit')->with('error', "This account is set as the “{$role}” control account — change that mapping in Setup → Control Accounts first.");
        }

        $model->delete($id);

        return redirect()->to('accounts')->with('message', 'Account ' . $acc['code'] . ' deleted.');
    }

    public function toggle(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('accounts')->with('error', 'Not allowed.');
        }
        $model = model(AccountModel::class);
        $acc   = $model->find($id);
        if ($acc) {
            $model->update($id, ['is_active' => $acc['is_active'] ? 0 : 1]);
        }

        return redirect()->to('accounts')->with('message', 'Account status changed.');
    }

    private function payload(): array
    {
        $type = (string) $this->request->getPost('type');

        return [
            'code'           => trim((string) $this->request->getPost('code')),
            'name'           => trim((string) $this->request->getPost('name')),
            'type'           => $type,
            'normal_balance' => in_array($this->request->getPost('normal_balance'), ['D', 'K'], true)
                ? $this->request->getPost('normal_balance')
                : AccountModel::normalBalanceFor($type),
            'parent_id'      => $this->request->getPost('parent_id') ?: null,
            'is_group'       => $this->request->getPost('is_group') ? 1 : 0,
            'is_cash'        => $this->request->getPost('is_cash') ? 1 : 0,
            'subledger'      => $this->request->getPost('subledger') ?: 'none',
            'cashflow'       => in_array($this->request->getPost('cashflow'), ['operating', 'investing', 'financing'], true)
                ? $this->request->getPost('cashflow') : 'operating',
            'currency_id'    => $this->request->getPost('currency_id') ?: null,
            'is_active'      => $this->request->getPost('is_active') !== null ? 1 : 0,
            'description'    => trim((string) $this->request->getPost('description')) ?: null,
        ];
    }
}
