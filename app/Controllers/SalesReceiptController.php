<?php

namespace App\Controllers;

use App\Libraries\Accounting\SalesPoster;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\ExchangeRateModel;
use App\Models\SalesInvoiceModel;
use App\Models\SalesReceiptModel;

class SalesReceiptController extends BaseController
{
    private SalesReceiptModel $receipts;

    public function __construct()
    {
        $this->receipts = model(SalesReceiptModel::class);
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
        $filters = [
            'customer_id' => $this->request->getGet('customer_id'),
            'q'           => $this->request->getGet('q'),
            'kind'        => $this->request->getGet('kind'),
        ];

        return view('sales/receipts/index', [
            'title'     => 'Customer Receipts',
            'rows'      => $this->receipts->listing($filters),
            'pager'     => $this->receipts->pager,
            'filters'   => $filters,
            'customers' => model(CustomerModel::class)->active(),
        ]);
    }

    public function new()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/receipts')->with('error', 'Not allowed.');
        }
        $customerId = (int) ($this->request->getGet('customer_id') ?? 0);
        $open       = $customerId ? model(SalesInvoiceModel::class)->openForCustomer($customerId) : [];

        return view('sales/receipts/new', [
            'title'      => 'New Customer Receipt',
            'customers'  => model(CustomerModel::class)->active(),
            'customerId' => $customerId,
            'open'       => $open,
            'banks'      => model(AccountModel::class)->cashAccounts(),
            'baseCode'   => base_code(),
            'rates'      => $this->ratesToday(),
        ]);
    }

    // ---------------------------------------------------------------- deposits

    public function depositNew()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/receipts')->with('error', 'Not allowed.');
        }

        return view('sales/receipts/deposit', [
            'title'      => 'Record Customer Down Payment',
            'customers'  => model(CustomerModel::class)->active(),
            'customerId' => (int) ($this->request->getGet('customer_id') ?? 0),
            'banks'      => model(AccountModel::class)->cashAccounts(),
            'currencies' => model(CurrencyModel::class)->where('is_active', 1)->orderBy('is_base', 'DESC')->orderBy('code')->findAll(),
            'baseCode'   => base_code(),
            'rates'      => $this->ratesToday(),
        ]);
    }

    public function depositCreate()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/receipts')->with('error', 'Not allowed.');
        }
        $r = (new SalesPoster())->saveDeposit([
            'customer_id'     => $this->request->getPost('customer_id'),
            'receipt_date'    => $this->request->getPost('receipt_date'),
            'bank_account_id' => $this->request->getPost('bank_account_id'),
            'currency_id'     => $this->request->getPost('currency_id'),
            'exchange_rate'   => $this->request->getPost('exchange_rate'),
            'amount'          => $this->request->getPost('amount'),
            'reference'       => $this->request->getPost('reference'),
        ]);
        if (! $r['ok']) {
            return redirect()->back()->withInput()->with('errors', $r['errors']);
        }

        return redirect()->to('sales/receipts/' . $r['id'])->with('message', 'Down payment recorded and posted.');
    }

    public function applyForm(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/receipts')->with('error', 'Not allowed.');
        }
        $dep = $this->receipts
            ->select('sales_receipts.*, cur.code AS currency_code')
            ->join('currencies cur', 'cur.id = sales_receipts.currency_id', 'left')
            ->find($id);
        if (! $dep || ($dep['kind'] ?? '') !== 'deposit') {
            return redirect()->to('sales/receipts')->with('error', 'Deposit not found.');
        }
        if ($dep['status'] !== 'posted' || (float) $dep['unapplied'] <= 0.005) {
            return redirect()->to('sales/receipts/' . $id)->with('error', 'Nothing left to apply on this deposit.');
        }
        $open = array_filter(
            model(SalesInvoiceModel::class)->openForCustomer((int) $dep['customer_id']),
            static fn ($i) => (int) $i['currency_id'] === (int) $dep['currency_id']
        );

        return view('sales/receipts/apply', [
            'title' => 'Apply ' . $dep['receipt_no'],
            'dep'   => $dep,
            'open'  => array_values($open),
        ]);
    }

    public function apply(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/receipts')->with('error', 'Not allowed.');
        }
        $allocations = [];
        foreach ((array) $this->request->getPost('alloc') as $invId => $amt) {
            $allocations[(int) $invId] = $amt;
        }
        $r = (new SalesPoster())->applyDeposit($id, $allocations, $this->request->getPost('apply_date') ?: null);

        return $r['ok']
            ? redirect()->to('sales/receipts/' . $id)->with('message', 'Deposit applied.')
            : redirect()->back()->withInput()->with('errors', $r['errors']);
    }

    public function unapply(int $id)
    {
        if (! user_can('journal.void')) {
            return redirect()->to('sales/receipts/' . $id)->with('error', 'You cannot reverse this.');
        }
        $r = (new SalesPoster())->unapplyDeposit($id, (int) $this->request->getPost('journal_id'), trim((string) $this->request->getPost('reason')) ?: 'no reason given');

        return $r['ok']
            ? redirect()->to('sales/receipts/' . $id)->with('message', 'Allocation reversed; the deposit balance is restored.')
            : redirect()->to('sales/receipts/' . $id)->with('errors', $r['errors']);
    }

    public function create()
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/receipts')->with('error', 'Not allowed.');
        }

        $header = [
            'customer_id'     => $this->request->getPost('customer_id'),
            'receipt_date'    => $this->request->getPost('receipt_date'),
            'bank_account_id' => $this->request->getPost('bank_account_id'),
            'reference'       => $this->request->getPost('reference'),
            'currency_id'     => $this->request->getPost('currency_id'),
            'exchange_rate'   => $this->request->getPost('exchange_rate'),
        ];
        $allocations = [];
        foreach ((array) $this->request->getPost('alloc') as $invId => $amt) {
            $allocations[(int) $invId] = $amt;
        }

        $r = (new SalesPoster())->saveReceipt($header, $allocations);
        if (! $r['ok']) {
            return redirect()->back()->withInput()->with('errors', $r['errors']);
        }

        return redirect()->to('sales/receipts/' . $r['id'])->with('message', 'Receipt recorded and posted.');
    }

    public function show(int $id)
    {
        $rc = $this->receipts
            ->select('sales_receipts.*, customers.name AS customer_name, accounts.name AS bank_name, cur.code AS currency_code')
            ->join('customers', 'customers.id = sales_receipts.customer_id', 'left')
            ->join('accounts', 'accounts.id = sales_receipts.bank_account_id', 'left')
            ->join('currencies cur', 'cur.id = sales_receipts.currency_id', 'left')
            ->find($id);
        if (! $rc) {
            return redirect()->to('sales/receipts')->with('error', 'Receipt not found.');
        }

        $allocs = db_connect()->table('sales_receipt_allocations sra')
            ->select('sra.amount, sra.amount_base, sra.journal_id, si.internal_no, si.customer_ref, si.id AS invoice_id, j.entry_date')
            ->join('sales_invoices si', 'si.id = sra.invoice_id')
            ->join('journals j', 'j.id = sra.journal_id', 'left')
            ->where('sra.receipt_id', $id)->orderBy('sra.id')->get()->getResultArray();

        $applications = [];
        if (($rc['kind'] ?? '') === 'deposit') {
            foreach ($allocs as $a) {
                $jid = (int) ($a['journal_id'] ?? 0);
                $applications[$jid] ??= ['journal_id' => $jid, 'date' => $a['entry_date'], 'lines' => [], 'total' => 0.0];
                $applications[$jid]['lines'][] = $a;
                $applications[$jid]['total']  += (float) $a['amount'];
            }
        }

        return view('sales/receipts/show', [
            'title'        => $rc['receipt_no'],
            'pay'          => $rc,
            'allocs'       => $allocs,
            'applications' => array_values($applications),
        ]);
    }

    public function void(int $id)
    {
        if (! user_can('journal.void')) {
            return redirect()->to('sales/receipts/' . $id)->with('error', 'You cannot void.');
        }
        $reason = trim((string) $this->request->getPost('reason')) ?: 'no reason given';
        $r      = (new SalesPoster())->voidReceipt($id, $reason);

        return $r['ok']
            ? redirect()->to('sales/receipts/' . $id)->with('message', 'Receipt voided; invoices reopened.')
            : redirect()->to('sales/receipts/' . $id)->with('errors', $r['errors']);
    }
}
