<?php

namespace App\Libraries\Accounting;

use App\Models\AccountModel;
use App\Models\CurrencyModel;

/**
 * Resolves the per-company, per-currency control account for a given role.
 *
 * A "USD sales invoice" books its receivable to whichever account the company
 * has mapped for (role: ar, currency: USD). Configuration lives in the settings
 * table under `Accounting.ctlAcct_{role}_{CCY}` (an account CODE), managed from
 * Setup -> Control Accounts.
 */
class ControlAccounts
{
    /** role => human label. */
    public const ROLES = [
        'ar'           => 'Trade A/R',
        'ap'           => 'Trade A/P',
        'cust_deposit' => 'Customer down payment',
        'supp_deposit' => 'Deposit paid to supplier',
        'fx'           => 'Realized FX gain / loss',
    ];

    /** roles whose chosen accounts are flagged as a party subledger. */
    public const SUBLEDGER = ['ar' => 'customer', 'ap' => 'supplier'];

    /**
     * Settings key. NB: the settings library only keeps `class.property`, so the
     * property itself must be dot-free — role and currency are joined with `_`.
     */
    private static function key(string $role, string $ccyCode): string
    {
        return 'ctlAcct_' . $role . '_' . strtoupper($ccyCode);
    }

    private const SUBLEDGER_MAP_KEY = 'ctlAcct_subledgerMap';

    /** Configured account CODE for a role + currency (no fallback). */
    public static function rawCode(string $role, string $ccyCode): ?string
    {
        $v = acc_setting(self::key($role, $ccyCode));

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    /**
     * Account CODE for a role in the given currency. Falls back to the mapping
     * for the company's base currency when the exact one is unset.
     */
    public static function code(string $role, ?int $currencyId = null): ?string
    {
        $currencies = model(CurrencyModel::class);
        $ccy        = $currencyId ? $currencies->find($currencyId) : $currencies->base();
        $ccyCode    = $ccy['code'] ?? base_code();

        return self::rawCode($role, $ccyCode)
            ?? self::rawCode($role, base_code());
    }

    /** Account id for a role in the given currency, or null if unmapped / unknown. */
    public static function id(string $role, ?int $currencyId = null): ?int
    {
        $code = self::code($role, $currencyId);
        if ($code === null) {
            return null;
        }
        $a = model(AccountModel::class)->byCode($code);

        return $a ? (int) $a['id'] : null;
    }

    /** Every distinct account id configured for a role, across all currencies. @return list<int> */
    public static function allIds(string $role): array
    {
        $out = [];
        foreach (model(CurrencyModel::class)->where('is_active', 1)->findAll() as $c) {
            $code = self::rawCode($role, $c['code']);
            if ($code === null) {
                continue;
            }
            $a = model(AccountModel::class)->byCode($code);
            if ($a) {
                $out[(int) $a['id']] = (int) $a['id'];
            }
        }

        return array_values($out);
    }

    /**
     * If the account is mapped as any control account, return that role's label
     * (so callers can block deleting it); otherwise null.
     */
    public static function roleUsing(int $accountId): ?string
    {
        foreach (array_keys(self::ROLES) as $role) {
            if (in_array($accountId, self::allIds($role), true)) {
                return self::ROLES[$role];
            }
        }

        return null;
    }

    /**
     * The full grid for the settings screen: [role][CCY] => code|''.
     *
     * @return array<string,array<string,string>>
     */
    public static function grid(): array
    {
        $ccys = model(CurrencyModel::class)->where('is_active', 1)->orderBy('code')->findAll();
        $out  = [];
        foreach (array_keys(self::ROLES) as $role) {
            foreach ($ccys as $c) {
                $out[$role][$c['code']] = self::rawCode($role, $c['code']) ?? '';
            }
        }

        return $out;
    }

    /**
     * Save the grid (role => [CCY => code]) and reconcile subledger flags on the
     * chosen A/R and A/P accounts.
     *
     * @param array<string,array<string,string>> $grid
     */
    public static function save(array $grid): void
    {
        $accounts = model(AccountModel::class);
        $newFlags = []; // code => 'customer'|'supplier'

        foreach (self::ROLES as $role => $_) {
            foreach ((array) ($grid[$role] ?? []) as $ccyCode => $code) {
                $code = trim((string) $code);
                acc_setting_set(self::key($role, (string) $ccyCode), $code !== '' ? $code : null);
                if ($code !== '' && isset(self::SUBLEDGER[$role])) {
                    $newFlags[$code] = self::SUBLEDGER[$role];
                }
            }
        }

        // reconcile: clear the flag on accounts we previously owned but no longer use
        $prevRaw = json_decode((string) acc_setting(self::SUBLEDGER_MAP_KEY), true);
        $prev    = is_array($prevRaw) ? $prevRaw : [];
        foreach (array_keys($prev) as $code) {
            if (! isset($newFlags[$code]) && ($a = $accounts->byCode((string) $code))) {
                $accounts->skipValidation(true)->update($a['id'], ['subledger' => 'none']);
            }
        }
        foreach ($newFlags as $code => $party) {
            if ($a = $accounts->byCode((string) $code)) {
                $accounts->skipValidation(true)->update($a['id'], ['subledger' => $party]);
            }
        }
        acc_setting_set(self::SUBLEDGER_MAP_KEY, json_encode($newFlags));
    }

    /**
     * Currency codes this company actually deals in: its base plus any currency
     * named on one of its accounts.
     *
     * @return list<string>
     */
    public static function usedCurrencies(): array
    {
        $codes = [base_code()];
        $rows  = model(AccountModel::class)
            ->select('currencies.code')
            ->join('currencies', 'currencies.id = accounts.currency_id')
            ->where('accounts.currency_id IS NOT NULL')
            ->groupBy('currencies.code')
            ->findAll();
        foreach ($rows as $r) {
            $codes[] = $r['code'];
        }

        return array_values(array_unique($codes));
    }

    /**
     * Trade A/R and A/P mappings the company needs (for a currency it uses) but
     * hasn't set yet.
     *
     * @return list<array{role:string,ccy:string}>
     */
    public static function gaps(): array
    {
        $out = [];
        foreach (['ar', 'ap'] as $role) {
            foreach (self::usedCurrencies() as $ccy) {
                if (self::rawCode($role, $ccy) === null) {
                    $out[] = ['role' => $role, 'ccy' => $ccy];
                }
            }
        }

        return $out;
    }
}
