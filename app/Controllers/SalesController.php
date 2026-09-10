<?php

namespace App\Controllers;

use App\Libraries\Accounting\SalesPoster;
use App\Libraries\CustomFields;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\JobModel;
use App\Models\SalesInvoiceLineModel;
use App\Models\SalesInvoiceModel;

class SalesController extends BaseController
{
    private SalesInvoiceModel $invoices;
    private CustomFields $cf;

    public function __construct()
    {
        $this->invoices = model(SalesInvoiceModel::class);
        $this->cf       = new CustomFields();
    }

    private function canEdit(): bool
    {
        return user_can('journal.create');
    }

    public function index()
    {
        $filters = sticky_filters('sales', ['status', 'customer_id', 'q', 'from', 'to', 'doc_type']);
        if ($filters instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $filters;
        }
        $rows = $this->invoices->listing($filters);
        $ids  = array_map(static fn ($r) => (int) $r['id'], $rows);

        return view('sales/index', [
            'title'     => 'Sales',
            'rows'      => $rows,
            'pager'     => $this->invoices->pager,
            'filters'   => $filters,
            'customers' => model(CustomerModel::class)->active(),
            'cfDefs'    => array_filter($this->cf->defs('sales_invoice'), static fn ($d) => (int) $d['show_in_list'] === 1),
            'cfValues'  => $this->cf->valuesForMany('sales_invoice', $ids),
        ]);
    }

    public function new(?string $type = null)
    {
        if (! $this->canEdit()) {
            return redirect()->to('sales')->with('error', 'Not allowed.');
        }
        $docType = $type === 'credit-note' ? 'credit_note' : 'invoice';

        return view('sales/form', $this->formData(null, [], $docType));
    }

