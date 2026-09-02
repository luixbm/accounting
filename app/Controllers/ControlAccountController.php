<?php

namespace App\Controllers;

use App\Libraries\Accounting\ControlAccounts;
use App\Models\AccountModel;
use App\Models\CurrencyModel;

/**
 * The per-currency control-account grid for the active company: which account
 * holds trade A/R, trade A/P, customer down payments, supplier deposits and
 * realized FX gain/loss for each currency the company transacts in.
 */
class ControlAccountController extends BaseController
{
    private function guard(): bool
    {
        return user_can('settings.manage');
    }

    public function index()
    {
        if (! $this->guard()) {
            return redirect()->to('/')->with('error', 'Not allowed.');
        }

        return view('control_accounts/index', [
            'title'      => 'Control Accounts',
            'roles'      => ControlAccounts::ROLES,
            'currencies' => model(CurrencyModel::class)->where('is_active', 1)->orderBy('code')->findAll(),
            'grid'       => ControlAccounts::grid(),
            'accounts'   => model(AccountModel::class)->where('is_group', 0)->orderBy('code')->findAll(),
            'gaps'       => ControlAccounts::gaps(),
        ]);
    }

    public function save()
    {
        if (! $this->guard()) {
            return redirect()->to('/')->with('error', 'Not allowed.');
        }

        $grid = [];
        foreach (array_keys(ControlAccounts::ROLES) as $role) {
            foreach ((array) ($this->request->getPost($role) ?? []) as $ccy => $code) {
                $grid[$role][(string) $ccy] = trim((string) $code);
            }
        }
        ControlAccounts::save($grid);

        return redirect()->to('control-accounts')->with('message', 'Control accounts saved for ' . company_name() . '.');
    }
}
