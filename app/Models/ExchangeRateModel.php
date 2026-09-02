<?php

namespace App\Models;

/**
 * Exchange rates are company-scoped (TenantModel): each rate is "1 unit of
 * currency_id = `rate` units of THIS company's base currency" on rate_date.
 */
class ExchangeRateModel extends TenantModel
{
    protected $table         = 'exchange_rates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = ['currency_id', 'rate_date', 'rate'];

    protected $validationRules = [
        'currency_id' => 'required|is_natural_no_zero',
        'rate_date'   => 'required|valid_date[Y-m-d]',
        'rate'        => 'required|greater_than[0]',
    ];

    /**
     * Most recent rate for a currency on or before the given date, in the
     * active company's base currency. Returns 1.0 for that base currency, or
     * when no rate is on file.
     */
    public function rateFor(int $currencyId, string $date): float
    {
        if (model(CurrencyModel::class)->isBase($currencyId)) {
            return 1.0;
        }

        $row = $this->where('currency_id', $currencyId)
            ->where('rate_date <=', $date)
            ->orderBy('rate_date', 'DESC')
            ->first();

        return $row ? (float) $row['rate'] : 1.0;
    }

    public function history(int $currencyId, int $limit = 50)
    {
        return $this->where('currency_id', $currencyId)
            ->orderBy('rate_date', 'DESC')
            ->findAll($limit);
    }
}