    public function edit(int $id)
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return redirect()->to('sales')->with('error', 'Invoice not found.');
        }
        if (! $this->canEdit()) {
            return redirect()->to('sales/' . $id)->with('error', 'Not allowed.');
        }

        $editablePosted = $inv['status'] === 'posted' && (float) $inv['received_base'] <= 0.005;
        if ($inv['status'] !== 'draft' && ! $editablePosted) {
            return redirect()->to('sales/' . $id)->with('error', $inv['status'] === 'void'
                ? 'Void invoices cannot be edited.'
                : 'Void the receipts against this invoice before editing it.');
        }
        if ($editablePosted && ! (user_can('journal.void') && user_can('journal.post'))) {
            return redirect()->to('sales/' . $id)->with('error', 'Editing a posted invoice needs the void and post permissions.');
        }

        $lines = model(SalesInvoiceLineModel::class)->where('invoice_id', $id)->orderBy('line_no')->findAll();

        return view('sales/form', $this->formData($inv, $lines));
    }

    public function create()
    {
        return $this->persist(null);
    }

    public function update(int $id)
    {
        return $this->persist($id);
    }

    private function persist(?int $id)
    {
        if (! $this->canEdit()) {
            return redirect()->to('sales')->with('error', 'Not allowed.');
        }

        // Editing a posted invoice re-posts it atomically (un-post -> save -> post)
        // via SalesPoster::reviseInvoice; drafts follow the normal save path.
        $editingPosted = false;
        if ($id !== null) {
            $existing = $this->invoices->find($id);
            if (! $existing) {
                return redirect()->to('sales')->with('error', 'Invoice not found.');
            }
            if ($existing['status'] === 'posted') {
                if (! (user_can('journal.void') && user_can('journal.post'))) {
                    return redirect()->to('sales/' . $id)->with('error', 'Editing a posted invoice needs the void and post permissions.');
                }
                $editingPosted = true;
            } elseif ($existing['status'] !== 'draft') {
                return redirect()->to('sales/' . $id)->with('error', $existing['status'] === 'void'
                    ? 'Void invoices cannot be edited.'
                    : 'Void the receipts against this invoice before editing it.');
            }
        }

        $cf    = (array) $this->request->getPost('cf');
        $cfErr = $this->cf->validate('sales_invoice', $cf);
        if ($cfErr) {
            return redirect()->back()->withInput()->with('errors', $cfErr);
        }

        $header = [
            'doc_type'      => $this->request->getPost('doc_type'),
            'customer_ref'  => $this->request->getPost('customer_ref'),
            'customer_id'   => $this->request->getPost('customer_id'),
            'invoice_date'  => $this->request->getPost('invoice_date'),
            'due_date'      => $this->request->getPost('due_date'),
            'currency_id'   => $this->request->getPost('currency_id'),
            'exchange_rate' => $this->request->getPost('exchange_rate'),
            'description'   => $this->request->getPost('description'),
            'ppn_amount'    => $this->request->getPost('ppn_amount'),
            'pph_amount'    => $this->request->getPost('pph_amount'),
        ];

        $poster = new SalesPoster();
        $res    = $editingPosted
            ? $poster->reviseInvoice((int) $id, $header, $this->collectLines())
            : $poster->saveInvoice($header, $this->collectLines(), $id);
        if (! $res['ok']) {
            return redirect()->back()->withInput()->with('errors', $res['errors']);
        }
        $this->cf->save('sales_invoice', $res['id'], $cf);

        $noun = $this->request->getPost('doc_type') === 'credit_note'
            ? lang('Txn.doc_credit_note') : lang('Txn.doc_invoice');

        if ($editingPosted) {
            return redirect()->to('sales/' . $res['id'])->with('message', $noun . ' updated and re-posted.');
        }

        if ($this->request->getPost('action') === 'post' && user_can('journal.post')) {
            $p = $poster->postInvoice($res['id']);
            if (! $p['ok']) {
                return redirect()->to('sales/' . $res['id'])->with('errors', $p['errors']);
            }

            return redirect()->to('sales/' . $res['id'])->with('message', $noun . ' saved and posted.');
        }

        return redirect()->to('sales/' . $res['id'])->with('message', $noun . ' saved as draft.');
    }

    public function show(int $id)
    {
        $inv = $this->invoices
            ->select('sales_invoices.*, customers.name AS customer_name, currencies.code AS currency_code, currencies.is_base AS currency_is_base')
            ->join('customers', 'customers.id = sales_invoices.customer_id', 'left')
            ->join('currencies', 'currencies.id = sales_invoices.currency_id', 'left')
            ->find($id);
        if (! $inv) {
            return redirect()->to('sales')->with('error', 'Invoice not found.');
        }

        $allocs = db_connect()->table('sales_receipt_allocations sra')
            ->select('sra.amount_base, sr.receipt_no, sr.receipt_date, sr.id AS receipt_id')
            ->join('sales_receipts sr', 'sr.id = sra.receipt_id')
            ->where('sra.invoice_id', $id)->where('sr.status', 'posted')
            ->orderBy('sr.receipt_date', 'ASC')->get()->getResultArray();

        $ei = model(\App\Models\EinvoiceSettingModel::class)->current();

        return view('sales/show', [
            'title'     => $inv['internal_no'],
            'inv'       => $inv,
            'lines'     => model(SalesInvoiceLineModel::class)->forInvoice($id),
            'allocs'    => $allocs,
            'cfDefs'    => $this->cf->defs('sales_invoice'),
            'cfValues'  => $this->cf->valuesFor('sales_invoice', $id),
            'eiEnabled' => (bool) ($ei['enabled'] ?? false),
            'eiEnv'     => (string) ($ei['environment'] ?? 'sandbox'),
        ]);
    }

    public function post(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/' . $id)->with('error', 'You cannot post.');
        }
        $r = (new SalesPoster())->postInvoice($id);

        return $r['ok']
            ? redirect()->to('sales/' . $id)->with('message', 'Invoice posted.')
            : redirect()->to('sales/' . $id)->with('errors', $r['errors']);
    }

    public function void(int $id)
    {
        if (! user_can('journal.void')) {
            return redirect()->to('sales/' . $id)->with('error', 'You cannot void.');
        }
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '') {
            return redirect()->to('sales/' . $id)->with('error', 'A reason is required.');
        }
        $r = (new SalesPoster())->voidInvoice($id, $reason);

        return $r['ok']
            ? redirect()->to('sales/' . $id)->with('message', 'Invoice voided.')
            : redirect()->to('sales/' . $id)->with('errors', $r['errors']);
    }

    public function delete(int $id)
    {
        if (! user_can('journal.delete')) {
            return redirect()->to('sales/' . $id)->with('error', 'You cannot delete.');
        }
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return redirect()->to('sales')->with('error', 'Invoice not found.');
        }
        if ($inv['status'] !== 'draft' && ! user_can('journal.void')) {
            return redirect()->to('sales/' . $id)->with('error', 'You need the void permission to delete a posted invoice.');
        }

        $r = (new SalesPoster())->deleteInvoice($id);
        if ($r['ok']) {
            $this->cf->deleteFor('sales_invoice', $id);

            return redirect()->to('sales')->with('message', 'Invoice deleted.');
        }

        return redirect()->to('sales/' . $id)->with('errors', $r['errors']);
    }

    /** @return array<int,array<string,mixed>> */
    private function collectLines(): array
    {
        $acc   = $this->request->getPost('line_account') ?? [];
        $job   = $this->request->getPost('line_job') ?? [];
        $desc  = $this->request->getPost('line_desc') ?? [];
        $amt   = $this->request->getPost('line_amount') ?? [];
        $svc   = $this->request->getPost('line_service_date') ?? [];
        $unit  = $this->request->getPost('line_units') ?? [];
        $party = $this->request->getPost('line_party') ?? [];
        $book  = $this->request->getPost('line_booking') ?? [];
        $nts   = $this->request->getPost('line_nights') ?? [];
        $pax   = $this->request->getPost('line_pax') ?? [];
        $dur   = $this->request->getPost('line_duration') ?? [];
        $rmk   = $this->request->getPost('line_remark') ?? [];

        $out = [];
        foreach ($acc as $i => $a) {
            $out[] = [
                'account_id' => $a, 'job_id' => $job[$i] ?? null, 'description' => $desc[$i] ?? null, 'amount' => $amt[$i] ?? 0,
                'service_date' => $svc[$i] ?? null, 'units' => $unit[$i] ?? null, 'party_name' => $party[$i] ?? null,
                'booking_ref' => $book[$i] ?? null, 'nights' => $nts[$i] ?? null,
                'pax' => $pax[$i] ?? null, 'duration' => $dur[$i] ?? null, 'remark' => $rmk[$i] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed>|null $inv
     *
     * @return array<string,mixed>
     */
    private function formData(?array $inv, array $lines = [], string $docType = 'invoice'): array
    {
        $docType = $inv['doc_type'] ?? $docType;
        $noun    = $docType === 'credit_note' ? lang('Txn.doc_credit_note') : lang('Txn.doc_invoice');

        return [
            'title'      => $inv ? 'Edit ' . $inv['internal_no'] : lang('Txn.new_x', [$noun]),
            'docType'    => $docType,
            'inv'        => $inv,
            'lines'      => $lines,
            'customers'  => model(CustomerModel::class)->active(),
            'accounts'   => model(AccountModel::class)->postable(),
            'jobs'       => model(JobModel::class)->open(),
            'currencies' => model(CurrencyModel::class)->active(),
            'baseId'     => model(CurrencyModel::class)->baseId(),
            'canPost'    => user_can('journal.post'),
            'ppnRate'    => (float) acc_setting('ppnRate'),
            'pphRate'    => (float) acc_setting('pph23Rate'),
            'cfDefs'     => $this->cf->defs('sales_invoice'),
            'cfValues'   => $inv ? $this->cf->valuesFor('sales_invoice', (int) $inv['id']) : [],
        ];
    }
}
