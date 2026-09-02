<?php

namespace App\Libraries\Accounting;

use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\FiscalPeriodModel;
use App\Models\SalesInvoiceLineModel;
use App\Models\SalesInvoiceModel;
use App\Models\SalesReceiptModel;

/**
 * Customer invoices and receipts. Posts through JournalPoster.
 *
 *   Invoice posting:  Dr Piutang Usaha (per customer) + Dr Uang Muka PPh 23 (withheld)
 *                     Cr Pendapatan lines + Cr PPN Keluaran
 *   Receipt posting:  Dr Bank                Cr Piutang Usaha
 */
class SalesPoster
{
    private SalesInvoiceModel $invoices;
    private SalesInvoiceLineModel $lines;
    private SalesReceiptModel $receipts;
    private AccountModel $accounts;
    private CurrencyModel $currencies;
    private FiscalPeriodModel $periods;
    private JournalPoster $journalPoster;

    public function __construct()
    {
        $this->invoices      = model(SalesInvoiceModel::class);
        $this->lines         = model(SalesInvoiceLineModel::class);
        $this->receipts      = model(SalesReceiptModel::class);
        $this->accounts      = model(AccountModel::class);
        $this->currencies    = model(CurrencyModel::class);
        $this->periods       = model(FiscalPeriodModel::class);
        $this->journalPoster = new JournalPoster();
    }

    private function ccyCode(int $currencyId): string
    {
        $c = $this->currencies->find($currencyId);

        return $c['code'] ?? base_code();
    }

