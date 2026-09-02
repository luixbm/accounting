<?php

namespace App\Libraries\Accounting;

use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\FiscalPeriodModel;
use App\Models\PurchaseInvoiceLineModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchasePaymentModel;
use Config\Accounting;

/**
 * Purchase invoices and supplier payments. Both post through JournalPoster so
 * the ledger, period locks and multi-currency handling stay in one place.
 *
 *   Invoice posting:  Dr expense/asset lines + Dr PPN Masukan
 *                     Cr PPh 23 payable (withheld) + Cr Hutang Usaha (net)
 *   Payment posting:  Dr Hutang Usaha            Cr Bank
 */
class PurchasePoster
{
    private PurchaseInvoiceModel $invoices;
    private PurchaseInvoiceLineModel $lines;
    private PurchasePaymentModel $payments;
    private AccountModel $accounts;
    private CurrencyModel $currencies;
    private FiscalPeriodModel $periods;
    private JournalPoster $journalPoster;

    public function __construct()
    {
        $this->invoices      = model(PurchaseInvoiceModel::class);
        $this->lines         = model(PurchaseInvoiceLineModel::class);
        $this->payments      = model(PurchasePaymentModel::class);
        $this->accounts      = model(AccountModel::class);
        $this->currencies    = model(CurrencyModel::class);
        $this->periods       = model(FiscalPeriodModel::class);
        $this->journalPoster = new JournalPoster();
    }

