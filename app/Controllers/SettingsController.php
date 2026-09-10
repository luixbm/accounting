<?php

namespace App\Controllers;

use App\Models\AccountModel;
use Config\Accounting;

class SettingsController extends BaseController
{
    /** Per-company control-account mappings + tax setup. */
    private const COMPANY_KEYS = [
        // trade A/R, A/P and realized FX are now per-currency, under Control Accounts
        'retainedEarningsCode', 'roundingCode',
        'ppnRate', 'ppnInputCode', 'ppnOutputCode',
        'pph23Rate', 'pph23PayableCode', 'pph23PrepaidCode',
    ];

    public function index()
    {
        $cfg     = new Accounting();
        $current = [];
        foreach (self::COMPANY_KEYS as $k) {
            $current[$k] = acc_setting($k) ?? ($cfg->{$k} ?? '');
        }
        $current['theme']  = setting()->get('Accounting.theme') ?: 'light';
        $current['locale'] = setting()->get('Accounting.locale') ?: 'id';
        $current['design']     = active_design();
        $current['chartStyle'] = chart_style();

        return view('settings/index', [
            'title'    => 'Settings',
            'current'  => $current,
            'accounts' => model(AccountModel::class)->orderBy('code')->findAll(),
            'company'  => active_company(),
        ]);
    }

    public function save()
    {
        setting()->set('Accounting.theme', trim((string) $this->request->getPost('theme')));
        $loc = $this->request->getPost('locale');
        setting()->set('Accounting.locale', in_array($loc, ['id', 'en'], true) ? $loc : 'id');
        $dsn = (string) $this->request->getPost('design');
        setting()->set('Accounting.design', array_key_exists($dsn, \Config\Design::DESIGNS) ? $dsn : \Config\Design::DEFAULT);
        $cst = (string) $this->request->getPost('chartStyle');
        setting()->set('Accounting.chartStyle', array_key_exists($cst, \Config\Charts::STYLES) ? $cst : \Config\Charts::DEFAULT);

        foreach (self::COMPANY_KEYS as $k) {
            acc_setting_set($k, trim((string) $this->request->getPost($k)));
        }

        return redirect()->to('settings')->with('message', 'Settings saved for ' . company_name() . '.');
    }
}
