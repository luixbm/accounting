<?php

namespace App\Libraries\Import;

use App\Libraries\Accounting\JournalPoster;
use App\Models\AccountAliasModel;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\ExchangeRateModel;
use App\Models\FiscalPeriodModel;
use App\Models\ImportBatchModel;
use App\Models\JournalLineModel;
use App\Models\JournalModel;
use App\Models\SupplierModel;

/**
 * Turns a mapped spreadsheet grid into draft journals.
 *
 * Pipeline: upload -> pick sheet -> map columns -> resolve account labels to
 * COA accounts (account_aliases) -> preview -> commit as a draft batch.
 */
class JournalImporter
{
    /** Logical field => [label, required?]. Keys are used in the column map. */
    public const FIELDS = [
        'group'         => ['Journal group / no.', true],
        'date'          => ['Date', true],
        'account_label' => ['Account (name or code)', true],
        'debit'         => ['Debit', true],
        'credit'        => ['Credit', true],
        'description'   => ['Description', false],
        'reference'     => ['Reference / Invoice no.', false],
        'currency'      => ['Currency code', false],
        'rate'          => ['Exchange rate', false],
        'party_name'    => ['Customer / Supplier name', false],
        'memo'          => ['Line memo', false],
        'source'        => ['Source', false],
    ];

    private AccountModel $accounts;
    private CurrencyModel $currencies;
    private ExchangeRateModel $rates;
    private AccountAliasModel $aliases;
    private FiscalPeriodModel $periods;

    public function __construct()
    {
        $this->accounts   = model(AccountModel::class);
        $this->currencies = model(CurrencyModel::class);
        $this->rates      = model(ExchangeRateModel::class);
        $this->aliases    = model(AccountAliasModel::class);
        $this->periods    = model(FiscalPeriodModel::class);
    }

    // ---------------------------------------------------------------- grid helpers

    /** @param list<list<mixed>> $grid @return array<int,string> col index => header text */
    public function headers(array $grid, int $headerRow): array
    {
        $row = $grid[$headerRow - 1] ?? [];
        $out = [];
        foreach ($row as $i => $val) {
            $txt      = trim((string) $val);
            $out[$i]  = $txt !== '' ? $txt : ('Column ' . $this->colLetter($i));
        }
        // pad to widest data row so extra unlabelled columns are still selectable
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

    /** Distinct non-empty account labels in the mapped column. @return list<string> */
    public function distinctAccountLabels(array $grid, int $headerRow, array $map): array
    {
        $col = $map['account_label'] ?? null;
        if ($col === null) {
            return [];
        }
        $seen = [];
        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $label = trim((string) ($r['cells'][$col] ?? ''));
            if ($label === '') {
                continue;
            }
            $seen[AccountAliasModel::normalise($label)] = $label;
        }
        ksort($seen);

        return array_values($seen);
    }

    // ---------------------------------------------------------------- parse

