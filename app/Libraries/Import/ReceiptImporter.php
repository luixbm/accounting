<?php

namespace App\Libraries\Import;

use App\Libraries\Accounting\SalesPoster;
use App\Models\CurrencyModel;
use App\Models\ExchangeRateModel;
use App\Models\SalesReceiptModel;
use Config\Database;

/**
 * Imports a worked-back list of customer settlements and posts them.
 *
 * Two modes (chosen on the map step):
 *   - "receipt": each row = money received into a bank for a sales invoice.
 *                Posted as one Customer Receipt per customer via
 *                SalesPoster::saveReceipt (Dr bank / Cr A/R).
 *   - "deposit": each row = an amount of an existing customer down payment to
 *                apply to a sales invoice. Per customer the batch draws from
 *                their oldest unapplied deposit via SalesPoster::applyDeposit
 *                (a reclass journal, no cash). Not revertible from the batch -
 *                use the deposit's own Unapply screen.
 *
 * Amounts are capped at each invoice's outstanding; in deposit mode the
 * customer's total is also capped at the deposit's unapplied balance.
 */
class ReceiptImporter
{
    /** logical field => [label, required?] */
    public const FIELDS = [
        'number' => ['Invoice number', true],
        'amount' => ['Amount', true],
    ];

    private $db;
    private SalesReceiptModel $receipts;
    private CurrencyModel $currencies;

    public function __construct()
    {
        $this->db         = Database::connect();
        $this->receipts   = model(SalesReceiptModel::class);
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
            'amountreceived'=> 'amount',
            'amounttoapply' => 'amount',
            'received'      => 'amount',
            'payment'       => 'amount',
            'pay'           => 'amount',
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
     * @param array<string,mixed> $opt  headerRow + header.mode
     *
     * @return array{mode:string, groups: list<array<string,mixed>>, summary: array<string,mixed>, unmatched: list<array<string,mixed>>}
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $mode      = ($opt['header']['mode'] ?? 'receipt') === 'deposit' ? 'deposit' : 'receipt';
        $get       = static fn (array $cells, ?int $col): string => $col === null ? '' : trim((string) ($cells[$col] ?? ''));
        $num       = static fn ($v): float => round((float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $v)), 2);

        $inv = [];
        foreach ($this->db->table('sales_invoices i')
            ->select('i.id, i.internal_no, i.customer_id, i.currency_id, i.invoice_date, i.total, i.received, c.name AS customer, cur.code AS currency_code')
            ->join('customers c', 'c.id = i.customer_id', 'left')
            ->join('currencies cur', 'cur.id = i.currency_id', 'left')
            ->where('i.company_id', active_company_id())
            ->whereIn('i.status', ['posted', 'partial'])
            ->get()->getResultArray() as $r) {
            $inv[mb_strtolower(trim($r['internal_no']))] = $r;
        }

        $byInvoice = [];
        $unmatched = [];
        $rowCount  = 0;

        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $c   = $r['cells'];
            $noS = $get($c, $map['number'] ?? null);
            $amt = $num($get($c, $map['amount'] ?? null));
            if ($noS === '' && $amt == 0.0) {
                continue;
            }
            $rowCount++;

