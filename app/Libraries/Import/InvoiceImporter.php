<?php

namespace App\Libraries\Import;

use App\Libraries\Accounting\PurchasePoster;
use App\Libraries\Accounting\SalesPoster;
use App\Models\AccountAliasModel;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\ExchangeRateModel;
use App\Models\ImportBatchModel;
use App\Models\JobModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SalesInvoiceModel;
use App\Models\SupplierModel;

/**
 * Builds draft purchase or sales invoices from a spreadsheet.
 * Each row is one invoice line; rows sharing the "group" column form one invoice.
 */
class InvoiceImporter
{
    public const FIELDS = [
        'group'         => ['Invoice group / no.', true],
        'date'          => ['Invoice date', true],
        'party'         => ['Supplier / Customer name', true],
        'account_label' => ['Account (name or code)', true],
        'amount'        => ['Line amount', true],
        'party_ref'     => ['Their invoice / PO no.', false],
        'due_date'      => ['Due date', false],
        'line_desc'     => ['Line description', false],
        'job'           => ['Job code', false],
        'description'   => ['Invoice description', false],
        'ppn'           => ['PPN amount', false],
        'pph'           => ['PPh amount', false],
        'currency'      => ['Currency code', false],
        'rate'          => ['Exchange rate', false],
        'line_booking'  => ['Line booking ref', false],
        'line_service'  => ['Line service date', false],
        'line_party'    => ['Line party / traveller name', false],
        'line_units'    => ['Line units', false],
        'line_nights'   => ['Line nights', false],
    ];

    /** @var 'purchase'|'sales' */
    private string $kind;
    private AccountModel $accounts;
    private AccountAliasModel $aliases;
    private CurrencyModel $currencies;
    private ExchangeRateModel $rates;
    private JobModel $jobs;

    public function __construct(string $kind)
    {
        $this->kind       = $kind === 'sales' ? 'sales' : 'purchase';
        $this->accounts   = model(AccountModel::class);
        $this->aliases    = model(AccountAliasModel::class);
        $this->currencies = model(CurrencyModel::class);
        $this->rates      = model(ExchangeRateModel::class);
        $this->jobs       = model(JobModel::class);
    }

    // ------------------------------------------------------------- grid helpers