    private function normDate($v): ?string
    {
        if (! is_string($v) && ! is_numeric($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' && ($t = strtotime($s)) ? date('Y-m-d', $t) : null;
    }

    private function accountId(string $settingKey): ?int
    {
        $code = acc_setting($settingKey);
        if ($code === null || $code === '') {
            return null;
        }
        $a = $this->accounts->byCode((string) $code);

        return $a ? (int) $a['id'] : null;
    }

    // ---------------------------------------------------------------- invoice

    /**
     * @return array{ok:bool,id?:int,errors?:list<string>}
     */
    public function saveInvoice(array $header, array $rawLines, ?int $id = null): array
    {
        $currency = $this->currencies->find((int) $header['currency_id']);
        if (! $currency) {
            return ['ok' => false, 'errors' => ['Unknown currency.']];
        }
        $rate = (int) $currency['is_base'] === 1 ? 1.0 : max(0.0, (float) ($header['exchange_rate'] ?? 1));

        $clean    = [];
        $no       = 1;
        $subtotal = 0.0;
        foreach ($rawLines as $l) {
            $accId  = (int) ($l['account_id'] ?? 0);
            $amount = round((float) str_replace([',', ' '], '', (string) ($l['amount'] ?? 0)), 2);
            if ($accId === 0) {
                continue;
            }
            $clean[] = [
                'line_no'      => $no++,
                'account_id'   => $accId,
                'job_id'       => ! empty($l['job_id']) ? (int) $l['job_id'] : null,
                'description'  => trim((string) ($l['description'] ?? '')) ?: null,
                'amount'       => $amount,
                'amount_base'  => round($amount * $rate, 2),
                'booking_ref'  => trim((string) ($l['booking_ref'] ?? '')) ?: null,
                'service_date' => $this->normDate($l['service_date'] ?? null),
                'party_name'   => trim((string) ($l['party_name'] ?? '')) ?: null,
                'units'        => trim((string) ($l['units'] ?? '')) ?: null,
                'nights'       => trim((string) ($l['nights'] ?? '')) ?: null,
                'pax'          => trim((string) ($l['pax'] ?? '')) ?: null,
                'duration'     => trim((string) ($l['duration'] ?? '')) ?: null,
                'remark'       => trim((string) ($l['remark'] ?? '')) ?: null,
            ];
            $subtotal += $amount;
        }
        if (! $clean) {
            return ['ok' => false, 'errors' => ['Add at least one line with an amount.']];
        }

        $ppn      = round((float) str_replace([',', ' '], '', (string) ($header['ppn_amount'] ?? 0)), 2);
        $pph      = round((float) str_replace([',', ' '], '', (string) ($header['pph_amount'] ?? 0)), 2);
        $subtotal = round($subtotal, 2);
        $total    = round($subtotal + $ppn - $pph, 2);

        $data = [
            'customer_ref'  => trim((string) ($header['customer_ref'] ?? '')) ?: null,
            'customer_id'   => (int) $header['customer_id'],
            'invoice_date'  => $header['invoice_date'],
            'due_date'      => $header['due_date'] ?: null,
            'currency_id'   => (int) $header['currency_id'],
            'exchange_rate' => $rate,
            'description'   => trim((string) ($header['description'] ?? '')) ?: null,
            'subtotal'      => $subtotal,
            'ppn_amount'    => $ppn,
            'pph_amount'    => $pph,
            'total'         => $total,
            'total_base'    => round($total * $rate, 2),
        ];

        $db = db_connect();
        $db->transStart();

        if ($id === null) {
            $data['status']      = 'draft';
            $data['created_by']  = auth()->id();
            $data['internal_no'] = $this->invoices->nextNo();
            $id                  = (int) $this->invoices->insert($data, true);
        } else {
            $existing = $this->invoices->find($id);
            if (! $existing || $existing['status'] !== 'draft') {
                $db->transComplete();

                return ['ok' => false, 'errors' => ['Only draft invoices can be edited.']];
            }
            $this->invoices->update($id, $data);
            $this->lines->where('invoice_id', $id)->delete();
        }

        foreach ($clean as $line) {
            $line['invoice_id'] = $id;
            $this->lines->insert($line);
        }

        $db->transComplete();

        return $db->transStatus() === false
            ? ['ok' => false, 'errors' => ['Database error while saving the invoice.']]
            : ['ok' => true, 'id' => $id];
    }

    public function postInvoice(int $id): array
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return ['ok' => false, 'errors' => ['Invoice not found.']];
        }
        if ($inv['status'] !== 'draft') {
            return ['ok' => false, 'errors' => ['Only draft invoices can be posted.']];
        }
        if ($this->periods->isDateLocked($inv['invoice_date'])) {
            return ['ok' => false, 'errors' => ['The period for ' . $inv['invoice_date'] . ' is closed.']];
        }

        $arId = ControlAccounts::id('ar', (int) $inv['currency_id']);
        if ($arId === null) {
            return ['ok' => false, 'errors' => ['No Trade A/R account is set for ' . $this->ccyCode((int) $inv['currency_id']) . ' — see Setup → Control Accounts.']];
        }

        $lines    = $this->lines->where('invoice_id', $id)->orderBy('line_no')->findAll();
        $rawLines = [];

        // Dr Piutang Usaha (net receivable) per customer
        $rawLines[] = [
            'account_id'  => $arId,
            'memo'        => 'Piutang - ' . ($inv['customer_ref'] ?: $inv['internal_no']),
            'debit'       => $inv['total'],
            'credit'      => 0,
            'customer_id' => $inv['customer_id'],
        ];

        if ((float) $inv['pph_amount'] > 0) {
            $prepaidId = $this->accountId('pph23PrepaidCode');
            if ($prepaidId === null) {
                return ['ok' => false, 'errors' => ['Set the Uang Muka PPh 23 account in Settings.']];
            }
            $rawLines[] = ['account_id' => $prepaidId, 'memo' => 'PPh 23 dipotong pelanggan', 'debit' => $inv['pph_amount'], 'credit' => 0];
        }

        foreach ($lines as $l) {
            $rawLines[] = [
                'account_id' => $l['account_id'],
                'job_id'     => $l['job_id'],
                'memo'       => $l['description'],
                'debit'      => 0,
                'credit'     => $l['amount'],
            ];
        }

        if ((float) $inv['ppn_amount'] > 0) {
            $ppnId = $this->accountId('ppnOutputCode');
            if ($ppnId === null) {
                return ['ok' => false, 'errors' => ['Set the PPN Keluaran account in Settings.']];
            }
            $rawLines[] = ['account_id' => $ppnId, 'memo' => 'PPN Keluaran', 'debit' => 0, 'credit' => $inv['ppn_amount']];
        }

        $save = $this->journalPoster->save([
            'entry_date'    => $inv['invoice_date'],
            'reference'     => $inv['customer_ref'] ?: $inv['internal_no'],
            'description'   => 'Penjualan ' . $inv['internal_no'] . ($inv['description'] ? ' - ' . $inv['description'] : ''),
            'source'        => 'sales',
            'currency_id'   => $inv['currency_id'],
            'exchange_rate' => $inv['exchange_rate'],
        ], $rawLines);
        if (! $save['ok']) {
            return ['ok' => false, 'errors' => $save['errors']];
        }
        $post = $this->journalPoster->post($save['id']);
        if (! $post['ok']) {
            $this->journalPoster->deleteDraft($save['id']);

            return ['ok' => false, 'errors' => $post['errors']];
        }

        $this->invoices->update($id, [
            'status'     => 'posted',
            'journal_id' => $save['id'],
            'total_base' => round((float) $inv['total'] * (float) $inv['exchange_rate'], 2),
            'posted_by'  => auth()->id(),
            'posted_at'  => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true];
    }

    public function voidInvoice(int $id, string $reason): array
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return ['ok' => false, 'errors' => ['Invoice not found.']];
        }
        if ($inv['status'] === 'draft') {
            return ['ok' => false, 'errors' => ['Delete the draft instead.']];
        }
        if ($inv['status'] === 'void') {
            return ['ok' => false, 'errors' => ['Already void.']];
        }
        if ((float) $inv['received_base'] > 0.005) {
            return ['ok' => false, 'errors' => ['Void the receipts against this invoice first.']];
        }
        if ($inv['journal_id']) {
            $r = $this->journalPoster->void((int) $inv['journal_id'], 'Void sales invoice ' . $inv['internal_no'] . ' - ' . $reason);
            if (! $r['ok']) {
                return $r;
            }
        }
        $this->invoices->update($id, ['status' => 'void']);