            $key = mb_strtolower($noS);
            if (! isset($inv[$key])) {
                $unmatched[] = ['n' => $r['n'], 'number' => $noS, 'amount' => $amt, 'why' => 'no open sales invoice with this number'];

                continue;
            }
            if ($amt <= 0.005) {
                $unmatched[] = ['n' => $r['n'], 'number' => $noS, 'amount' => $amt, 'why' => 'amount is zero / blank'];

                continue;
            }
            $iv  = $inv[$key];
            $iid = (int) $iv['id'];
            if (! isset($byInvoice[$iid])) {
                $out = round((float) $iv['total'] - (float) $iv['received'], 2);
                $byInvoice[$iid] = [
                    'invoice_id'   => $iid,
                    'number'       => $iv['internal_no'],
                    'customer_id'  => (int) $iv['customer_id'],
                    'customer'     => $iv['customer'],
                    'currency_id'  => (int) $iv['currency_id'],
                    'currency'     => $iv['currency_code'],
                    'invoice_date' => $iv['invoice_date'],
                    'outstanding'  => $out,
                    'requested'    => 0.0,
                ];
            }
            $byInvoice[$iid]['requested'] += $amt;
        }

        // deposit balances per customer (oldest unapplied deposit)
        $depByCustomer = [];
        if ($mode === 'deposit') {
            foreach ($this->db->table('sales_receipts')
                ->select('id, customer_id, receipt_no, receipt_date, currency_id, unapplied')
                ->where('company_id', active_company_id())
                ->where('kind', 'deposit')->where('status', 'posted')
                ->where('unapplied >', 0.005)
                ->orderBy('receipt_date', 'ASC')->orderBy('id', 'ASC')
                ->get()->getResultArray() as $d) {
                $depByCustomer[(int) $d['customer_id']] ??= $d; // first = oldest
            }
        }

        $groups = [];
        $sum    = ['mode' => $mode, 'rows' => $rowCount, 'invoices' => 0, 'customers' => 0,
            'unmatched' => count($unmatched), 'apply_total' => 0.0, 'capped' => 0, 'no_deposit' => 0, 'over_deposit' => 0];

        foreach ($byInvoice as $iv) {
            $pay    = min(round($iv['requested'], 2), $iv['outstanding']);
            $capped = $iv['requested'] - $iv['outstanding'] > 0.01;

            $gkey = $iv['customer_id'] . ':' . $iv['currency_id'];
            if (! isset($groups[$gkey])) {
                $dep = $mode === 'deposit' ? ($depByCustomer[$iv['customer_id']] ?? null) : null;
                $groups[$gkey] = [
                    'customer_id' => $iv['customer_id'], 'customer' => $iv['customer'],
                    'currency_id' => $iv['currency_id'], 'currency' => $iv['currency'],
                    'deposit'     => $dep,
                    'deposit_left'=> $dep ? (float) $dep['unapplied'] : null,
                    'invoices'    => [], 'total' => 0.0,
                ];
            }
            $g = &$groups[$gkey];

            $skip = '';
            if ($mode === 'deposit') {
                if (! $g['deposit']) {
                    $skip = 'no unapplied deposit for this customer';
                    $sum['no_deposit']++;
                } elseif ($g['deposit_left'] <= 0.005) {
                    $skip = 'deposit balance exhausted';
                    $sum['over_deposit']++;
                    $pay  = 0.0;
                } elseif ($pay > $g['deposit_left'] + 0.005) {
                    $pay  = round($g['deposit_left'], 2);
                    $g['deposit_left'] = 0.0;
                    $sum['over_deposit']++;
                } else {
                    $g['deposit_left'] = round($g['deposit_left'] - $pay, 2);
                }
            }

            $iv['pay']     = $skip !== '' && $skip !== 'deposit balance exhausted' ? 0.0 : $pay;
            $iv['capped']  = $capped;
            $iv['partial'] = $iv['pay'] > 0.005 && $iv['pay'] + 0.01 < $iv['outstanding'];
            $iv['skip']    = $skip;

            $g['invoices'][] = $iv;
            $g['total']     += $iv['pay'];
            unset($g);

            $sum['invoices']++;
            $sum['apply_total'] += $groups[$gkey]['invoices'][count($groups[$gkey]['invoices']) - 1]['pay'];
            if ($capped) {
                $sum['capped']++;
            }
        }
        $sum['customers']   = count($groups);
        $sum['apply_total'] = round($sum['apply_total'], 2);

        usort($groups, static fn ($a, $b) => strcasecmp((string) $a['customer'], (string) $b['customer']));

        return ['mode' => $mode, 'groups' => array_values($groups), 'summary' => $sum, 'unmatched' => $unmatched];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param array<string,mixed> $parsed
     * @param array<string,mixed> $header  mode, bank_account_id, date, reference
     *
     * @return array{posts:int, invoices:int, amount:float, errors: list<string>, receipt_ids: list<int>}
     */
    public function commit(array $parsed, int $batchId, array $header): array
    {
        $poster = new SalesPoster();
        $mode   = ($header['mode'] ?? 'receipt') === 'deposit' ? 'deposit' : 'receipt';
        $res    = ['posts' => 0, 'invoices' => 0, 'amount' => 0.0, 'errors' => [], 'receipt_ids' => []];

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

            if ($mode === 'deposit') {
                if (empty($g['deposit'])) {
                    $res['errors'][] = $g['customer'] . ': no unapplied deposit.';

                    continue;
                }
                $r = $poster->applyDeposit((int) $g['deposit']['id'], $allocations, (string) $header['date']);
            } else {
                $isBase = $this->currencies->isBase((int) $g['currency_id']);
                $rate   = $isBase ? 1.0 : model(ExchangeRateModel::class)->rateFor((int) $g['currency_id'], (string) $header['date']);
                $r = $poster->saveReceipt([
                    'customer_id'     => $g['customer_id'],
                    'receipt_date'    => $header['date'],
                    'bank_account_id' => $header['bank_account_id'],
                    'reference'       => $header['reference'] ?? null,
                    'currency_id'     => $g['currency_id'],
                    'exchange_rate'   => $rate,
                ], $allocations);
            }

            if (! empty($r['ok'])) {
                $res['posts']++;
                $res['invoices'] += count($allocations);
                $res['amount']   += array_sum($allocations);
                if (isset($r['id'])) {
                    $res['receipt_ids'][] = (int) $r['id'];
                }
            } else {
                $res['errors'][] = $g['customer'] . ' (' . $g['currency'] . '): ' . implode(' ', $r['errors'] ?? ['failed']);
            }
        }
        $res['amount'] = round($res['amount'], 2);

        return $res;
    }

    /**
     * Void every receipt this batch created (receipt mode only).
     *
     * @return array{voided:int, kept:int}
     */
    public function revert(int $batchId, array $receiptIds): array
    {
        $poster = new SalesPoster();
        $voided = 0;
        $kept   = 0;
        foreach ($receiptIds as $rid) {
            $rc = $this->receipts->find((int) $rid);
            if (! $rc || $rc['status'] === 'void') {
                continue;
            }
            $r = $poster->voidReceipt((int) $rid, 'Reverted receipt import batch #' . $batchId);
            $r['ok'] ? $voided++ : $kept++;
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