    /** @param list<list<mixed>> $grid @return array<int,string> */
    public function headers(array $grid, int $headerRow): array
    {
        $row   = $grid[$headerRow - 1] ?? [];
        $width = max(array_map('count', array_slice($grid, 0, 50) ?: [[]]));
        $out   = [];
        for ($i = 0; $i < max($width, count($row)); $i++) {
            $txt     = trim((string) ($row[$i] ?? ''));
            $out[$i] = $txt !== '' ? $txt : ('Column ' . $this->colLetter($i));
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

    /** @return list<string> */
    public function distinctAccountLabels(array $grid, int $headerRow, array $map): array
    {
        $col = $map['account_label'] ?? null;
        if ($col === null) {
            return [];
        }
        $seen = [];
        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $label = trim((string) ($r['cells'][$col] ?? ''));
            if ($label !== '') {
                $seen[AccountAliasModel::normalise($label)] = $label;
            }
        }
        ksort($seen);

        return array_values($seen);
    }

    // ------------------------------------------------------------- parse

    /**
     * @return array{invoices:list<array<string,mixed>>, summary:array<string,int>, newParties:list<string>, unmapped:list<string>}
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $dateFmt   = $opt['dateFormat'] ?? 'auto';

        $aliasMap  = $this->aliases->map();
        $accById   = [];
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
        $jobByCode = [];
        foreach ($this->jobs->findAll() as $j) {
            $jobByCode[strtoupper($j['code'])] = (int) $j['id'];
        }

        $partyModel  = $this->kind === 'sales' ? model(CustomerModel::class) : model(SupplierModel::class);
        $partyIndex  = [];
        foreach ($partyModel->findAll() as $p) {
            $partyIndex[AccountAliasModel::normalise($p['name'])] = (int) $p['id'];
        }
        $invModel      = $this->kind === 'sales' ? model(SalesInvoiceModel::class) : model(PurchaseInvoiceModel::class);
        $existingExt   = [];
        foreach ($invModel->select('external_id')->where('external_id IS NOT NULL')->findAll() as $r) {
            $existingExt[(string) $r['external_id']] = true;
        }

        $get = static fn (array $cells, string $f) => isset($map[$f]) ? trim((string) ($cells[$map[$f]] ?? '')) : '';

        $groups   = [];
        $order    = [];
        $unmapped = [];

        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $cells  = $r['cells'];
            $label  = $get($cells, 'account_label');
            $amount = SpreadsheetReader::toNumber($cells[$map['amount']] ?? null);
            if ($label === '' && $amount === 0.0) {
                continue;
            }
            $key = $get($cells, 'group') ?: ('(no group) row ' . $r['n']);
            if (! isset($groups[$key])) {
                $order[]      = $key;
                $groups[$key] = ['key' => $key, 'rows' => []];
            }

            $norm  = AccountAliasModel::normalise($label);
            $accId = $aliasMap[$norm] ?? $accByCode[$norm] ?? null;
            if ($accId === null && $label !== '') {
                $unmapped[$norm] = $label;
            }

            $groups[$key]['rows'][] = [
                'n'          => $r['n'],
                'label'      => $label,
                'account_id' => $accId,
                'amount'     => $amount,
                'desc'       => $get($cells, 'line_desc'),
                'job'        => strtoupper($get($cells, 'job')),
                'date'       => $get($cells, 'date'),
                'due'        => $get($cells, 'due_date'),
                'party'      => $get($cells, 'party'),
                'party_ref'  => $get($cells, 'party_ref'),
                'invdesc'    => $get($cells, 'description'),
                'ppn'        => $get($cells, 'ppn'),
                'pph'        => $get($cells, 'pph'),
                'cur'        => strtoupper($get($cells, 'currency')),
                'rate'       => $get($cells, 'rate'),
                'l_booking'  => $get($cells, 'line_booking'),
                'l_service'  => $get($cells, 'line_service'),
                'l_party'    => $get($cells, 'line_party'),
                'l_units'    => $get($cells, 'line_units'),
                'l_nights'   => $get($cells, 'line_nights'),
            ];
        }

        $invoices   = [];
        $newParties = [];
        $summary    = ['groups' => 0, 'ok' => 0, 'error' => 0, 'skip' => 0, 'lines' => 0];

        foreach ($order as $key) {
            $g      = $groups[$key];
            $first  = $g['rows'][0];
            $errors = [];
            $warn   = [];
            $summary['groups']++;

            $firstOf = static function (string $f) use ($g) {
                foreach ($g['rows'] as $row) {
                    if (($row[$f] ?? '') !== '') {
                        return $row[$f];
                    }
                }

                return '';
            };

            $date = SpreadsheetReader::toDate($firstOf('date'), $dateFmt);
            if ($date === null) {
                $errors[] = "Could not read an invoice date (got '" . $firstOf('date') . "').";
                $date     = date('Y-m-d');
            }
            $due     = SpreadsheetReader::toDate($firstOf('due'), $dateFmt);
            $partyNm = trim((string) $firstOf('party'));
            $partyId = $partyNm !== '' ? ($partyIndex[AccountAliasModel::normalise($partyNm)] ?? null) : null;
            if ($partyNm === '') {
                $errors[] = ucfirst($this->kind === 'sales' ? 'customer' : 'supplier') . ' name is missing.';
            } elseif ($partyId === null && ! in_array($partyNm, $newParties, true)) {
                $newParties[] = $partyNm;
            }

            $curCode  = strtoupper((string) $firstOf('cur'));
            $currency = $curCode !== '' ? ($curByCode[$curCode] ?? null) : $this->currencies->base();
            if ($curCode !== '' && $currency === null) {
                $errors[] = "Unknown currency '{$curCode}'.";
                $currency = $this->currencies->base();
            }
            $isBase = $this->currencies->isBase((int) $currency['id']);
            $rateRaw = (string) $firstOf('rate');
            $rate    = $isBase ? 1.0 : ($rateRaw !== '' ? SpreadsheetReader::toNumber($rateRaw) : $this->rates->rateFor((int) $currency['id'], $date));
            if ($rate <= 0) {
                $rate   = 1.0;
                $warn[] = 'No rate found; used 1.0.';
            }

            $lines    = [];
            $subtotal = 0.0;
            foreach ($g['rows'] as $row) {
                if ($row['amount'] === 0.0) {
                    continue;
                }
                if ($row['account_id'] === null) {
                    $errors[] = "Row {$row['n']}: account '" . ($row['label'] ?: '(blank)') . "' is not mapped.";

                    continue;
                }
                $jobId = $row['job'] !== '' ? ($jobByCode[$row['job']] ?? null) : null;
                if ($row['job'] !== '' && $jobId === null) {
                    $warn[] = "Job '{$row['job']}' not found — line left untagged.";
                }
                $lines[] = [
                    'account_id'    => $row['account_id'],
                    'job_id'        => $jobId,
                    'description'   => $row['desc'] ?: null,
                    'amount'        => $row['amount'],
                    // purchase imports carry a planned/quoted cost (same convention as the
                    // Jambix wizard) so Invoice Review has a budget baseline to check the
                    // real supplier invoice against; sales invoices have no such concept.
                    'budget_amount' => $this->kind === 'purchase' ? $row['amount'] : null,
                    'cost_source'   => $this->kind === 'purchase' ? 'budget' : null,
                    'booking_ref'   => $row['l_booking'] ?: null,
                    'service_date'  => $row['l_service'] !== '' ? SpreadsheetReader::toDate($row['l_service'], $dateFmt) : null,
                    'party_name'    => $row['l_party'] ?: null,
                    'units'         => $row['l_units'] ?: null,
                    'nights'        => $row['l_nights'] ?: null,
                ];
                $subtotal += $row['amount'];
            }
            $summary['lines'] += count($lines);
            if (! $lines) {
                $errors[] = 'No usable lines.';
            }

            $ppn      = SpreadsheetReader::toNumber($firstOf('ppn'));
            $pph      = SpreadsheetReader::toNumber($firstOf('pph'));
            $subtotal = round($subtotal, 2);
            $total    = round($subtotal + $ppn - $pph, 2);

            $extId  = ($key !== '' && ! str_starts_with($key, '(no group)')) ? mb_substr($key, 0, 80) : null;
            $status = 'ok';
            if ($extId !== null && isset($existingExt[$extId])) {
                $status = 'skip';
                $warn[] = 'Already imported (' . $extId . ') — skipped.';
            } elseif ($errors !== []) {
                $status = 'error';
            }
            $summary[$status]++;

            $invoices[] = [
                'key'          => $key,
                'external_id'  => $extId,
                'date'         => $date,
                'due_date'     => $due,
                'party_name'   => $partyNm,
                'party_id'     => $partyId,
                'party_ref'    => trim((string) $firstOf('party_ref')) ?: null,
                'description'  => trim((string) $firstOf('invdesc')) ?: null,
                'currency_id'  => (int) $currency['id'],
                'currency'     => $currency['code'],
                'rate'         => $rate,
                'lines'        => $lines,
                'subtotal'     => $subtotal,
                'ppn'          => $ppn,
                'pph'          => $pph,
                'total'        => $total,
                'total_base'   => round($total * $rate, 2),
                'status'       => $status,
                'errors'       => $errors,
                'warnings'     => $warn,
            ];
        }

        return [
            'invoices'   => $invoices,
            'summary'    => $summary,
            'newParties' => $newParties,
            'unmapped'   => array_values($unmapped),
        ];
    }

    // ------------------------------------------------------------- commit

    /**
     * @return array{invoices:int,skipped:int,parties:int}
     */
    /**
     * @param bool $post  post each invoice straight away (create its journal);
     *                     an invoice that fails to post is kept as a draft
     */
    public function commit(int $batchId, array $parsed, int $userId, bool $post = true): array
    {
        $db          = db_connect();
        $partyModel  = $this->kind === 'sales' ? model(CustomerModel::class) : model(SupplierModel::class);
        $invModel    = $this->kind === 'sales' ? model(SalesInvoiceModel::class) : model(PurchaseInvoiceModel::class);
        $poster      = $this->kind === 'sales' ? new SalesPoster() : new PurchasePoster();
        $refField    = $this->kind === 'sales' ? 'customer_ref' : 'supplier_ref';
        $partyField  = $this->kind === 'sales' ? 'customer_id' : 'supplier_id';

        $db->transStart();

        $idx = [];
        foreach ($partyModel->findAll() as $p) {
            $idx[AccountAliasModel::normalise($p['name'])] = (int) $p['id'];
        }
        $newParties = 0;
        foreach ($parsed['newParties'] as $name) {
            $k = AccountAliasModel::normalise($name);
            if (isset($idx[$k])) {
                continue;
            }
            $id      = $partyModel->insert(['code' => $this->partyCode($partyModel), 'name' => $name, 'is_active' => 1], true);
            $idx[$k] = (int) $id;
            $newParties++;
        }

        $made      = 0;
        $skipped   = 0;
        $createdId = [];
        foreach ($parsed['invoices'] as $inv) {
            if ($inv['status'] !== 'ok') {
                $skipped++;

                continue;
            }
            $pid = $inv['party_id'] ?? ($idx[AccountAliasModel::normalise($inv['party_name'])] ?? null);
            if ($pid === null) {
                $skipped++;

                continue;
            }

            $res = $poster->saveInvoice([
                $refField       => $inv['party_ref'],
                $partyField     => $pid,
                'invoice_date'  => $inv['date'],
                'due_date'      => $inv['due_date'],
                'currency_id'   => $inv['currency_id'],
                'exchange_rate' => $inv['rate'],
                'description'   => $inv['description'],
                'ppn_amount'    => $inv['ppn'],
                'pph_amount'    => $inv['pph'],
            ], $inv['lines']);

            if (! $res['ok']) {
                $skipped++;

                continue;
            }
            $invModel->update($res['id'], [
                'external_id'     => $inv['external_id'],
                'source'         => 'import',
                'import_batch_id'=> $batchId,
            ]);
            $made++;
            $createdId[] = (int) $res['id'];
        }

        model(ImportBatchModel::class)->update($batchId, [
            'status'        => 'committed',
            'journal_count' => $made,
            'skipped_count' => $skipped,
            'committed_at'  => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        // Post OUTSIDE the create transaction (mirrors postAll) so one bad post
        // fails a single invoice instead of rolling the whole batch back. A
        // logical failure (unbalanced, locked period, …) just leaves it a draft.
        $posted     = 0;
        $postFailed = 0;
        if ($post) {
            foreach ($createdId as $invId) {
                if (($poster->postInvoice($invId)['ok'] ?? false) === true) {
                    $posted++;
                } else {
                    $postFailed++;
                }
            }
        }

        return [
            'invoices'    => $made,
            'skipped'     => $skipped,
            'parties'     => $newParties,
            'posted'      => $posted,
            'post_failed' => $postFailed,
        ];
    }

    private function partyCode($model): string
    {
        $prefix = $this->kind === 'sales' ? 'C' : 'S';
        $last   = $model->like('code', $prefix, 'after')->orderBy('code', 'DESC')->first();
        $n      = $last ? ((int) substr($last['code'], 1) + 1) : 1;

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
