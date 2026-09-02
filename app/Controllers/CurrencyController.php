<?php

namespace App\Controllers;

use App\Models\CurrencyModel;
use App\Models\ExchangeRateModel;

class CurrencyController extends BaseController
{
    private function guard(): bool
    {
        return user_can('masterdata.manage');
    }

    public function index()
    {
        $baseCode   = base_code();
        $currencies = model(CurrencyModel::class)->orderBy('code')->findAll();
        $rates      = model(ExchangeRateModel::class);

        $recent = [];
        foreach ($currencies as $c) {
            if ($c['code'] === $baseCode) {
                continue;
            }
            $recent[$c['id']] = $rates->history((int) $c['id'], 10);
        }

        return view('currencies/index', [
            'title'      => 'Currencies & Exchange Rates',
            'currencies' => $currencies,
            'recent'     => $recent,
            'baseCode'   => $baseCode,
            'baseSymbol' => base_symbol(),
        ]);
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to('currencies')->with('error', 'Not allowed.');
        }
        $model = model(CurrencyModel::class);
        $data  = [
            'code'           => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'           => trim((string) $this->request->getPost('name')),
            'symbol'         => trim((string) $this->request->getPost('symbol')) ?: null,
            'decimal_places' => (int) ($this->request->getPost('decimal_places') ?? 2),
            'is_base'        => 0,
            'is_active'      => 1,
        ];
        if (! $model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('currencies')->with('message', 'Currency ' . $data['code'] . ' added.');
    }

    public function update(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('currencies')->with('error', 'Not allowed.');
        }
        $model = model(CurrencyModel::class);
        if (! $model->find($id)) {
            return redirect()->to('currencies')->with('error', 'Not found.');
        }
        $data = [
            'name'           => trim((string) $this->request->getPost('name')),
            'symbol'         => trim((string) $this->request->getPost('symbol')) ?: null,
            'decimal_places' => (int) ($this->request->getPost('decimal_places') ?? 2),
            'is_active'      => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];
        if (! $model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('currencies')->with('message', 'Currency updated.');
    }

    public function addRate(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('currencies')->with('error', 'Not allowed.');
        }
        $currency = model(CurrencyModel::class)->find($id);
        if (! $currency || $currency['code'] === base_code()) {
            return redirect()->to('currencies')->with('error', 'Cannot set a rate for this company\'s base currency.');
        }
        $model = model(ExchangeRateModel::class);
        $data  = [
            'currency_id' => $id,
            'rate_date'   => $this->request->getPost('rate_date') ?: date('Y-m-d'),
            'rate'        => (float) $this->request->getPost('rate'),
        ];

        $existing = $model->where('currency_id', $id)->where('rate_date', $data['rate_date'])->first();
        if ($existing) {
            $model->update($existing['id'], ['rate' => $data['rate']]);
        } elseif (! $model->insert($data)) {
            return redirect()->back()->with('errors', $model->errors());
        }

        return redirect()->to('currencies')->with('message', '1 ' . $currency['code'] . ' = ' . base_symbol() . ' ' . money($data['rate'], 4) . ' as of ' . $data['rate_date'] . '.');
    }
}
