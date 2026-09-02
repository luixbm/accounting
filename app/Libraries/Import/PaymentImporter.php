<?php

namespace App\Libraries\Import;

use App\Libraries\Accounting\PurchasePoster;
use App\Models\CurrencyModel;
use App\Models\ExchangeRateModel;
use App\Models\PurchasePaymentModel;
use Config\Database;

/**
 * Imports a worked-back "payment list" spreadsheet and posts the payments.
 *
 * The user exports the Payment List report, does their grouping / approval in
 * Excel, then uploads the result here. Each row carries an invoice number and
 * the amount to pay; rows are matched to posted/partial purchase invoices,
 * summed per invoice, grouped by supplier + currency, and posted as one
 * Supplier Payment per group via PurchasePoster::savePayment (the same engine
 * as the Pay Supplier screen). Amounts are capped at the invoice's outstanding;
 * a row may pay less (partial). Reversing a batch voids its payments.
 */
class PaymentImporter
{
    /** logical field => [label, required?] */
    public const FIELDS = [
        'number' => ['Invoice number', true],
        'amount' => ['Amount to pay', true],
    ];

    private $db;
    private PurchasePaymentModel $payments;
    private CurrencyModel $currencies;

    public function __construct()
    {
        $this->db         = Database::connect();
        $this->payments   = model(PurchasePaymentModel::class);
        $this->currencies = model(CurrencyModel::class);
    }

    // ---------------------------------------------------------------- grid helpers

    /** @param list<list<mixed>> $grid @return array<int,string> */
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

    /**
     * @param array<int,string> $headers
     *
     * @return array<string,int>
     */
    public function guessMap(array $headers): array
    {
        $norm  = static fn (string $s): string => preg_replace('/[^a-z0-9]+/', '', strtolower($s));
        $alias = [
            'number'        => 'number',
            'invoiceno'     => 'number',
            'invoicenumber' => 'number',
            'no'            => 'number',
            'amount'        => 'amount',
            'amounttopay'   => 'amount',
            'totalpayment'  => 'amount',
            'payamount'     => 'amount',
            'pay'           => 'amount',
            'payment'       => 'amount',
        ];
        $map = [];
        foreach ($headers as $i => $label) {
            $k = $norm($label);
            if (isset($alias[$k]) && ! isset($map[$alias[$k]])) {
                $map[$alias[$k]] = $i;
            }
        }

        return $map;
    }

    // ---------------------------------------------------------------- parse

    /**
     * @param list<list<mixed>>   $grid
     * @param array<string,int>   $map
     * @param array<string,mixed> $opt   headerRow
     *
     * @return array{groups: list<array<string,mixed>>, summary: array<string,mixed>, unmatched: list<array<string,mixed>>}
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $get       = static fn (array $cells, ?int $col): string => $col === null ? '' : trim((string) ($cells[$col] ?? ''));
        $num       = static fn ($v): float => round((float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $v)), 2);

        // pull every payable purchase invoice once, keyed on internal_no
        $inv = [];
        foreach ($this->db->table('purchase_invoices i')
            ->select('i.id, i.internal_no, i.supplier_id, i.currency_id, i.invoice_date, i.total, i.paid, s.name AS supplier, cur.code AS currency_code')
            ->join('suppliers s', 's.id = i.supplier_id', 'left')
            ->join('currencies cur', 'cur.id = i.currency_id', 'left')
            ->where('i.company_id', active_company_id())
            ->whereIn('i.status', ['posted', 'partial'])
            ->get()->getResultArray() as $r) {
            $inv[mb_strtolower(trim($r['internal_no']))] = $r;
        }

        /** @var array<int,array<string,mixed>> $byInvoice invoiceId => aggregate */
        $byInvoice = [];
        $unmatched = [];
        $rowCount  = 0;

        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $c   = $r['cells'];
            $noS = $get($c, $map['number'] ?? null);
            $amt = $num($get($c, $map['amount'] ?? null));
            if ($noS === '' && $amt == 0.0) {
                continue; // blank row
            }
            $rowCount++;

