<?php

namespace App\Controllers;

use App\Libraries\Accounting\PurchasePoster;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\ExchangeRateModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchasePaymentModel;
use App\Models\SupplierModel;

class PurchasePaymentController extends BaseController
{
    private PurchasePaymentModel $payments;

    public function __construct()
    {
        $this->payments = model(PurchasePaymentModel::class);
    }

    /** @return array<string,float> currency code => suggested rate today */
    private function ratesToday(): array
    {
        $out = [];
        foreach (model(CurrencyModel::class)->where('is_active', 1)->findAll() as $c) {
            $out[$c['code']] = model(ExchangeRateModel::class)->rateFor((int) $c['id'], date('Y-m-d'));
        }

        return $out;
    }

    public function index()
    {
        $filters = sticky_filters('purchase_payments', ['supplier_id', 'q', 'kind']);
        if ($filters instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $filters;
        }

        return view('purchases/payments/index', [
            'title'     => 'Supplier Payments',
            'rows'      => $this->payments->listing($filters),
            'pager'     => $this->payments->pager,
            'filters'   => $filters,
            'suppliers' => model(SupplierModel::class)->active(),
        ]);
    }

    /**
     * Unapplied supplier deposits, normalised for partials/deposit_notice.
     *
     * @return list<array<string,mixed>>
     */
    private function depositNotice(int $supplierId): array
    {
        return array_map(static fn ($d) => [
            'id'            => (int) $d['id'],
            'no'            => $d['payment_no'],
            'date'          => $d['payment_date'],
            'unapplied'     => (float) $d['unapplied'],
            'currency_code' => $d['currency_code'] ?: base_code(),
        ], $this->payments->unappliedDepositsFor($supplierId));
    }