    /**
     * @param list<list<mixed>>   $grid
     * @param array<string,int>   $map        logical field => column index
     * @param array<string,mixed> $opt        headerRow, dateFormat, defaultSource
     *
     * @return array{
     *   journals: list<array<string,mixed>>,
     *   summary: array<string,int>,
     *   newCustomers: list<string>,
     *   newSuppliers: list<string>,
     *   unmapped: list<string>
     * }
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow  = (int) ($opt['headerRow'] ?? 1);
        $dateFmt    = $opt['dateFormat'] ?? 'auto';
        $defSource  = $opt['defaultSource'] ?? 'general';

        $aliasMap   = $this->aliases->map();
        $accById    = [];
        foreach ($this->accounts->findAll() as $a) {
            $accById[(int) $a['id']] = $a;
        }
        $accByCode = [];
        foreach ($accById as $a) {
            $accByCode[AccountAliasModel::normalise($a['code'])] = (int) $a['id'];
        }
        $curByCode = [];
        foreach ($this->currencies->findAll() as $c) {
            $curByCode[strtoupper($c['code'])] = $c;
        }
        $baseId = model(CurrencyModel::class)->baseId();

        $existingCustomers = $this->nameIndex(model(CustomerModel::class)->findAll());
        $existingSuppliers = $this->nameIndex(model(SupplierModel::class)->findAll());

        $groups   = [];
        $order    = [];
        $unmapped = [];

        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $cells = $r['cells'];
            $get   = static fn (string $f) => isset($map[$f]) ? trim((string) ($cells[$map[$f]] ?? '')) : '';

            $debit  = SpreadsheetReader::toNumber($cells[$map['debit']] ?? null);
            $credit = SpreadsheetReader::toNumber($cells[$map['credit']] ?? null);
            $label  = $get('account_label');

            if ($label === '' && $debit === 0.0 && $credit === 0.0) {
                continue; // blank spacer row
            }

            $key = $get('group');
            if ($key === '') {
                $key = '(no group) row ' . $r['n'];
            }
            if (! isset($groups[$key])) {
                $order[]       = $key;
                $groups[$key]  = ['key' => $key, 'rows' => [], 'first' => $r['n']];
            }

            // resolve account
            $norm = AccountAliasModel::normalise($label);
            $accId = $aliasMap[$norm] ?? $accByCode[$norm] ?? null;
            if ($accId === null && $label !== '') {
                $unmapped[$norm] = $label;
            }

            $groups[$key]['rows'][] = [
                'n'          => $r['n'],
                'label'      => $label,
                'account_id' => $accId,
                'debit'      => $debit,
                'credit'     => $credit,
                'party'      => $get('party_name'),
                'memo'       => $get('memo') !== '' ? $get('memo') : null,
                'date'       => $get('date'),
                'desc'       => $get('description'),
                'ref'        => $get('reference'),
                'cur'        => strtoupper($get('currency')),
                'rate'       => $get('rate'),
                'source'     => $get('source'),
            ];
        }

        $existingJournalNos = $this->existingJournalNos();

        $journals     = [];
        $seenNos      = [];
        $newCustomers = [];
        $newSuppliers = [];
        $summary      = ['groups' => 0, 'ok' => 0, 'error' => 0, 'skip' => 0, 'lines' => 0];

        foreach ($order as $key) {
            $g       = $groups[$key];
            $summary['groups']++;
            $errors  = [];
            $warn    = [];
            $first   = $g['rows'][0];

            $dateRaw = '';
            foreach ($g['rows'] as $row) {
                if ($row['date'] !== '') {
                    $dateRaw = $row['date'];
                    break;
                }
            }
            $date = SpreadsheetReader::toDate($dateRaw, $dateFmt);
            if ($date === null) {
                $errors[] = "Could not read a date (got '" . $dateRaw . "').";
                $date     = date('Y-m-d');
            } elseif ($this->periods->isDateLocked($date)) {
                $errors[] = "Period containing {$date} is closed.";
            }

            $curCode = '';
            $rateRaw = '';
            foreach ($g['rows'] as $row) {
                $curCode = $curCode ?: $row['cur'];
                $rateRaw = $rateRaw ?: $row['rate'];
            }
            $currency = $curCode !== '' ? ($curByCode[$curCode] ?? null) : ($curByCode[array_key_first($curByCode)] ?? null);
            if ($curCode !== '' && $currency === null) {
                $errors[] = "Unknown currency code '{$curCode}'.";
                $currency = $this->currencies->base();
            }
            $currency ??= $this->currencies->base();
            $isBase = $this->currencies->isBase((int) $currency['id']);
            $rate   = $isBase ? 1.0
                : ($rateRaw !== '' ? SpreadsheetReader::toNumber($rateRaw)
                    : $this->rates->rateFor((int) $currency['id'], $date));
            if ($rate <= 0) {
                $rate     = 1.0;
                $warn[]   = 'No exchange rate found; used 1.0.';
            }

            $descr = '';
            foreach ($g['rows'] as $row) {
                if ($row['desc'] !== '') {
                    $descr = $row['desc'];
                    break;
                }
            }
            $descr = $descr !== '' ? $descr : ('Imported ' . $key);
            $ref   = '';
            foreach ($g['rows'] as $row) {
                if ($row['ref'] !== '') {
                    $ref = $row['ref'];
                    break;
                }
            }

            // build lines
            $rawLines = [];
            foreach ($g['rows'] as $row) {
                if ($row['debit'] === 0.0 && $row['credit'] === 0.0) {
                    continue;
                }
                if ($row['account_id'] === null) {
                    $errors[] = "Row {$row['n']}: account '" . ($row['label'] ?: '(blank)') . "' is not mapped.";

                    continue;
                }
                $acc = $accById[$row['account_id']];
                if ((int) $acc['is_group'] === 1) {
                    $errors[] = "Row {$row['n']}: {$acc['code']} {$acc['name']} is a header account.";
                }
                $custId = $suppId = null;
                if ($acc['subledger'] === 'customer') {
                    if ($row['party'] === '') {
                        $errors[] = "Row {$row['n']}: {$acc['name']} needs a customer name.";
                    } else {
                        $custId = $existingCustomers[AccountAliasModel::normalise($row['party'])] ?? null;
                        if ($custId === null && ! in_array($row['party'], $newCustomers, true)) {
                            $newCustomers[] = $row['party'];
                        }
                    }
                } elseif ($acc['subledger'] === 'supplier') {
                    if ($row['party'] === '') {
                        $errors[] = "Row {$row['n']}: {$acc['name']} needs a supplier name.";
                    } else {
                        $suppId = $existingSuppliers[AccountAliasModel::normalise($row['party'])] ?? null;
                        if ($suppId === null && ! in_array($row['party'], $newSuppliers, true)) {
                            $newSuppliers[] = $row['party'];
                        }
                    }
                }

                $rawLines[] = [
                    'account_id'   => $row['account_id'],
                    'memo'         => $row['memo'],
                    'debit'        => $row['debit'] < 0 ? 0 : $row['debit'],
                    'credit'       => $row['credit'] < 0 ? 0 : $row['credit'],
                    // negatives folded to the opposite side
                    '_flipD'       => $row['debit'] < 0 ? -$row['debit'] : 0,
                    '_flipC'       => $row['credit'] < 0 ? -$row['credit'] : 0,
                    'customer_id'  => $custId,
                    'supplier_id'  => $suppId,
                    // party is only meaningful on subledger accounts
                    'party'        => in_array($acc['subledger'], ['customer', 'supplier'], true) ? $row['party'] : '',
                    'subledger'    => $acc['subledger'],
                    'account_code' => $acc['code'],
                    'account_name' => $acc['name'],
                ];
            }
            // apply folded negatives, then add base-currency amounts for display
            foreach ($rawLines as &$rl) {
                $rl['credit'] += $rl['_flipD'];
                $rl['debit']  += $rl['_flipC'];
                unset($rl['_flipD'], $rl['_flipC']);
                $rl['debit_base']  = round($rl['debit'] * $rate, 2);
                $rl['credit_base'] = round($rl['credit'] * $rate, 2);
            }
            unset($rl);

            $summary['lines'] += count($rawLines);

            if (count($rawLines) < 2) {
                $errors[] = 'Fewer than two usable lines.';
            }
            $txtD = round(array_sum(array_column($rawLines, 'debit')), 2);
            $txtC = round(array_sum(array_column($rawLines, 'credit')), 2);
            if (abs($txtD - $txtC) > 0.01) {
                $errors[] = sprintf('Out of balance: %s vs %s.', number_format($txtD, 2), number_format($txtC, 2));
            }
            $totalBase = round(array_sum(array_column($rawLines, 'debit_base')), 2);

            // journal number
            $no       = $key;
            $usableNo = $no !== '' && ! str_starts_with($no, '(no group)') && mb_strlen($no) <= 30;
            $status   = 'ok';
            if ($usableNo && (isset($existingJournalNos[$no]) || isset($seenNos[$no]))) {
                $status = 'skip';
                $warn[] = 'A journal ' . $no . ' already exists — will be skipped.';
            }
            if (! $usableNo) {
                $no = null; // generated at commit time
            }
            $seenNos[$no ?? $key] = true;

            if ($errors !== [] && $status !== 'skip') {
                $status = 'error';
            }
            $summary[$status]++;

            $journals[] = [
                'key'        => $key,
                'no'         => $no,
                'date'       => $date,
                'description'=> $descr,
                'reference'  => $ref ?: null,
                'currency'   => $currency['code'],
                'currency_id'=> (int) $currency['id'],
                'rate'       => $rate,
                'source'     => $this->mapSource((string) ($first['source'] ?? ''), $defSource),
                'lines'      => $rawLines,
                'rawLines'   => $rawLines,
                'total_base' => $totalBase,
                'balanced'   => abs($txtD - $txtC) <= 0.01 && count($rawLines) >= 2,
                'status'     => $status,
                'errors'     => $errors,
                'warnings'   => $warn,
                'first_row'  => $g['first'],
            ];
        }

        return [
            'journals'     => $journals,
            'summary'      => $summary,
            'newCustomers' => $newCustomers,
            'newSuppliers' => $newSuppliers,
            'unmapped'     => array_values($unmapped),
        ];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param array<string,mixed> $parsed result of parse()
     *
     * @return array{journals:int,skipped:int,customers:int,suppliers:int}
     */
    public function commit(int $batchId, array $parsed, int $userId): array
    {
        $db = db_connect();
        $db->transStart();

        $customers = model(CustomerModel::class);
        $suppliers = model(SupplierModel::class);
        $journals  = model(JournalModel::class);
        $lines     = model(JournalLineModel::class);
        $poster    = new JournalPoster();

        $custIdx = $this->nameIndex($customers->findAll());
        $suppIdx = $this->nameIndex($suppliers->findAll());
        $newCust = 0;
        $newSupp = 0;

        foreach ($parsed['newCustomers'] as $name) {
            $k = AccountAliasModel::normalise($name);
            if (isset($custIdx[$k])) {
                continue;
            }
            $id           = $customers->insert(['code' => $this->partyCode('C', $customers), 'name' => $name, 'is_active' => 1], true);
            $custIdx[$k]   = (int) $id;
            $newCust++;
        }
        foreach ($parsed['newSuppliers'] as $name) {
            $k = AccountAliasModel::normalise($name);
            if (isset($suppIdx[$k])) {
                continue;
            }
            $id          = $suppliers->insert(['code' => $this->partyCode('S', $suppliers), 'name' => $name, 'is_active' => 1], true);
            $suppIdx[$k]  = (int) $id;
            $newSupp++;
        }

        $made    = 0;
        $skipped = 0;
        $seqBase = date('ym');
        $seq     = $journals->like('journal_no', 'IMP-' . $seqBase . '-', 'after')->orderBy('journal_no', 'DESC')->first();
        $seq     = $seq ? (int) substr($seq['journal_no'], -4) : 0;

        foreach ($parsed['journals'] as $j) {
            if ($j['status'] !== 'ok') {
                $skipped++;

                continue;
            }

            $no = $j['no'];
            if ($no === null) {
                $no = 'IMP-' . $seqBase . '-' . str_pad((string) (++$seq), 4, '0', STR_PAD_LEFT);
            }

            // resolve party ids now that new parties exist
            $raw = [];
            foreach ($j['rawLines'] as $rl) {
                $cid = $rl['customer_id'];
                $sid = $rl['supplier_id'];
                if (($rl['subledger'] ?? 'none') !== 'none' && $cid === null && $sid === null && $rl['party'] !== '') {
                    $k = AccountAliasModel::normalise($rl['party']);
                    if ($rl['subledger'] === 'customer') {
                        $cid = $custIdx[$k] ?? null;
                    } else {
                        $sid = $suppIdx[$k] ?? null;
                    }
                }
                $raw[] = [
                    'account_id'  => $rl['account_id'],
                    'memo'        => $rl['memo'],
                    'debit'       => $rl['debit'],
                    'credit'      => $rl['credit'],
                    'customer_id' => $cid,
                    'supplier_id' => $sid,
                ];
            }
            $prepared = $poster->prepareLines($raw, $j['rate']);

            $jid = $journals->insert([
                'journal_no'      => $no,
                'entry_date'      => $j['date'],
                'reference'       => $j['reference'],
                'description'     => $j['description'],
                'source'          => $j['source'],
                'currency_id'     => $j['currency_id'],
                'exchange_rate'   => $j['rate'],
                'status'          => 'draft',
                'total_debit'     => $prepared['totalBase'],
                'total_credit'    => $prepared['totalBase'],
                'import_batch_id' => $batchId,
                'created_by'      => $userId,
            ], true);

            foreach ($prepared['lines'] as $line) {
                $line['journal_id'] = $jid;
                $lines->insert($line);
            }
            $made++;
        }

        model(ImportBatchModel::class)->update($batchId, [
            'status'        => 'committed',
            'journal_count' => $made,
            'skipped_count' => $skipped,
            'committed_at'  => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        return ['journals' => $made, 'skipped' => $skipped, 'customers' => $newCust, 'suppliers' => $newSupp];
    }

    // ---------------------------------------------------------------- misc

    private function mapSource(string $raw, string $default): string
    {
        $valid = array_keys(JournalModel::SOURCES);
        $norm  = str_replace([' ', '-', '/'], '_', mb_strtolower(trim($raw)));

        return in_array($norm, $valid, true) ? $norm : $default;
    }

    /** @return array<string,int> normalised name => id */
    private function nameIndex(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[AccountAliasModel::normalise($r['name'])] = (int) $r['id'];
        }

        return $out;
    }

    private function existingJournalNos(): array
    {
        $out = [];
        foreach (model(JournalModel::class)->select('journal_no')->findAll() as $r) {
            $out[$r['journal_no']] = true;
        }

        return $out;
    }

    private function partyCode(string $prefix, $model): string
    {
        $last = $model->like('code', $prefix, 'after')->orderBy('code', 'DESC')->first();
        $n    = $last ? ((int) substr($last['code'], 1) + 1) : 1;

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
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