            $key = mb_strtolower($noS);
            if (! isset($inv[$key])) {
                $unmatched[] = ['n' => $r['n'], 'number' => $noS, 'amount' => $amt, 'why' => 'no payable invoice with this number'];

                continue;
            }
            if ($amt <= 0.005) {
                $unmatched[] = ['n' => $r['n'], 'number' => $noS, 'amount' => $amt, 'why' => 'amount is zero / blank'];

                continue;
            }
            $iv  = $inv[$key];
            $iid = (int) $iv['id'];
            if (! isset($byInvoice[$iid])) {
                $out = round((float) $iv['total'] - (float) $iv['paid'], 2);
                $byInvoice[$iid] = [
                    'invoice_id'   => $iid,
                    'number'       => $iv['internal_no'],
                    'supplier_id'  => (int) $iv['supplier_id'],
                    'supplier'     => $iv['supplier'],
                    'currency_id'  => (int) $iv['currency_id'],
                    'currency'     => $iv['currency_code'],
                    'invoice_date' => $iv['invoice_date'],
                    'outstanding'  => $out,
                    'requested'    => 0.0,
                    'rows'         => 0,
                ];
            }
            $byInvoice[$iid]['requested'] += $amt;
            $byInvoice[$iid]['rows']++;
        }

        // finalise: cap at outstanding, group by supplier + currency
        $groups = [];
        $sum    = ['rows' => $rowCount, 'invoices' => 0, 'suppliers' => 0, 'unmatched' => count($unmatched),
            'pay_total' => 0.0, 'capped' => 0, 'bad_date' => 0];

        foreach ($byInvoice as $iv) {
            $pay     = min(round($iv['requested'], 2), $iv['outstanding']);
            $capped  = $iv['requested'] - $iv['outstanding'] > 0.01;
            $iv['pay']     = $pay;
            $iv['capped']  = $capped;
            $iv['partial'] = $pay + 0.01 < $iv['outstanding'];

            $gkey = $iv['supplier_id'] . ':' . $iv['currency_id'];
            $groups[$gkey] ??= [
                'supplier_id' => $iv['supplier_id'], 'supplier' => $iv['supplier'],
                'currency_id' => $iv['currency_id'], 'currency' => $iv['currency'],
                'invoices' => [], 'total' => 0.0,
            ];
            $groups[$gkey]['invoices'][] = $iv;
            $groups[$gkey]['total']     += $pay;

            $sum['invoices']++;
            $sum['pay_total'] += $pay;
            if ($capped) {
                $sum['capped']++;
            }
        }
        $sum['suppliers'] = count(array_unique(array_map(static fn ($g) => $g['supplier_id'], $groups)));
        $sum['pay_total'] = round($sum['pay_total'], 2);

        usort($groups, static fn ($a, $b) => strcasecmp((string) $a['supplier'], (string) $b['supplier']));

        return ['groups' => array_values($groups), 'summary' => $sum, 'unmatched' => $unmatched];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param array<string,mixed> $parsed  result of parse()
     * @param array<string,mixed> $header  payment_date, bank_account_id, reference
     *
     * @return array{payments:int, invoices:int, amount:float, errors: list<string>, payment_ids: list<int>}
     */
    public function commit(array $parsed, int $batchId, array $header): array
    {
        $poster = new PurchasePoster();
        $res    = ['payments' => 0, 'invoices' => 0, 'amount' => 0.0, 'errors' => [], 'payment_ids' => []];

        foreach ($parsed['groups'] as $g) {
            $allocations = [];
            foreach ($g['invoices'] as $iv) {
                if ($iv['pay'] > 0.005) {
                    $allocations[$iv['invoice_id']] = $iv['pay'];
                }
            }
            if (! $allocations) {
                continue;
            }

            $isBase = $this->currencies->isBase((int) $g['currency_id']);
            $rate   = $isBase ? 1.0 : model(ExchangeRateModel::class)->rateFor((int) $g['currency_id'], (string) $header['payment_date']);

            $r = $poster->savePayment([
                'supplier_id'     => $g['supplier_id'],
                'payment_date'    => $header['payment_date'],
                'bank_account_id' => $header['bank_account_id'],
                'reference'       => $header['reference'] ?? null,
                'currency_id'     => $g['currency_id'],
                'exchange_rate'   => $rate,
            ], $allocations);

            if (! empty($r['ok'])) {
                $res['payments']++;
                $res['invoices'] += count($allocations);
                $res['amount']   += array_sum($allocations);
                $res['payment_ids'][] = (int) $r['id'];
            } else {
                $res['errors'][] = $g['supplier'] . ' (' . $g['currency'] . '): ' . implode(' ', $r['errors'] ?? ['payment failed']);
            }
        }
        $res['amount'] = round($res['amount'], 2);

        return $res;
    }

    /**
     * Void every payment this batch created that is still posted and untouched.
     *
     * @return array{voided:int, kept:int}
     */
    public function revert(int $batchId, array $paymentIds): array
    {
        $poster = new PurchasePoster();
        $voided = 0;
        $kept   = 0;
        foreach ($paymentIds as $pid) {
            $p = $this->payments->find((int) $pid);
            if (! $p || $p['status'] === 'void') {
                continue;
            }
            $r = $poster->voidPayment((int) $pid, 'Reverted payment import batch #' . $batchId);
            if (! empty($r['ok'])) {
                $voided++;
            } else {
                $kept++;
            }
        }

        return ['voided' => $voided, 'kept' => $kept];
    }

    private function colLetter(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $i--;
            $s = chr(65 + ($i % 26)) . $s;
            $i = intdiv($i, 26);
        }

        return $s;
    }
}