        return ['ok' => true];
    }

    public function deleteDraft(int $id): array
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return ['ok' => false, 'errors' => ['Invoice not found.']];
        }
        if ($inv['status'] !== 'draft') {
            return ['ok' => false, 'errors' => ['Only draft invoices can be deleted.']];
        }
        $this->invoices->delete($id);

        return ['ok' => true];
    }

    /**
     * Return a posted invoice to draft: hard-delete its ledger journal (no
     * reversing entry) so the invoice can be edited. Refuses when receipts are
     * applied, the period is closed, or the journal is otherwise entangled.
     *
     * @return array{ok: bool, errors?: list<string>}
     */
    public function unpostInvoice(int $id): array
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return ['ok' => false, 'errors' => ['Invoice not found.']];
        }
        if ($inv['status'] === 'draft') {
            return ['ok' => true];
        }
        if ($inv['status'] === 'void') {
            return ['ok' => false, 'errors' => ['This invoice is void; its reversal is already booked.']];
        }
        if ((float) $inv['received_base'] > 0.005) {
            return ['ok' => false, 'errors' => ['Void the receipts against this invoice first.']];
        }

        if (! empty($inv['journal_id'])) {
            $r = $this->journalPoster->deletePosted((int) $inv['journal_id']);
            if (! $r['ok']) {
                return $r;
            }
        }
        $this->invoices->update($id, [
            'status'     => 'draft',
            'journal_id' => null,
            'posted_by'  => null,
            'posted_at'  => null,
        ]);

        return ['ok' => true];
    }

    /**
     * Edit a POSTED invoice: within one transaction un-post it, re-save the
     * header + lines, and re-post. Any failure rolls the whole thing back so the
     * invoice is left exactly as it was.
     *
     * @param array<string,mixed>            $header
     * @param array<int,array<string,mixed>> $rawLines
     *
     * @return array{ok: bool, id?: int, errors?: list<string>}
     */
    public function reviseInvoice(int $id, array $header, array $rawLines): array
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return ['ok' => false, 'errors' => ['Invoice not found.']];
        }
        if ($inv['status'] === 'draft') {
            return $this->saveInvoice($header, $rawLines, $id);
        }
        if ($inv['status'] !== 'posted') {
            return ['ok' => false, 'errors' => ['Only draft or posted invoices can be edited.']];
        }

        $db = db_connect();
        $db->transBegin();

        $un = $this->unpostInvoice($id);
        if (! $un['ok']) {
            $db->transRollback();

            return $un;
        }
        $save = $this->saveInvoice($header, $rawLines, $id);
        if (! $save['ok']) {
            $db->transRollback();

            return $save;
        }
        $post = $this->postInvoice($id);
        if (! $post['ok']) {
            $db->transRollback();

            return $post;
        }

        $db->transCommit();

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Delete an invoice at any lifecycle stage that still allows it: drafts
     * outright, posted-and-unpaid by un-posting first. Paid / void invoices are
     * refused with guidance.
     *
     * @return array{ok: bool, errors?: list<string>}
     */
    public function deleteInvoice(int $id): array
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return ['ok' => false, 'errors' => ['Invoice not found.']];
        }
        if ($inv['status'] === 'void') {
            return ['ok' => false, 'errors' => ['This invoice is void - it is kept for the audit trail.']];
        }
        if ($inv['status'] !== 'draft') {
            $un = $this->unpostInvoice($id);
            if (! $un['ok']) {
                return $un;
            }
        }
        $this->invoices->delete($id);

        return ['ok' => true];
    }

    // ---------------------------------------------------------------- receipt

    /**
     * Record a customer receipt. The receipt has one currency; it may only be
     * allocated to invoices of that same currency. A/R is cleared at each
     * invoice's own rate, cash moves at the receipt rate, and the difference is
     * booked to the realized-FX account for the currency.
     *
     * @param array<string,mixed> $header      customer_id, receipt_date, bank_account_id,
     *                                          reference, currency_id, exchange_rate
     * @param array<int,float|string> $allocations  invoice_id => amount (receipt currency)
     *
     * @return array{ok:bool,id?:int,errors?:list<string>}
     */
    public function saveReceipt(array $header, array $allocations): array
    {
        $num = static fn ($v) => round((float) str_replace([',', ' '], '', (string) $v), 2);

        $allocations = array_filter(array_map($num, $allocations), static fn ($v) => $v > 0.005);
        if (! $allocations) {
            return ['ok' => false, 'errors' => ['Allocate the receipt to at least one invoice.']];
        }

        $ccyId = (int) ($header['currency_id'] ?: $this->currencies->baseId());
        $ccy   = $this->currencies->find($ccyId);
        if (! $ccy) {
            return ['ok' => false, 'errors' => ['Unknown receipt currency.']];
        }
        $isBase = $this->currencies->isBase($ccyId);
        $rate   = $isBase ? 1.0 : max(0.0, (float) ($header['exchange_rate'] ?? 0));
        if (! $isBase && $rate <= 0) {
            return ['ok' => false, 'errors' => ['Enter the exchange rate for ' . $ccy['code'] . '.']];
        }

        $bank = $this->accounts->find((int) $header['bank_account_id']);
        if (! $bank || (int) $bank['is_cash'] !== 1) {
            return ['ok' => false, 'errors' => ['Choose a cash / bank account to receive into.']];
        }
        $bankCcy = $bank['currency_id'] ? (int) $bank['currency_id'] : $this->currencies->baseId();
        if ($bankCcy !== $ccyId) {
            return ['ok' => false, 'errors' => [$bank['name'] . ' is a ' . $this->ccyCode($bankCcy) . ' account — choose one in ' . $ccy['code'] . '.']];
        }

        $arId = ControlAccounts::id('ar', $ccyId);
        if ($arId === null) {
            return ['ok' => false, 'errors' => ['No Trade A/R account is set for ' . $ccy['code'] . ' — see Setup → Control Accounts.']];
        }
        if ($this->periods->isDateLocked($header['receipt_date'])) {
            return ['ok' => false, 'errors' => ['The period for ' . $header['receipt_date'] . ' is closed.']];
        }

        $customerId = (int) $header['customer_id'];
        $rows       = [];
        $fx         = 0.0;
        $bankBase   = 0.0;
        $arBaseTot  = 0.0;
        $totalTxn   = 0.0;

        foreach ($allocations as $invId => $txn) {
            $inv = $this->invoices->find((int) $invId);
            if (! $inv || (int) $inv['customer_id'] !== $customerId || ! in_array($inv['status'], ['posted', 'partial'], true)) {
                return ['ok' => false, 'errors' => ['Invoice ' . $invId . ' is not receivable for this customer.']];
            }
            if ((int) $inv['currency_id'] !== $ccyId) {
                return ['ok' => false, 'errors' => ['Invoice ' . $inv['internal_no'] . ' is in ' . $this->ccyCode((int) $inv['currency_id']) . ', not ' . $ccy['code'] . ' — settle it with a separate receipt.']];
            }
            if (strtotime((string) $header['receipt_date']) < strtotime((string) $inv['invoice_date'])) {
                return ['ok' => false, 'errors' => ['Receipt date is before invoice ' . $inv['internal_no'] . ' (' . $inv['invoice_date'] . '). Hold it as a customer deposit instead.']];
            }
            $outstanding = round((float) $inv['total'] - (float) $inv['received'], 2);
            if ($txn - $outstanding > 0.01) {
                return ['ok' => false, 'errors' => ['Allocation for ' . $inv['internal_no'] . ' exceeds its outstanding ' . number_format($outstanding, 2) . ' ' . $ccy['code'] . '.']];
            }

            $arBase   = round($txn * (float) $inv['exchange_rate'], 2);
            $cashBase = round($txn * $rate, 2);
            $fx        += $cashBase - $arBase;
            $bankBase  += $cashBase;
            $arBaseTot += $arBase;
            $totalTxn  += $txn;
            $rows[]     = ['invoice_id' => (int) $invId, 'amount' => $txn, 'amount_base' => $arBase];
        }
        $fx        = round($fx, 2);
        $bankBase  = round($bankBase, 2);
        $arBaseTot = round($arBaseTot, 2);
        $totalTxn  = round($totalTxn, 2);

        $fxId = null;
        if (abs($fx) >= 0.005) {
            $fxId = ControlAccounts::id('fx', $ccyId);
            if ($fxId === null) {
                return ['ok' => false, 'errors' => ['No realized FX account is set for ' . $ccy['code'] . ' — see Setup → Control Accounts.']];
            }
        }

        $db = db_connect();
        $db->transStart();

        $receiptNo = $this->receipts->nextNo();
        $receiptId = (int) $this->receipts->insert([
            'receipt_no'      => $receiptNo,
            'customer_id'     => $customerId,
            'receipt_date'    => $header['receipt_date'],
            'bank_account_id' => (int) $header['bank_account_id'],
            'currency_id'     => $ccyId,
            'exchange_rate'   => $rate,
            'amount'          => $totalTxn,
            'amount_base'     => $bankBase,
            'reference'       => trim((string) ($header['reference'] ?? '')) ?: null,
            'status'          => 'posted',
            'created_by'      => auth()->id(),
        ], true);

        foreach ($rows as $r) {
            $db->table('sales_receipt_allocations')->insert([
                'receipt_id' => $receiptId, 'invoice_id' => $r['invoice_id'],
                'amount' => $r['amount'], 'amount_base' => $r['amount_base'],
            ]);
        }

        $jLines = [
            ['account_id' => (int) $header['bank_account_id'], 'debit' => $totalTxn, 'credit' => 0, 'debit_base' => $bankBase, 'memo' => 'Penerimaan ' . $receiptNo],
            ['account_id' => $arId, 'debit' => 0, 'credit' => $totalTxn, 'credit_base' => $arBaseTot, 'customer_id' => $customerId, 'memo' => 'Pelunasan piutang'],
        ];
        if ($fxId !== null) {
            $jLines[] = $fx > 0
                ? ['account_id' => $fxId, 'debit' => 0, 'credit' => 0, 'credit_base' => $fx, 'memo' => 'Laba selisih kurs realisasi']
                : ['account_id' => $fxId, 'debit' => 0, 'credit' => 0, 'debit_base' => -$fx, 'memo' => 'Rugi selisih kurs realisasi'];
        }

        $save = $this->journalPoster->save([
            'entry_date'    => $header['receipt_date'],
            'reference'     => trim((string) ($header['reference'] ?? '')) ?: $receiptNo,
            'description'   => 'Penerimaan pelanggan ' . $receiptNo,
            'source'        => 'cash_receipt',
            'currency_id'   => $ccyId,
            'exchange_rate' => $rate,
        ], $jLines);
        if (! $save['ok']) {
            $db->transComplete();

            return ['ok' => false, 'errors' => $save['errors']];
        }
        $post = $this->journalPoster->post($save['id']);
        if (! $post['ok']) {
            $db->transComplete();

            return ['ok' => false, 'errors' => $post['errors']];
        }

        $this->receipts->update($receiptId, ['journal_id' => $save['id']]);
        foreach ($rows as $r) {
            $this->applyToInvoice($r['invoice_id'], $r['amount'], $r['amount_base']);
        }

        $db->transComplete();

        return $db->transStatus() === false
            ? ['ok' => false, 'errors' => ['Database error while saving the receipt.']]
            : ['ok' => true, 'id' => $receiptId];
    }

    public function voidReceipt(int $id, string $reason): array
    {
        $rc = $this->receipts->find($id);
        if (! $rc) {
            return ['ok' => false, 'errors' => ['Receipt not found.']];
        }
        if (($rc['kind'] ?? 'settlement') === 'deposit') {
            return $this->voidDeposit($id, $reason);
        }
        if ($rc['status'] === 'void') {
            return ['ok' => false, 'errors' => ['Already void.']];
        }

        $db     = db_connect();
        $allocs = $db->table('sales_receipt_allocations')->where('receipt_id', $id)->get()->getResultArray();

        $db->transStart();
        if ($rc['journal_id']) {
            $r = $this->journalPoster->void((int) $rc['journal_id'], 'Void customer receipt ' . $rc['receipt_no'] . ' - ' . $reason);
            if (! $r['ok']) {
                $db->transComplete();

                return $r;
            }
        }
        foreach ($allocs as $a) {
            $this->applyToInvoice((int) $a['invoice_id'], -(float) $a['amount'], -(float) $a['amount_base']);
        }
        $this->receipts->update($id, ['status' => 'void']);
        $db->transComplete();

        return ['ok' => true];
    }

    // ---------------------------------------------------------------- customer down payment

    /**
     * Record an advance received from a customer (no invoice yet).
     * Books  Dr Bank / Cr Customer Down Payment  and holds the amount as
     * "unapplied" until it is applied to invoices.
     *
     * @param array<string,mixed> $header  customer_id, receipt_date, currency_id,
     *                                     exchange_rate, bank_account_id, amount, reference
     *
     * @return array{ok:bool,id?:int,errors?:list<string>}
     */
    public function saveDeposit(array $header): array
    {
        $ccyId = (int) ($header['currency_id'] ?: $this->currencies->baseId());
        $ccy   = $this->currencies->find($ccyId);
        if (! $ccy) {
            return ['ok' => false, 'errors' => ['Unknown currency.']];
        }
        $isBase = $this->currencies->isBase($ccyId);
        $rate   = $isBase ? 1.0 : max(0.0, (float) ($header['exchange_rate'] ?? 0));
        if (! $isBase && $rate <= 0) {
            return ['ok' => false, 'errors' => ['Enter the exchange rate for ' . $ccy['code'] . '.']];
        }
        $amount = round((float) str_replace([',', ' '], '', (string) ($header['amount'] ?? 0)), 2);
        if ($amount <= 0) {
            return ['ok' => false, 'errors' => ['Enter the deposit amount.']];
        }
        $header['receipt_date'] = trim((string) ($header['receipt_date'] ?? ''));
        if ($header['receipt_date'] === '' || ! strtotime($header['receipt_date'])) {
            return ['ok' => false, 'errors' => ['Enter a valid deposit date.']];
        }
        if ((int) ($header['customer_id'] ?? 0) <= 0) {
            return ['ok' => false, 'errors' => ['Choose a customer.']];
        }

        $bank = $this->accounts->find((int) $header['bank_account_id']);
        if (! $bank || (int) $bank['is_cash'] !== 1) {
            return ['ok' => false, 'errors' => ['Choose a cash / bank account to receive into.']];
        }
        $bankCcy = $bank['currency_id'] ? (int) $bank['currency_id'] : $this->currencies->baseId();
        if ($bankCcy !== $ccyId) {
            return ['ok' => false, 'errors' => [$bank['name'] . ' is a ' . $this->ccyCode($bankCcy) . ' account — choose one in ' . $ccy['code'] . '.']];
        }
        $depAcct = ControlAccounts::id('cust_deposit', $ccyId);
        if ($depAcct === null) {
            return ['ok' => false, 'errors' => ['No Customer Down Payment account is set for ' . $ccy['code'] . ' — see Setup → Control Accounts.']];
        }
        if ($this->periods->isDateLocked($header['receipt_date'])) {
            return ['ok' => false, 'errors' => ['The period for ' . $header['receipt_date'] . ' is closed.']];
        }

        $customerId = (int) $header['customer_id'];
        $amountBase = round($amount * $rate, 2);

        $db = db_connect();
        $db->transStart();

        $no  = $this->receipts->nextNo();
        $rid = (int) $this->receipts->insert([
            'receipt_no'      => $no,
            'customer_id'     => $customerId,
            'receipt_date'    => $header['receipt_date'],
            'bank_account_id' => (int) $header['bank_account_id'],
            'currency_id'     => $ccyId,
            'exchange_rate'   => $rate,
            'amount'          => $amount,
            'amount_base'     => $amountBase,
            'unapplied'       => $amount,
            'unapplied_base'  => $amountBase,
            'kind'            => 'deposit',
            'reference'       => trim((string) ($header['reference'] ?? '')) ?: null,
            'status'          => 'posted',
            'created_by'      => auth()->id(),
        ], true);

        $save = $this->journalPoster->save([
            'entry_date'    => $header['receipt_date'],
            'reference'     => trim((string) ($header['reference'] ?? '')) ?: $no,
            'description'   => 'Uang muka pelanggan ' . $no,
            'source'        => 'cash_receipt',
            'currency_id'   => $ccyId,
            'exchange_rate' => $rate,
        ], [
            ['account_id' => (int) $header['bank_account_id'], 'debit' => $amount, 'credit' => 0, 'debit_base' => $amountBase, 'memo' => 'Penerimaan uang muka ' . $no],
            ['account_id' => $depAcct, 'debit' => 0, 'credit' => $amount, 'credit_base' => $amountBase, 'customer_id' => $customerId, 'memo' => 'Uang muka ' . $no],
        ]);
        if (! $save['ok']) {
            $db->transComplete();

            return ['ok' => false, 'errors' => $save['errors']];
        }
        $post = $this->journalPoster->post($save['id']);
        if (! $post['ok']) {
            $db->transComplete();

            return ['ok' => false, 'errors' => $post['errors']];
        }
        $this->receipts->update($rid, ['journal_id' => $save['id']]);

        $db->transComplete();

        return $db->transStatus() === false
            ? ['ok' => false, 'errors' => ['Database error while saving the deposit.']]
            : ['ok' => true, 'id' => $rid];
    }

    /**
     * Apply an unapplied deposit to open invoices (one reclass journal, no cash):
     *   Dr Customer Down Payment (at the deposit's rate)
     *   Cr Trade A/R             (at each invoice's rate)
     *   +/- realized FX
     *
     * @param array<int,float|string> $allocations  invoice_id => amount (deposit currency)
     *
     * @return array{ok:bool,errors?:list<string>}
     */
    public function applyDeposit(int $depositId, array $allocations, ?string $applyDate = null): array
    {
        $dep = $this->receipts->find($depositId);
        if (! $dep || ($dep['kind'] ?? '') !== 'deposit') {
            return ['ok' => false, 'errors' => ['Deposit not found.']];
        }
        if ($dep['status'] !== 'posted') {
            return ['ok' => false, 'errors' => ['This deposit is not active.']];
        }

        $num         = static fn ($v) => round((float) str_replace([',', ' '], '', (string) $v), 2);
        $allocations = array_filter(array_map($num, $allocations), static fn ($v) => $v > 0.005);
        if (! $allocations) {
            return ['ok' => false, 'errors' => ['Allocate the deposit to at least one invoice.']];
        }

        $ccyId   = (int) $dep['currency_id'];
        $depRate = (float) $dep['exchange_rate'];
        $date    = $applyDate ?: date('Y-m-d');
        $ccyCode = $this->ccyCode($ccyId);

        if ($this->periods->isDateLocked($date)) {
            return ['ok' => false, 'errors' => ['The period for ' . $date . ' is closed.']];
        }
        if (strtotime($date) < strtotime((string) $dep['receipt_date'])) {
            return ['ok' => false, 'errors' => ['The apply date is before the deposit date.']];
        }

        $totalTxn = round(array_sum($allocations), 2);
        if ($totalTxn - (float) $dep['unapplied'] > 0.01) {
            return ['ok' => false, 'errors' => ['That is more than the ' . number_format((float) $dep['unapplied'], 2) . ' ' . $ccyCode . ' still unapplied on this deposit.']];
        }

        $depAcct = ControlAccounts::id('cust_deposit', $ccyId);
        $arAcct  = ControlAccounts::id('ar', $ccyId);
        if ($depAcct === null || $arAcct === null) {
            return ['ok' => false, 'errors' => ['Control accounts for ' . $ccyCode . ' are not fully set — see Setup → Control Accounts.']];
        }

        $rows      = [];
        $fx        = 0.0;
        $arBaseTot = 0.0;
        $depBaseTot = 0.0;
        foreach ($allocations as $invId => $txn) {
            $inv = $this->invoices->find((int) $invId);
            if (! $inv || (int) $inv['customer_id'] !== (int) $dep['customer_id'] || ! in_array($inv['status'], ['posted', 'partial'], true)) {
                return ['ok' => false, 'errors' => ['Invoice ' . $invId . ' is not open for this customer.']];
            }
            if ((int) $inv['currency_id'] !== $ccyId) {
                return ['ok' => false, 'errors' => ['Invoice ' . $inv['internal_no'] . ' is in ' . $this->ccyCode((int) $inv['currency_id']) . ', not ' . $ccyCode . '.']];
            }
            if (strtotime($date) < strtotime((string) $inv['invoice_date'])) {
                return ['ok' => false, 'errors' => ['Apply date is before invoice ' . $inv['internal_no'] . ' (' . $inv['invoice_date'] . ').']];
            }
            $outstanding = round((float) $inv['total'] - (float) $inv['received'], 2);
            if ($txn - $outstanding > 0.01) {
                return ['ok' => false, 'errors' => ['Allocation for ' . $inv['internal_no'] . ' exceeds its outstanding ' . number_format($outstanding, 2) . ' ' . $ccyCode . '.']];
            }

            $arBase  = round($txn * (float) $inv['exchange_rate'], 2);
            $depBase = round($txn * $depRate, 2);
            $fx        += $depBase - $arBase;
            $arBaseTot += $arBase;
            $depBaseTot += $depBase;
            $rows[]     = ['invoice_id' => (int) $invId, 'amount' => $txn, 'amount_base' => $arBase, 'dep_base' => $depBase];
        }
        $fx         = round($fx, 2);
        $arBaseTot  = round($arBaseTot, 2);
        $depBaseTot = round($depBaseTot, 2);

        $fxAcct = null;
        if (abs($fx) >= 0.005) {
            $fxAcct = ControlAccounts::id('fx', $ccyId);
            if ($fxAcct === null) {
                return ['ok' => false, 'errors' => ['No realized FX account is set for ' . $ccyCode . '.']];
            }
        }

        $db = db_connect();
        $db->transStart();

        $jLines = [
            ['account_id' => $depAcct, 'debit' => $totalTxn, 'credit' => 0, 'debit_base' => $depBaseTot, 'customer_id' => (int) $dep['customer_id'], 'memo' => 'Alokasi uang muka ' . $dep['receipt_no']],
            ['account_id' => $arAcct, 'debit' => 0, 'credit' => $totalTxn, 'credit_base' => $arBaseTot, 'customer_id' => (int) $dep['customer_id'], 'memo' => 'Pelunasan piutang dari uang muka'],
        ];
        if ($fxAcct !== null) {
            $jLines[] = $fx > 0
                ? ['account_id' => $fxAcct, 'debit' => 0, 'credit' => 0, 'credit_base' => $fx, 'memo' => 'Laba selisih kurs realisasi']
                : ['account_id' => $fxAcct, 'debit' => 0, 'credit' => 0, 'debit_base' => -$fx, 'memo' => 'Rugi selisih kurs realisasi'];
        }

        $save = $this->journalPoster->save([
            'entry_date'    => $date,
            'reference'     => $dep['receipt_no'],
            'description'   => 'Alokasi uang muka pelanggan ' . $dep['receipt_no'],
            'source'        => 'cash_receipt',
            'currency_id'   => $ccyId,
            'exchange_rate' => $depRate,
        ], $jLines);
        if (! $save['ok']) {
            $db->transComplete();

            return ['ok' => false, 'errors' => $save['errors']];
        }
        $post = $this->journalPoster->post($save['id']);
        if (! $post['ok']) {
            $db->transComplete();

            return ['ok' => false, 'errors' => $post['errors']];
        }

        foreach ($rows as $r) {
            $db->table('sales_receipt_allocations')->insert([
                'receipt_id' => $depositId, 'invoice_id' => $r['invoice_id'],
                'amount' => $r['amount'], 'amount_base' => $r['amount_base'], 'journal_id' => (int) $save['id'],
            ]);
            $this->applyToInvoice($r['invoice_id'], $r['amount'], $r['amount_base']);
        }
        $this->receipts->update($depositId, [
            'unapplied'      => round((float) $dep['unapplied'] - $totalTxn, 2),
            'unapplied_base' => round((float) $dep['unapplied_base'] - $depBaseTot, 2),
        ]);

        $db->transComplete();

        return $db->transStatus() === false
            ? ['ok' => false, 'errors' => ['Database error while applying the deposit.']]
            : ['ok' => true];
    }

    public function voidDeposit(int $depositId, string $reason): array
    {
        $dep = $this->receipts->find($depositId);
        if (! $dep || ($dep['kind'] ?? '') !== 'deposit') {
            return ['ok' => false, 'errors' => ['Deposit not found.']];
        }
        if ($dep['status'] === 'void') {
            return ['ok' => false, 'errors' => ['Already void.']];
        }
        if (round((float) $dep['unapplied'] - (float) $dep['amount'], 2) < -0.01) {
            return ['ok' => false, 'errors' => ['This deposit has been applied to invoices — reverse those applications first.']];
        }

        $db = db_connect();
        $db->transStart();
        if ($dep['journal_id']) {
            $r = $this->journalPoster->void((int) $dep['journal_id'], 'Void customer deposit ' . $dep['receipt_no'] . ' - ' . $reason);
            if (! $r['ok']) {
                $db->transComplete();

                return $r;
            }
        }
        $this->receipts->update($depositId, ['status' => 'void', 'unapplied' => 0, 'unapplied_base' => 0]);
        $db->transComplete();

        return ['ok' => true];
    }

    /**
     * Reverse one "apply deposit" action (identified by its journal), restoring
     * the deposit's unapplied balance and the invoices' outstanding.
     */
    public function unapplyDeposit(int $depositId, int $journalId, string $reason): array
    {
        $dep = $this->receipts->find($depositId);
        if (! $dep || ($dep['kind'] ?? '') !== 'deposit') {
            return ['ok' => false, 'errors' => ['Deposit not found.']];
        }
        $db     = db_connect();
        $allocs = $db->table('sales_receipt_allocations')
            ->where('receipt_id', $depositId)->where('journal_id', $journalId)->get()->getResultArray();
        if (! $allocs) {
            return ['ok' => false, 'errors' => ['That application was not found.']];
        }

        $db->transStart();
        $r = $this->journalPoster->void($journalId, 'Reverse deposit allocation ' . $dep['receipt_no'] . ' - ' . $reason);
        if (! $r['ok']) {
            $db->transComplete();

            return $r;
        }
        $txnBack = 0.0;
        foreach ($allocs as $a) {
            $this->applyToInvoice((int) $a['invoice_id'], -(float) $a['amount'], -(float) $a['amount_base']);
            $txnBack += (float) $a['amount'];
        }
        $db->table('sales_receipt_allocations')->where('receipt_id', $depositId)->where('journal_id', $journalId)->delete();

        $this->receipts->update($depositId, [
            'unapplied'      => round((float) $dep['unapplied'] + $txnBack, 2),
            'unapplied_base' => round((float) $dep['unapplied_base'] + $txnBack * (float) $dep['exchange_rate'], 2),
        ]);
        $db->transComplete();

        return ['ok' => true];
    }

    private function applyToInvoice(int $invoiceId, float $deltaTxn, float $deltaBase): void
    {
        $inv = $this->invoices->find($invoiceId);
        if (! $inv) {
            return;
        }
        $recv     = max(round((float) $inv['received'] + $deltaTxn, 2), 0.0);
        $recvBase = max(round((float) $inv['received_base'] + $deltaBase, 2), 0.0);
        $status   = $inv['status'];
        if ($status !== 'void') {
            $status = $recv <= 0.005 ? 'posted' : ($recv + 0.01 >= (float) $inv['total'] ? 'paid' : 'partial');
        }
        $this->invoices->update($invoiceId, ['received' => $recv, 'received_base' => $recvBase, 'status' => $status]);
    }
}
