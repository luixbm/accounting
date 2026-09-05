<?php

namespace App\Libraries\Import;

use App\Models\AccountModel;
use Config\Database;

/**
 * Loads a per-account monthly budget from a wide-format spreadsheet (one row
 * per account, one column per month) into a budget version. Re-importing the
 * same accounts replaces their figures for that version.
 */
class BudgetImporter
{
    /** Logical field => [label, required?]. */
    public const FIELDS = [
        'account' => ['Account (code or name)', true],
        'm1'      => ['January amount', false],
        'm2'      => ['February amount', false],
        'm3'      => ['March amount', false],
        'm4'      => ['April amount', false],
        'm5'      => ['May amount', false],
        'm6'      => ['June amount', false],
        'm7'      => ['July amount', false],
        'm8'      => ['August amount', false],
        'm9'      => ['September amount', false],
        'm10'     => ['October amount', false],
        'm11'     => ['November amount', false],
        'm12'     => ['December amount', false],
    ];

    private AccountModel $accounts;

    public function __construct()
    {
        $this->accounts = model(AccountModel::class);
    }

    // ---------------------------------------------------------------- grid helpers

    /** @param list<list<mixed>> $grid @return array<int,string> */
    public function headers(array $grid, int $headerRow): array
    {
        $row   = $grid[$headerRow - 1] ?? [];
        $out   = [];
        foreach ($row as $i => $val) {
            $txt     = trim((string) $val);
            $out[$i] = $txt !== '' ? $txt : ('Column ' . $this->colLetter($i));
        }
        $width = max(array_map('count', array_slice($grid, 0, 50) ?: [[]]));
        for ($i = count($out); $i < $width; $i++) {
            $out[$i] = 'Column ' . $this->colLetter($i);
        }

        return $out;
    }

    /** @param list<list<mixed>> $grid @return list<array{n:int,cells:list<mixed>}> */
    public function dataRows(array $grid, int $headerRow): array
    {
        $out = [];
        foreach ($grid as $idx => $cells) {
            if ($idx < $headerRow) {
                continue;
            }
            $out[] = ['n' => $idx + 1, 'cells' => $cells];
        }

        return $out;
    }

    /** Best-guess column map from the header labels. @return array<string,int> */
    public function guessMap(array $headers): array
    {
        $map    = [];
        $months = [
            1 => ['jan'], 2 => ['feb'], 3 => ['mar'], 4 => ['apr'], 5 => ['may'], 6 => ['jun'],
            7 => ['jul'], 8 => ['aug'], 9 => ['sep'], 10 => ['oct'], 11 => ['nov'], 12 => ['dec'],
        ];
        foreach ($headers as $i => $h) {
            $l = mb_strtolower(trim((string) $h));
            if ($l === '') {
                continue;
            }
            if (! isset($map['account']) && (str_contains($l, 'account') || str_contains($l, 'acct') || str_contains($l, 'akun') || $l === 'code' || $l === 'name')) {
                $map['account'] = $i;

                continue;
            }
            foreach ($months as $m => $keys) {
                if (isset($map['m' . $m])) {
                    continue;
                }
                foreach ($keys as $k) {
                    if (str_starts_with($l, $k)) {
                        $map['m' . $m] = $i;

                        continue 3;
                    }
                }
            }
        }

        return $map;
    }

    // ---------------------------------------------------------------- parse