    public function new()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('purchases/payments')->with('error', 'Not allowed.');
        }
        $supplierId = (int) ($this->request->getGet('supplier_id') ?? 0);
        $open       = $supplierId ? model(PurchaseInvoiceModel::class)->openForSupplier($supplierId) : [];

        return view('purchases/payments/new', [
            'title'      => 'New Supplier Payment',
            'suppliers'  => model(SupplierModel::class)->active(),
            'supplierId' => $supplierId,
            'open'       => $open,
            'deposits'   => $this->depositNotice($supplierId),
            'banks'      => model(AccountModel::class)->cashAccounts(),
            'baseCode'   => base_code(),
            'rates'      => $this->ratesToday(),
        ]);
    }

    // ---------------------------------------------------------------- deposits

    public function depositNew()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('purchases/payments')->with('error', 'Not allowed.');
        }

        return view('purchases/payments/deposit', [
            'title'      => 'Record Supplier Deposit',
            'suppliers'  => model(SupplierModel::class)->active(),
            'supplierId' => (int) ($this->request->getGet('supplier_id') ?? 0),
            'banks'      => model(AccountModel::class)->cashAccounts(),
            'currencies' => model(CurrencyModel::class)->where('is_active', 1)->orderBy('is_base', 'DESC')->orderBy('code')->findAll(),
            'baseCode'   => base_code(),
            'rates'      => $this->ratesToday(),
        ]);
    }

    public function depositCreate()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('purchases/payments')->with('error', 'Not allowed.');
        }
        $r = (new PurchasePoster())->saveDeposit([
            'supplier_id'     => $this->request->getPost('supplier_id'),
            'payment_date'    => $this->request->getPost('payment_date'),
            'bank_account_id' => $this->request->getPost('bank_account_id'),
            'currency_id'     => $this->request->getPost('currency_id'),
            'exchange_rate'   => $this->request->getPost('exchange_rate'),
            'amount'          => $this->request->getPost('amount'),
            'reference'       => $this->request->getPost('reference'),
        ]);
        if (! $r['ok']) {
            return redirect()->back()->withInput()->with('errors', $r['errors']);
        }

        return redirect()->to('purchases/payments/' . $r['id'])->with('message', 'Deposit recorded and posted.');
    }

    public function applyForm(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('purchases/payments')->with('error', 'Not allowed.');
        }
        $dep = $this->payments
            ->select('purchase_payments.*, cur.code AS currency_code')
            ->join('currencies cur', 'cur.id = purchase_payments.currency_id', 'left')
            ->find($id);
        if (! $dep || ($dep['kind'] ?? '') !== 'deposit') {
            return redirect()->to('purchases/payments')->with('error', 'Deposit not found.');
        }
        if ($dep['status'] !== 'posted' || (float) $dep['unapplied'] <= 0.005) {
            return redirect()->to('purchases/payments/' . $id)->with('error', 'Nothing left to apply on this deposit.');
        }
        $open = array_filter(
            model(PurchaseInvoiceModel::class)->openForSupplier((int) $dep['supplier_id']),
            static fn ($i) => (int) $i['currency_id'] === (int) $dep['currency_id']
        );

        return view('purchases/payments/apply', [
            'title' => 'Apply ' . $dep['payment_no'],
            'dep'   => $dep,
            'open'  => array_values($open),
        ]);
    }

    public function apply(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('purchases/payments')->with('error', 'Not allowed.');
        }
        $allocations = [];
        foreach ((array) $this->request->getPost('alloc') as $invId => $amt) {
            $allocations[(int) $invId] = $amt;
        }
        $r = (new PurchasePoster())->applyDeposit($id, $allocations, $this->request->getPost('apply_date') ?: null);

        return $r['ok']
            ? redirect()->to('purchases/payments/' . $id)->with('message', 'Deposit applied.')
            : redirect()->back()->withInput()->with('errors', $r['errors']);
    }

    public function unapply(int $id)
    {
        if (! user_can('journal.void')) {
            return redirect()->to('purchases/payments/' . $id)->with('error', 'You cannot reverse this.');
        }
        $r = (new PurchasePoster())->unapplyDeposit($id, (int) $this->request->getPost('journal_id'), trim((string) $this->request->getPost('reason')) ?: 'no reason given');

        return $r['ok']
            ? redirect()->to('purchases/payments/' . $id)->with('message', 'Allocation reversed; the deposit balance is restored.')
            : redirect()->to('purchases/payments/' . $id)->with('errors', $r['errors']);
    }

    public function create()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('purchases/payments')->with('error', 'Not allowed.');
        }

        $header = [
            'supplier_id'     => $this->request->getPost('supplier_id'),
            'payment_date'    => $this->request->getPost('payment_date'),
            'bank_account_id' => $this->request->getPost('bank_account_id'),
            'reference'       => $this->request->getPost('reference'),
            'currency_id'     => $this->request->getPost('currency_id'),
            'exchange_rate'   => $this->request->getPost('exchange_rate'),
        ];
        $allocations = [];
        foreach ((array) $this->request->getPost('alloc') as $invId => $amt) {
            $allocations[(int) $invId] = $amt;
        }

        $r = (new PurchasePoster())->savePayment($header, $allocations);
        if (! $r['ok']) {
            return redirect()->back()->withInput()->with('errors', $r['errors']);
        }

        return redirect()->to('purchases/payments/' . $r['id'])->with('message', 'Payment recorded and posted.');
    }

    public function show(int $id)
    {
        $pay = $this->payments
            ->select('purchase_payments.*, suppliers.name AS supplier_name, accounts.name AS bank_name, cur.code AS currency_code')
            ->join('suppliers', 'suppliers.id = purchase_payments.supplier_id', 'left')
            ->join('accounts', 'accounts.id = purchase_payments.bank_account_id', 'left')
            ->join('currencies cur', 'cur.id = purchase_payments.currency_id', 'left')
            ->find($id);
        if (! $pay) {
            return redirect()->to('purchases/payments')->with('error', 'Payment not found.');
        }

        $allocs = db_connect()->table('purchase_payment_allocations ppa')
            ->select('ppa.amount, ppa.amount_base, ppa.journal_id, pi.internal_no, pi.supplier_ref, pi.id AS invoice_id, j.entry_date')
            ->join('purchase_invoices pi', 'pi.id = ppa.invoice_id')
            ->join('journals j', 'j.id = ppa.journal_id', 'left')
            ->where('ppa.payment_id', $id)->orderBy('ppa.id')->get()->getResultArray();

        $applications = [];
        if (($pay['kind'] ?? '') === 'deposit') {
            foreach ($allocs as $a) {
                $jid = (int) ($a['journal_id'] ?? 0);
                $applications[$jid] ??= ['journal_id' => $jid, 'date' => $a['entry_date'], 'lines' => [], 'total' => 0.0];
                $applications[$jid]['lines'][] = $a;
                $applications[$jid]['total']  += (float) $a['amount'];
            }
        }

        return view('purchases/payments/show', [
            'title'        => $pay['payment_no'],
            'pay'          => $pay,
            'allocs'       => $allocs,
            'applications' => array_values($applications),
        ]);
    }

    public function void(int $id)
    {
        if (! user_can('journal.void')) {
            return redirect()->to('purchases/payments/' . $id)->with('error', 'You cannot void.');
        }
        $reason = trim((string) $this->request->getPost('reason')) ?: 'no reason given';
        $r      = (new PurchasePoster())->voidPayment($id, $reason);

        return $r['ok']
            ? redirect()->to('purchases/payments/' . $id)->with('message', 'Payment voided; invoices reopened.')
            : redirect()->to('purchases/payments/' . $id)->with('errors', $r['errors']);
    }
}
