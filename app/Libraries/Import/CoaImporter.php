<?php

namespace App\Libraries\Import;

use App\Models\AccountModel;
use App\Models\CurrencyModel;
use Config\Database;

/**
 * Loads a chart of accounts from a spreadsheet.
 *
 * Pipeline: upload -> map columns + map the file's account-type codes onto the
 * app's nine types -> preview -> commit (upsert by code, then a second pass to
 * wire parent_id). Re-running the same file updates existing accounts in place.
 */
class CoaImporter
{
    /** Logical field => [label, required?]. */
    public const FIELDS = [
        'code'        => ['Account number', true],
        'name'        => ['Account name', true],
        'type'        => ['Account type', true],
        'parent_code' => ['Parent account number', false],
        'currency'    => ['Currency code', false],
        'notes'       => ['Notes / description', false],
    ];

    /**
     * Common accounting-package type codes -> [app type, is_cash]. The map step
     * lets the user override any row, so this only needs to be a good guess.
     */
    public const TYPE_GUESS = [
        'BANK' => ['asset', 1], 'CASH' => ['asset', 1], 'KAS' => ['asset', 1],
        'AREC' => ['asset', 0], 'AR' => ['asset', 0], 'RECV' => ['asset', 0],
        'OCAS' => ['asset', 0], 'OCA' => ['asset', 0], 'CASS' => ['asset', 0],
        'FASS' => ['asset', 0], 'FA' => ['asset', 0], 'FIXA' => ['asset', 0],
        'OASS' => ['asset', 0], 'OAS' => ['asset', 0],
        'DEPR' => ['contra_asset', 0], 'ACCU' => ['contra_asset', 0], 'ACUM' => ['contra_asset', 0],
        'APAY' => ['liability', 0], 'AP' => ['liability', 0], 'PAYB' => ['liability', 0],
        'OCLY' => ['liability', 0], 'OCL' => ['liability', 0], 'CLIA' => ['liability', 0], 'LTLY' => ['liability', 0],
        'EQTY' => ['equity', 0], 'EQ' => ['equity', 0], 'EQUI' => ['equity', 0],
        'REVE' => ['revenue', 0], 'REV' => ['revenue', 0], 'INCM' => ['revenue', 0], 'SALE' => ['revenue', 0],
        'COGS' => ['cogs', 0], 'COS' => ['cogs', 0], 'HPP' => ['cogs', 0],
        'EXPS' => ['expense', 0], 'EXP' => ['expense', 0], 'BEBAN' => ['expense', 0],
        'OEXP' => ['other_expense', 0], 'OEX' => ['other_expense', 0],
        'OINC' => ['other_income', 0], 'OIN' => ['other_income', 0],
    ];

    private AccountModel $accounts;
    private CurrencyModel $currencies;

    public function __construct()
    {
        $this->accounts   = model(AccountModel::class);
        $this->currencies = model(CurrencyModel::class);
    }

    // ---------------------------------------------------------------- grid helpers

    /** @param list<list<mixed>> $grid @return array<int,string> col index => header */
    public function headers(array $grid, int $headerRow): array
    {
        $row = $grid[$headerRow - 1] ?? [];
        $out = [];
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

    /** Distinct non-empty values in the mapped "type" column. @return list<string> */
    public function distinctTypes(array $grid, int $headerRow, array $map): array
    {
        $col = $map['type'] ?? null;
        if ($col === null) {
            return [];
        }
        $seen = [];
        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $v = trim((string) ($r['cells'][$col] ?? ''));
            if ($v !== '') {
                $seen[mb_strtoupper($v)] = $v;
            }
        }
        ksort($seen);

        return array_values($seen);
    }

    /** A default type-code -> [app_type, is_cash] map for the given raw values. */
    public function guessTypeMap(array $rawTypes): array
    {
        $out = [];
        foreach ($rawTypes as $raw) {
            $key       = mb_strtoupper(trim($raw));
            $out[$raw] = self::TYPE_GUESS[$key] ?? ['asset', 0];
        }

        return $out;
    }

    // ---------------------------------------------------------------- parse

