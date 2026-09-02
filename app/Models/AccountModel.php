<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountModel extends TenantModel
{
    protected $table         = 'accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'code', 'name', 'type', 'normal_balance', 'parent_id',
        'is_group', 'is_cash', 'subledger', 'cashflow', 'currency_id', 'is_active', 'description',
    ];

    /** Cash-flow statement section a movement against this account belongs to. */
    public const CASHFLOW = [
        'operating' => 'Operating',
        'investing' => 'Investing',
        'financing' => 'Financing',
    ];

    public const TYPES = [
        'asset'         => 'Asset',
        'contra_asset'  => 'Contra Asset',
        'liability'     => 'Liability',
        'equity'        => 'Equity',
        'revenue'       => 'Revenue',
        'cogs'          => 'Cost of Sales',
        'expense'       => 'Expense',
        'other_income'  => 'Other Income',
        'other_expense' => 'Other Expense',
    ];

    /** Types whose balances roll into the Income Statement. */
    public const PNL_TYPES = ['revenue', 'cogs', 'expense', 'other_income', 'other_expense'];

    /** Types whose balances roll into the Balance Sheet. */
    public const BS_TYPES = ['asset', 'contra_asset', 'liability', 'equity'];

    protected $validationRules = [
        'code'           => 'required|max_length[20]',
        'name'           => 'required|max_length[150]',
        'type'           => 'required|in_list[asset,contra_asset,liability,equity,revenue,cogs,expense,other_income,other_expense]',
        'normal_balance' => 'required|in_list[D,K]',
        'subledger'      => 'permit_empty|in_list[none,customer,supplier]',
    ];

    public static function normalBalanceFor(string $type): string
    {
        return in_array($type, ['asset', 'cogs', 'expense', 'other_expense'], true) ? 'D' : 'K';
    }

    public function postable()
    {
        return $this->where('is_group', 0)->where('is_active', 1)
            ->orderBy('code', 'ASC')->findAll();
    }

    public function tree(): array
    {
        $all = $this->orderBy('code', 'ASC')->findAll();
        $byParent = [];
        foreach ($all as $a) {
            $byParent[$a['parent_id'] ?? 0][] = $a;
        }

        return $byParent;
    }

    public function byCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }

    public function cashAccounts()
    {
        return $this->where('is_cash', 1)->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
    }
}