    /**
     * @param list<list<mixed>>   $grid
     * @param array<string,int>   $map
     * @param array<string,mixed> $opt  headerRow
     *
     * @return array{rows:list<array<string,mixed>>, summary:array<string,int>, errors:list<string>}
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);

        $byCode = [];
        $byName = [];
        $meta   = [];   // id => [code, name, is_group, type]
        foreach ($this->accounts->findAll() as $a) {
            $id            = (int) $a['id'];
            $byCode[mb_strtolower(trim((string) $a['code']))] = $id;
            $byName[$this->norm($a['name'])]                  = $id;
            $meta[$id]     = $a;
        }

        $get     = static fn (array $cells, ?int $col) => $col === null ? '' : trim((string) ($cells[$col] ?? ''));
        $accCol  = $map['account'] ?? null;

        $rows    = [];
        $summary = ['total' => 0, 'ok' => 0, 'error' => 0, 'accounts' => 0, 'cells' => 0];
        $seenAcc = [];

        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $rawAcc = $get($r['cells'], $accCol);
            if ($rawAcc === '') {
                continue;
            }
            $summary['total']++;
            $errs = [];

            $id = $byCode[mb_strtolower($rawAcc)] ?? $byName[$this->norm($rawAcc)] ?? null;
            if ($id === null) {
                // tolerate a "code · name" or "code name" cell
                $firstTok = trim(preg_split('/[\s·|,-]+/', $rawAcc)[0] ?? '');
                $id = $firstTok !== '' ? ($byCode[mb_strtolower($firstTok)] ?? null) : null;
            }

            $acc = $id !== null ? ($meta[$id] ?? null) : null;
            if ($acc === null) {
                $errs[] = "Account '{$rawAcc}' not recognised.";
            } elseif ((int) $acc['is_group'] === 1) {
                $errs[] = "'{$rawAcc}' is a header account — budget only leaf accounts.";
            } elseif (! in_array($acc['type'], AccountModel::PNL_TYPES, true)) {
                $errs[] = "'{$rawAcc}' is not a P&L account.";
            }

            $months = [];
            $rowTot = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $col = $map['m' . $m] ?? null;
                if ($col === null) {
                    continue;
                }
                $amt = SpreadsheetReader::toNumber($r['cells'][$col] ?? null);
                $months[$m] = $amt;
                $rowTot += $amt;
            }

            $row = [
                'n'          => $r['n'],
                'raw'        => $rawAcc,
                'account_id' => $acc !== null && ! $errs ? (int) $acc['id'] : null,
                'code'       => $acc['code'] ?? '',
                'name'       => $acc['name'] ?? '',
                'months'     => $months,
                'total'      => $rowTot,
                'errors'     => $errs,
            ];

            if ($errs) {
                $summary['error']++;
            } else {
                $summary['ok']++;
                if (! isset($seenAcc[$row['account_id']])) {
                    $seenAcc[$row['account_id']] = true;
                    $summary['accounts']++;
                }
                foreach ($months as $amt) {
                    if (abs($amt) >= 0.005) {
                        $summary['cells']++;
                    }
                }
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'summary' => $summary, 'errors' => []];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param array<string,mixed> $parsed
     *
     * @return array{accounts:int, cells:int, skipped:int}
     */
    public function commit(int $versionId, array $parsed): array
    {
        $db  = Database::connect();
        $ids = [];
        $cell = [];   // "accountId-month" => amount  (last row wins if a sheet lists an account twice)
        $skipped = 0;
        foreach ($parsed['rows'] as $r) {
            if ($r['errors'] || ! $r['account_id']) {
                $skipped++;

                continue;
            }
            $ids[$r['account_id']] = true;
            foreach ($r['months'] as $m => $amt) {
                if (abs($amt) >= 0.005) {
                    $cell[$r['account_id'] . '-' . (int) $m] = round($amt, 2);
                }
            }
        }
        $ins = [];
        foreach ($cell as $key => $amt) {
            [$aid, $m] = explode('-', $key);
            $ins[] = ['version_id' => $versionId, 'account_id' => (int) $aid, 'period_month' => (int) $m, 'amount' => $amt];
        }

        $db->transStart();
        if ($ids) {
            $db->table('budget_lines')->where('version_id', $versionId)->whereIn('account_id', array_keys($ids))->delete();
        }
        if ($ins) {
            $now = date('Y-m-d H:i:s');
            foreach ($ins as &$x) {
                $x['created_at'] = $now;
                $x['updated_at'] = $now;
            }
            unset($x);
            $db->table('budget_lines')->insertBatch($ins);
        }
        $db->transComplete();

        return ['accounts' => count($ids), 'cells' => count($ins), 'skipped' => $skipped];
    }

    // ---------------------------------------------------------------- misc

    private function norm(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    private function colLetter(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $i--;
            $s = chr(65 + $i % 26) . $s;
            $i = intdiv($i, 26);
        }

        return $s;
    }
}