    /**
     * @param list<list<mixed>>   $grid
     * @param array<string,int>   $map
     * @param array<string,mixed> $opt  headerRow, typeMap (raw => [type, isCash])
     *
     * @return array{rows: list<array<string,mixed>>, summary: array<string,int>, errors: list<string>}
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $typeMap   = (array) ($opt['typeMap'] ?? []);

        $curByCode = [];
        foreach ($this->currencies->findAll() as $c) {
            $curByCode[mb_strtoupper($c['code'])] = (int) $c['id'];
        }
        $existing = [];
        foreach ($this->accounts->findAll() as $a) {
            $existing[(string) $a['code']] = $a;
        }

        $get = static fn (array $cells, ?int $col) => $col === null ? '' : trim((string) ($cells[$col] ?? ''));

        // pass 1: collect raw rows
        $raw = [];
        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $code = $get($r['cells'], $map['code'] ?? null);
            $name = $get($r['cells'], $map['name'] ?? null);
            if ($code === '' && $name === '') {
                continue;
            }
            $raw[] = [
                'n'           => $r['n'],
                'code'        => $code,
                'name'        => $name,
                'rawType'     => $get($r['cells'], $map['type'] ?? null),
                'parent_code' => $get($r['cells'], $map['parent_code'] ?? null),
                'currency'    => mb_strtoupper($get($r['cells'], $map['currency'] ?? null)),
                'notes'       => $get($r['cells'], $map['notes'] ?? null),
            ];
        }

        // which codes are used as a parent -> group accounts
        $isParent = [];
        foreach ($raw as $x) {
            if ($x['parent_code'] !== '') {
                $isParent[$x['parent_code']] = true;
            }
        }
        $codesInFile = array_column($raw, 'code');
        $codeCount   = array_count_values(array_filter($codesInFile, static fn ($c) => $c !== ''));

        $rows    = [];
        $summary = ['total' => 0, 'ok' => 0, 'error' => 0, 'new' => 0, 'update' => 0, 'groups' => 0];

        foreach ($raw as $x) {
            $summary['total']++;
            $errs = [];

            if ($x['code'] === '') {
                $errs[] = 'Missing account number.';
            } elseif (($codeCount[$x['code']] ?? 0) > 1) {
                $errs[] = "Account number '{$x['code']}' appears more than once in the file.";
            }
            if ($x['name'] === '') {
                $errs[] = 'Missing account name.';
            }

            $mapped = $typeMap[$x['rawType']] ?? ($typeMap[mb_strtoupper($x['rawType'])] ?? null);
            if ($x['rawType'] === '') {
                $errs[] = 'Missing account type.';
            } elseif ($mapped === null || ! isset(AccountModel::TYPES[$mapped[0]])) {
                $errs[] = "Account type '{$x['rawType']}' is not mapped to an app type.";
            }
            $appType = $mapped[0] ?? 'asset';
            $isCash  = (int) (bool) ($mapped[1] ?? 0);

            $curId = null;
            if ($x['currency'] !== '') {
                $curId = $curByCode[$x['currency']] ?? null;
                if ($curId === null) {
                    $errs[] = "Unknown currency '{$x['currency']}'.";
                }
            }

            if ($x['parent_code'] !== '' && ! in_array($x['parent_code'], $codesInFile, true) && ! isset($existing[$x['parent_code']])) {
                $errs[] = "Parent '{$x['parent_code']}' is not in the file or the existing chart.";
            }

            $isGroup = isset($isParent[$x['code']]) ? 1 : 0;
            $exists  = isset($existing[$x['code']]);

            $row = [
                'n'             => $x['n'],
                'code'          => $x['code'],
                'name'          => $x['name'],
                'raw_type'      => $x['rawType'],
                'type'          => $appType,
                'is_cash'       => $isCash,
                'is_group'      => $isGroup,
                'normal_balance' => AccountModel::normalBalanceFor($appType),
                'currency_id'   => $curId,
                'currency'      => $x['currency'],
                'parent_code'   => $x['parent_code'],
                'description'   => $x['notes'] !== '' ? $x['notes'] : null,
                'cashflow'      => $this->cashflowFor($appType),
                'action'        => $exists ? 'update' : 'new',
                'errors'        => $errs,
            ];

            if ($errs) {
                $summary['error']++;
            } else {
                $summary['ok']++;
                $summary[$exists ? 'update' : 'new']++;
                if ($isGroup) {
                    $summary['groups']++;
                }
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'summary' => $summary, 'errors' => []];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param array<string,mixed> $parsed result of parse()
     *
     * @return array{inserted:int, updated:int, skipped:int}
     */
    public function commit(array $parsed): array
    {
        $db = Database::connect();
        $db->transStart();

        $companyId = active_company_id();
        $codeToId  = [];
        foreach ($this->accounts->findAll() as $a) {
            $codeToId[(string) $a['code']] = (int) $a['id'];
        }

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;

        // pass 1: upsert accounts (no parent yet)
        foreach ($parsed['rows'] as $r) {
            if ($r['errors']) {
                $skipped++;

                continue;
            }
            $data = [
                'code'           => $r['code'],
                'name'           => $r['name'],
                'type'           => $r['type'],
                'normal_balance' => $r['normal_balance'],
                'is_group'       => $r['is_group'],
                'is_cash'        => $r['is_cash'],
                'currency_id'    => $r['currency_id'],
                'cashflow'       => $r['cashflow'],
                'description'    => $r['description'],
                'is_active'      => 1,
            ];

            if (isset($codeToId[$r['code']])) {
                $this->accounts->skipValidation(true)->update($codeToId[$r['code']], $data);
                $updated++;
            } else {
                $data['company_id'] = $companyId;
                $data['subledger']  = 'none';
                $id                 = (int) $this->accounts->skipValidation(true)->insert($data, true);
                $codeToId[$r['code']] = $id;
                $inserted++;
            }
        }

        // pass 2: wire parent_id
        foreach ($parsed['rows'] as $r) {
            if ($r['errors'] || ! isset($codeToId[$r['code']])) {
                continue;
            }
            $parentId = $r['parent_code'] !== '' ? ($codeToId[$r['parent_code']] ?? null) : null;
            $this->accounts->skipValidation(true)->update($codeToId[$r['code']], ['parent_id' => $parentId]);
        }

        $db->transComplete();

        return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
    }

    // ---------------------------------------------------------------- misc

    private function cashflowFor(string $type): string
    {
        if ($type === 'equity') {
            return 'financing';
        }
        if ($type === 'contra_asset') {
            return 'investing';
        }

        return 'operating';
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