    private function code(string $key): ?string
    {
        $v = acc_setting($key);

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    private function accountId(string $settingKey): ?int
    {
        $code = $this->code($settingKey);
        if ($code === null) {
            return null;
        }
        $a = $this->accounts->byCode($code);

        return $a ? (int) $a['id'] : null;
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

    // ---------------------------------------------------------------- invoice

    /**
     * @param array<string,mixed>            $header
     * @param array<int,array<string,mixed>> $rawLines
     *
     * @return array{ok:bool,id?:int,errors?:list<string>}
     */
    public function saveInvoice(array $header, array $rawLines, ?int $id = null): array
    {
        $currency = $this->currencies->find((int) $header['currency_id']);
        if (! $currency) {
            return ['ok' => false, 'errors' => ['Unknown currency.']];
        }
        $rate = (int) $currency['is_base'] === 1 ? 1.0 : max(0.0, (float) ($header['exchange_rate'] ?? 1));

        $clean   = [];
        $no      = 1;
        $subtotal = 0.0;
        foreach ($rawLines as $l) {
            $accId  = (int) ($l['account_id'] ?? 0);
            $amount = round((float) str_replace([',', ' '], '', (string) ($l['amount'] ?? 0)), 2);
            // keep zero-amount lines that have an account (Jambix bookings with no
            // budget yet); they simply don't reach the journal until priced.
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
                'budget_amount' => isset($l['budget_amount']) && $l['budget_amount'] !== null && $l['budget_amount'] !== ''
                    ? round((float) str_replace([',', ' '], '', (string) $l['budget_amount']), 2)
                    : $amount,
                'cost_remark'  => trim((string) ($l['cost_remark'] ?? '')) ?: null,
                'booking_ref'  => trim((string) ($l['booking_ref'] ?? '')) ?: null,
                'service_date' => $this->normDate($l['service_date'] ?? null),
                'party_name'   => trim((string) ($l['party_name'] ?? '')) ?: null,
                'units'        => trim((string) ($l['units'] ?? '')) ?: null,
                'nights'       => trim((string) ($l['nights'] ?? '')) ?: null,
                'cost_source'  => in_array($l['cost_source'] ?? '', ['manual', 'budget', 'actual'], true) ? $l['cost_source'] : 'manual',
                'supp_inv_ref' => trim((string) ($l['supp_inv_ref'] ?? '')) ?: null,
                'supp_inv_date' => $this->normDate($l['supp_inv_date'] ?? null),
            ];
            $subtotal += $amount;
        }
        if (! $clean) {
            return ['ok' => false, 'errors' => ['Add at least one line with an amount.']];
        }

        $ppn = round((float) str_replace([',', ' '], '', (string) ($header['ppn_amount'] ?? 0)), 2);
        $pph = round((float) str_replace([',', ' '], '', (string) ($header['pph_amount'] ?? 0)), 2);
        $subtotal = round($subtotal, 2);
        $total    = round($subtotal + $ppn - $pph, 2);

        $data = [
            'supplier_ref'  => trim((string) ($header['supplier_ref'] ?? '')) ?: null,
            'supplier_id'   => (int) $header['supplier_id'],
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

    /**
     * @return array{ok:bool,errors?:list<string>}
     */
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

        $apId = ControlAccounts::id('ap', (int) $inv['currency_id']);
        if ($apId === null) {
            return ['ok' => false, 'errors' => ['No Trade A/P account is set for ' . $this->ccyCode((int) $inv['currency_id']) . ' — see Setup → Control Accounts.']];
        }

        $lines    = $this->lines->where('invoice_id', $id)->orderBy('line_no')->findAll();
        $rawLines = [];
        foreach ($lines as $l) {
            $rawLines[] = [
                'account_id' => $l['account_id'],
                'job_id'     => $l['job_id'],
                'memo'       => $l['description'],
                'debit'      => $l['amount'],
                'credit'     => 0,
            ];
        }

        if ((float) $inv['ppn_amount'] > 0) {
            $ppnId = $this->accountId('ppnInputCode');
            if ($ppnId === null) {
                return ['ok' => false, 'errors' => ['Set the PPN Masukan account in Settings.']];
            }
            $rawLines[] = ['account_id' => $ppnId, 'memo' => 'PPN Masukan', 'debit' => $inv['ppn_amount'], 'credit' => 0];
        }
        if ((float) $inv['pph_amount'] > 0) {
            $pphId = $this->accountId('pph23PayableCode');
            if ($pphId === null) {
                return ['ok' => false, 'errors' => ['Set the PPh 23 payable account in Settings.']];
            }
            $rawLines[] = ['account_id' => $pphId, 'memo' => 'PPh 23 dipotong', 'debit' => 0, 'credit' => $inv['pph_amount']];
        }

        $rawLines[] = [
            'account_id'  => $apId,
            'memo'        => 'Hutang - ' . ($inv['supplier_ref'] ?: $inv['internal_no']),
            'debit'       => 0,
            'credit'      => $inv['total'],
            'supplier_id' => $inv['supplier_id'],
        ];

        $save = $this->journalPoster->save([
            'entry_date'    => $inv['invoice_date'],
            'reference'     => $inv['supplier_ref'] ?: $inv['internal_no'],
            'description'   => 'Pembelian ' . $inv['internal_no'] . ($inv['description'] ? ' - ' . $inv['description'] : ''),
            'source'        => 'purchase',
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
            // amount actually owed to the supplier (net of withholding), in base currency
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
        if ((float) $inv['paid_base'] > 0.005) {
            return ['ok' => false, 'errors' => ['Void the payments against this invoice first.']];
        }

        if ($inv['journal_id']) {
            $r = $this->journalPoster->void((int) $inv['journal_id'], 'Void purchase invoice ' . $inv['internal_no'] . ' - ' . $reason);
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
     * reversing entry) so the invoice can be edited. Refuses when payments are
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
        if ((float) $inv['paid_base'] > 0.005) {
            return ['ok' => false, 'errors' => ['Void the payments against this invoice first.']];
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

    // ---------------------------------------------------------------- payment

    /**
     * Record a supplier payment. The payment has one currency and may only be
     * allocated to invoices of that currency. A/P is cleared at each invoice's
     * own rate, cash moves at the payment rate, and the difference is booked to
     * the realized-FX account for the currency.
     *
     * @param array<string,mixed>     $header       supplier_id, payment_date, bank_account_id,
     *                                              reference, currency_id, exchange_rate
     * @param array<int,float|string> $allocations  invoice_id => amount (payment currency)
     *
     * @return array{ok:bool,id?:int,errors?:list<string>}
     */
    public function savePayment(array $header, array $allocations): array
    {
        $num = static fn ($v) => round((float) str_replace([',', ' '], '', (string) $v), 2);

        $allocations = array_filter(array_map($num, $allocations), static fn ($v) => $v > 0.005);
        if (! $allocations) {
            return ['ok' => false, 'errors' => ['Allocate the payment to at least one invoice.']];
        }

        $ccyId = (int) ($header['currency_id'] ?: $this->currencies->baseId());
        $ccy   = $this->currencies->find($ccyId);
        if (! $ccy) {
            return ['ok' => false, 'errors' => ['Unknown payment currency.']];
        }
        $isBase = $this->currencies->isBase($ccyId);
        $rate   = $isBase ? 1.0 : max(0.0, (float) ($header['exchange_rate'] ?? 0));
        if (! $isBase && $rate <= 0) {
            return ['ok' => false, 'errors' => ['Enter the exchange rate for ' . $ccy['code'] . '.']];
        }

        $bank = $this->accounts->find((int) $header['bank_account_id']);
        if (! $bank || (int) $bank['is_cash'] !== 1) {
            return ['ok' => false, 'errors' => ['Choose a cash / bank account to pay from.']];
        }
        $bankCcy = $bank['currency_id'] ? (int) $bank['currency_id'] : $this->currencies->baseId();
        if ($bankCcy !== $ccyId) {
            return ['ok' => false, 'errors' => [$bank['name'] . ' is a ' . $this->ccyCode($bankCcy) . ' account — choose one in ' . $ccy['code'] . '.']];
        }

        $apId = ControlAccounts::id('ap', $ccyId);
        if ($apId === null) {
            return ['ok' => false, 'errors' => ['No Trade A/P account is set for ' . $ccy['code'] . ' — see Setup → Control Accounts.']];
        }
        if ($this->periods->isDateLocked($header['payment_date'])) {
            return ['ok' => false, 'errors' => ['The period for ' . $header['payment_date'] . ' is closed.']];
        }

        $supplierId = (int) $header['supplier_id'];
        $rows       = [];
        $fx         = 0.0;
        $bankBase   = 0.0;
        $apBaseTot  = 0.0;
        $totalTxn   = 0.0;

        foreach ($allocations as $invId => $txn) {
            $inv = $this->invoices->find((int) $invId);
            if (! $inv || (int) $inv['supplier_id'] !== $supplierId || ! in_array($inv['status'], ['posted', 'partial'], true)) {
                return ['ok' => false, 'errors' => ['Invoice ' . $invId . ' is not payable for this supplier.']];
            }
            if ((int) $inv['currency_id'] !== $ccyId) {
                return ['ok' => false, 'errors' => ['Invoice ' . $inv['internal_no'] . ' is in ' . $this->ccyCode((int) $inv['currency_id']) . ', not ' . $ccy['code'] . ' — settle it with a separate payment.']];
            }
            if (strtotime((string) $header['payment_date']) < strtotime((string) $inv['invoice_date'])) {
                return ['ok' => false, 'errors' => ['Payment date is before invoice ' . $inv['internal_no'] . ' (' . $inv['invoice_date'] . '). Hold it as a supplier deposit instead.']];
            }
            $outstanding = round((float) $inv['total'] - (float) $inv['paid'], 2);
            if ($txn - $outstanding > 0.01) {
                return ['ok' => false, 'errors' => ['Allocation for ' . $inv['internal_no'] . ' exceeds its outstanding ' . number_format($outstanding, 2) . ' ' . $ccy['code'] . '.']];
            }

            $apBase   = round($txn * (float) $inv['exchange_rate'], 2);
            $cashBase = round($txn * $rate, 2);
            $fx        += $apBase - $cashBase; // + = gain (paid fewer base units than owed)
            $bankBase  += $cashBase;
            $apBaseTot += $apBase;
            $totalTxn  += $txn;
            $rows[]     = ['invoice_id' => (int) $invId, 'amount' => $txn, 'amount_base' => $apBase];
        }
        $fx        = round($fx, 2);
        $bankBase  = round($bankBase, 2);
        $apBaseTot = round($apBaseTot, 2);
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

        $paymentNo = $this->payments->nextNo();
        $paymentId = (int) $this->payments->insert([
            'payment_no'      => $paymentNo,
            'supplier_id'     => $supplierId,
            'payment_date'    => $header['payment_date'],
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
            $db->table('purchase_payment_allocations')->insert([
                'payment_id' => $paymentId, 'invoice_id' => $r['invoice_id'],
                'amount' => $r['amount'], 'amount_base' => $r['amount_base'],
            ]);
        }

        // Dr Hutang Usaha (invoice rate) / Cr Bank (payment rate) / realized FX plug
        $jLines = [
            ['account_id' => $apId, 'debit' => $totalTxn, 'credit' => 0, 'debit_base' => $apBaseTot, 'supplier_id' => $supplierId, 'memo' => 'Pelunasan hutang'],
            ['account_id' => (int) $header['bank_account_id'], 'debit' => 0, 'credit' => $totalTxn, 'credit_base' => $bankBase, 'memo' => 'Pembayaran ' . $paymentNo],
        ];
        if ($fxId !== null) {
            $jLines[] = $fx > 0
                ? ['account_id' => $fxId, 'debit' => 0, 'credit' => 0, 'credit_base' => $fx, 'memo' => 'Laba selisih kurs realisasi']
                : ['account_id' => $fxId, 'debit' => 0, 'credit' => 0, 'debit_base' => -$fx, 'memo' => 'Rugi selisih kurs realisasi'];
        }

        $save = $this->journalPoster->save([
            'entry_date'    => $header['payment_date'],
            'reference'     => trim((string) ($header['reference'] ?? '')) ?: $paymentNo,
            'description'   => 'Pembayaran supplier ' . $paymentNo,
            'source'        => 'cash_payment',
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

        $this->payments->update($paymentId, ['journal_id' => $save['id']]);
        foreach ($rows as $r) {
            $this->applyToInvoice($r['invoice_id'], $r['amount'], $r['amount_base']);
        }

        $db->transComplete();

        return $db->transStatus() === false
            ? ['ok' => false, 'errors' => ['Database error while saving the payment.']]
            : ['ok' => true, 'id' => $paymentId];
    }

    public function voidPayment(int $id, string $reason): array
    {
        $pay = $this->payments->find($id);
        if (! $pay) {
            return ['ok' => false, 'errors' => ['Payment not found.']];
        }
        if (($pay['kind'] ?? 'settlement') === 'deposit') {
            return $this->voidDeposit($id, $reason);
        }
        if ($pay['status'] === 'void') {
            return ['ok' => false, 'errors' => ['Already void.']];
        }

        $db     = db_connect();
        $allocs = $db->table('purchase_payment_allocations')->where('payment_id', $id)->get()->getResultArray();

        $db->transStart();
        if ($pay['journal_id']) {
            $r = $this->journalPoster->void((int) $pay['journal_id'], 'Void supplier payment ' . $pay['payment_no'] . ' - ' . $reason);
            if (! $r['ok']) {
                $db->transComplete();

                return $r;
            }
        }
        foreach ($allocs as $a) {
            $this->applyToInvoice((int) $a['invoice_id'], -(float) $a['amount'], -(float) $a['amount_base']);
        }
        $this->payments->update($id, ['status' => 'void']);
        $db->transComplete();

        return ['ok' => true];
    }

    // ---------------------------------------------------------------- supplier deposit

    /**
     * Record an advance paid to a supplier (no invoice yet):
     *   Dr Deposit paid to supplier / Cr Bank
     *
     * @param array<string,mixed> $header supplier_id, payment_date, currency_id,
     *                                    exchange_rate, bank_account_id, amount, reference
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
        $header['payment_date'] = trim((string) ($header['payment_date'] ?? ''));
        if ($header['payment_date'] === '' || ! strtotime($header['payment_date'])) {
            return ['ok' => false, 'errors' => ['Enter a valid deposit date.']];
        }
        if ((int) ($header['supplier_id'] ?? 0) <= 0) {
            return ['ok' => false, 'errors' => ['Choose a supplier.']];
        }

        $bank = $this->accounts->find((int) $header['bank_account_id']);
        if (! $bank || (int) $bank['is_cash'] !== 1) {
            return ['ok' => false, 'errors' => ['Choose a cash / bank account to pay from.']];
        }
        $bankCcy = $bank['currency_id'] ? (int) $bank['currency_id'] : $this->currencies->baseId();
        if ($bankCcy !== $ccyId) {
            return ['ok' => false, 'errors' => [$bank['name'] . ' is a ' . $this->ccyCode($bankCcy) . ' account — choose one in ' . $ccy['code'] . '.']];
        }
        $depAcct = ControlAccounts::id('supp_deposit', $ccyId);
        if ($depAcct === null) {
            return ['ok' => false, 'errors' => ['No Deposit paid to supplier account is set for ' . $ccy['code'] . ' — see Setup → Control Accounts.']];
        }
        if ($this->periods->isDateLocked($header['payment_date'])) {
            return ['ok' => false, 'errors' => ['The period for ' . $header['payment_date'] . ' is closed.']];
        }

        $supplierId = (int) $header['supplier_id'];
        $amountBase = round($amount * $rate, 2);

        $db = db_connect();
        $db->transStart();

        $no  = $this->payments->nextNo();
        $pid = (int) $this->payments->insert([
            'payment_no'      => $no,
            'supplier_id'     => $supplierId,
            'payment_date'    => $header['payment_date'],
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
            'entry_date'    => $header['payment_date'],
            'reference'     => trim((string) ($header['reference'] ?? '')) ?: $no,
            'description'   => 'Uang muka pembelian ' . $no,
            'source'        => 'cash_payment',
            'currency_id'   => $ccyId,
            'exchange_rate' => $rate,
        ], [
            ['account_id' => $depAcct, 'debit' => $amount, 'credit' => 0, 'debit_base' => $amountBase, 'supplier_id' => $supplierId, 'memo' => 'Uang muka ' . $no],
            ['account_id' => (int) $header['bank_account_id'], 'debit' => 0, 'credit' => $amount, 'credit_base' => $amountBase, 'memo' => 'Pembayaran uang muka ' . $no],
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
        $this->payments->update($pid, ['journal_id' => $save['id']]);

        $db->transComplete();

        return $db->transStatus() === false
            ? ['ok' => false, 'errors' => ['Database error while saving the deposit.']]
            : ['ok' => true, 'id' => $pid];
    }

    /**
     * Apply a supplier deposit to open invoices (one reclass journal, no cash):
     *   Dr Trade A/P (invoice rate) / Cr Deposit paid to supplier (deposit rate) / +/- FX
     *
     * @param array<int,float|string> $allocations invoice_id => amount (deposit currency)
     *
     * @return array{ok:bool,errors?:list<string>}
     */
    public function applyDeposit(int $depositId, array $allocations, ?string $applyDate = null): array
    {
        $dep = $this->payments->find($depositId);
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
        if (strtotime($date) < strtotime((string) $dep['payment_date'])) {
            return ['ok' => false, 'errors' => ['The apply date is before the deposit date.']];
        }

        $totalTxn = round(array_sum($allocations), 2);
        if ($totalTxn - (float) $dep['unapplied'] > 0.01) {
            return ['ok' => false, 'errors' => ['That is more than the ' . number_format((float) $dep['unapplied'], 2) . ' ' . $ccyCode . ' still unapplied on this deposit.']];
        }

        $depAcct = ControlAccounts::id('supp_deposit', $ccyId);
        $apAcct  = ControlAccounts::id('ap', $ccyId);
        if ($depAcct === null || $apAcct === null) {
            return ['ok' => false, 'errors' => ['Control accounts for ' . $ccyCode . ' are not fully set — see Setup → Control Accounts.']];
        }

        $rows       = [];
        $fx         = 0.0;
        $apBaseTot  = 0.0;
        $depBaseTot = 0.0;
        foreach ($allocations as $invId => $txn) {
            $inv = $this->invoices->find((int) $invId);
            if (! $inv || (int) $inv['supplier_id'] !== (int) $dep['supplier_id'] || ! in_array($inv['status'], ['posted', 'partial'], true)) {
                return ['ok' => false, 'errors' => ['Invoice ' . $invId . ' is not open for this supplier.']];
            }
            if ((int) $inv['currency_id'] !== $ccyId) {
                return ['ok' => false, 'errors' => ['Invoice ' . $inv['internal_no'] . ' is in ' . $this->ccyCode((int) $inv['currency_id']) . ', not ' . $ccyCode . '.']];
            }
            if (strtotime($date) < strtotime((string) $inv['invoice_date'])) {
                return ['ok' => false, 'errors' => ['Apply date is before invoice ' . $inv['internal_no'] . ' (' . $inv['invoice_date'] . ').']];
            }
            $outstanding = round((float) $inv['total'] - (float) $inv['paid'], 2);
            if ($txn - $outstanding > 0.01) {
                return ['ok' => false, 'errors' => ['Allocation for ' . $inv['internal_no'] . ' exceeds its outstanding ' . number_format($outstanding, 2) . ' ' . $ccyCode . '.']];
            }

            $apBase  = round($txn * (float) $inv['exchange_rate'], 2);
            $depBase = round($txn * $depRate, 2);
            $fx         += $apBase - $depBase;
            $apBaseTot  += $apBase;
            $depBaseTot += $depBase;
            $rows[]      = ['invoice_id' => (int) $invId, 'amount' => $txn, 'amount_base' => $apBase];
        }
        $fx         = round($fx, 2);
        $apBaseTot  = round($apBaseTot, 2);
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
            ['account_id' => $apAcct, 'debit' => $totalTxn, 'credit' => 0, 'debit_base' => $apBaseTot, 'supplier_id' => (int) $dep['supplier_id'], 'memo' => 'Pelunasan hutang dari uang muka'],
            ['account_id' => $depAcct, 'debit' => 0, 'credit' => $totalTxn, 'credit_base' => $depBaseTot, 'supplier_id' => (int) $dep['supplier_id'], 'memo' => 'Alokasi uang muka ' . $dep['payment_no']],
        ];
        if ($fxAcct !== null) {
            $jLines[] = $fx > 0
                ? ['account_id' => $fxAcct, 'debit' => 0, 'credit' => 0, 'credit_base' => $fx, 'memo' => 'Laba selisih kurs realisasi']
                : ['account_id' => $fxAcct, 'debit' => 0, 'credit' => 0, 'debit_base' => -$fx, 'memo' => 'Rugi selisih kurs realisasi'];
        }

        $save = $this->journalPoster->save([
            'entry_date'    => $date,
            'reference'     => $dep['payment_no'],
            'description'   => 'Alokasi uang muka pembelian ' . $dep['payment_no'],
            'source'        => 'cash_payment',
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
            $db->table('purchase_payment_allocations')->insert([
                'payment_id' => $depositId, 'invoice_id' => $r['invoice_id'],
                'amount' => $r['amount'], 'amount_base' => $r['amount_base'], 'journal_id' => (int) $save['id'],
            ]);
            $this->applyToInvoice($r['invoice_id'], $r['amount'], $r['amount_base']);
        }
        $this->payments->update($depositId, [
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
        $dep = $this->payments->find($depositId);
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
            $r = $this->journalPoster->void((int) $dep['journal_id'], 'Void supplier deposit ' . $dep['payment_no'] . ' - ' . $reason);
            if (! $r['ok']) {
                $db->transComplete();

                return $r;
            }
        }
        $this->payments->update($depositId, ['status' => 'void', 'unapplied' => 0, 'unapplied_base' => 0]);
        $db->transComplete();

        return ['ok' => true];
    }

    public function unapplyDeposit(int $depositId, int $journalId, string $reason): array
    {
        $dep = $this->payments->find($depositId);
        if (! $dep || ($dep['kind'] ?? '') !== 'deposit') {
            return ['ok' => false, 'errors' => ['Deposit not found.']];
        }
        $db     = db_connect();
        $allocs = $db->table('purchase_payment_allocations')
            ->where('payment_id', $depositId)->where('journal_id', $journalId)->get()->getResultArray();
        if (! $allocs) {
            return ['ok' => false, 'errors' => ['That application was not found.']];
        }

        $db->transStart();
        $r = $this->journalPoster->void($journalId, 'Reverse deposit allocation ' . $dep['payment_no'] . ' - ' . $reason);
        if (! $r['ok']) {
            $db->transComplete();

            return $r;
        }
        $txnBack = 0.0;
        foreach ($allocs as $a) {
            $this->applyToInvoice((int) $a['invoice_id'], -(float) $a['amount'], -(float) $a['amount_base']);
            $txnBack += (float) $a['amount'];
        }
        $db->table('purchase_payment_allocations')->where('payment_id', $depositId)->where('journal_id', $journalId)->delete();

        $this->payments->update($depositId, [
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
        $paid     = max(round((float) $inv['paid'] + $deltaTxn, 2), 0.0);
        $paidBase = max(round((float) $inv['paid_base'] + $deltaBase, 2), 0.0);
        $status   = $inv['status'];
        if ($status !== 'void') {
            $status = $paid <= 0.005 ? 'posted' : ($paid + 0.01 >= (float) $inv['total'] ? 'paid' : 'partial');
        }
        $this->invoices->update($invoiceId, ['paid' => $paid, 'paid_base' => $paidBase, 'status' => $status]);
    }
}
