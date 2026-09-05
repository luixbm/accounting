<?php

namespace App\Libraries\Budget;

use App\Models\BudgetVersionModel;
use Config\Database;

/** Read-side helpers over budget_versions / budget_lines for the active company. */
class Budget
{
    /** @return list<array<string,mixed>> versions for the year, default first */
    public static function versionsForYear(int $year): array
    {
        return model(BudgetVersionModel::class)
            ->where('year', $year)
            ->orderBy('is_default', 'DESC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Which version to report against: the requested one (if it belongs to the
     * active company), else the year's default, else the newest for that year,
     * else null.
     */
    public static function resolveVersionId(?int $requested, int $year): ?int
    {
        $m = model(BudgetVersionModel::class);
        if ($requested) {
            $row = $m->find($requested);   // TenantModel-scoped to the active company
            if ($row) {
                return (int) $row['id'];
            }
        }
        if ($def = $m->defaultFor($year)) {
            return (int) $def['id'];
        }
        $newest = $m->where('year', $year)->orderBy('id', 'DESC')->first();

        return $newest ? (int) $newest['id'] : null;
    }

    /**
     * @return array<int,array<int,float>> [accountId => [1..12 => amount]]
     */
    public static function byAccount(int $versionId): array
    {
        $out = [];
        $rows = Database::connect()->table('budget_lines')
            ->select('account_id, period_month, amount')
            ->where('version_id', $versionId)
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $out[(int) $r['account_id']][(int) $r['period_month']] = (float) $r['amount'];
        }

        return $out;
    }

    /**
     * Budget summed per account code for the months of the version's year that
     * fall within [from, to]. Partial months contribute their whole figure.
     *
     * @return array<string,float> [accountCode => amount]
     */
    public static function byCodeForRange(int $versionId, string $from, string $to): array
    {
        $ver = model(BudgetVersionModel::class)->find($versionId);
        if (! $ver) {
            return [];
        }
        $year  = (int) $ver['year'];
        $mFrom = substr($from, 0, 7);
        $mTo   = substr($to, 0, 7);

        $out = [];
        $rows = Database::connect()->table('budget_lines bl')
            ->select('a.code AS code, bl.period_month AS m, bl.amount AS amt')
            ->join('accounts a', 'a.id = bl.account_id')
            ->where('bl.version_id', $versionId)
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $ym = sprintf('%04d-%02d', $year, (int) $r['m']);
            if ($ym < $mFrom || $ym > $mTo) {
                continue;
            }
            $out[$r['code']] = ($out[$r['code']] ?? 0.0) + (float) $r['amt'];
        }

        return $out;
    }

    /** @return array{lines:int,amount:float} */
    public static function totals(int $versionId): array
    {
        $row = Database::connect()->table('budget_lines')
            ->select('COUNT(*) AS n, COALESCE(SUM(amount),0) AS amt')
            ->where('version_id', $versionId)
            ->get()->getRowArray();

        return ['lines' => (int) ($row['n'] ?? 0), 'amount' => (float) ($row['amt'] ?? 0)];
    }
}
