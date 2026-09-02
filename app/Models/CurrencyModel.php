<?php

namespace App\Models;

use CodeIgniter\Model;

class CurrencyModel extends Model
{
    protected $table            = 'currencies';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['code', 'name', 'symbol', 'decimal_places', 'is_base', 'is_active'];

    protected $validationRules = [
        'id'   => 'permit_empty|is_natural_no_zero',
        'code' => 'required|max_length[3]|is_unique[currencies.code,id,{id}]',
        'name' => 'required|max_length[60]',
    ];

    /**
     * The active company's base (functional) currency. Per-company: resolved
     * from companies.base_currency via the base_currency() helper, with the
     * is_base flag as a fallback default.
     */
    public function base(): ?array
    {
        return function_exists('base_currency')
            ? base_currency()
            : $this->where('is_base', 1)->first();
    }

    public function baseId(): int
    {
        $b = $this->base();

        return $b ? (int) $b['id'] : 0;
    }

    /** Is this currency the active company's base currency? */
    public function isBase(int $currencyId): bool
    {
        return $currencyId > 0 && $currencyId === $this->baseId();
    }

    public function active()
    {
        return $this->where('is_active', 1)->orderBy('is_base', 'DESC')->orderBy('code', 'ASC')->findAll();
    }
}
