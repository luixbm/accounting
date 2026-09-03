<?php

namespace App\Controllers;

use App\Libraries\Accounting\PurchasePoster;
use App\Libraries\CustomFields;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\JobModel;
use App\Models\PurchaseInvoiceLineModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SupplierModel;

class PurchaseController extends BaseController
{
    private PurchaseInvoiceModel $invoices;
    private CustomFields $cf;

    public function __construct()
    {
        $this->invoices = model(PurchaseInvoiceModel::class);
        $this->cf       = new CustomFields();
    }

    private function canEdit(): bool
    {
        return user_can('journal.create');
    }

    public function index()
    {
        $filters = sticky_filters('purchases', ['status', 'supplier_id', 'q', 'from', 'to']);
        if ($filters instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $filters;
        }
        $rows = $this->invoices->listing($filters);
        $ids  = array_map(static fn ($r) => (int) $r['id'], $rows);

        return view('purchases/index', [
            'title'     => 'Purchases',
            'rows'      => $rows,
            'pager'     => $this->invoices->pager,
            'filters'   => $filters,
            'suppliers' => model(SupplierModel::class)->active(),
            'cfDefs'    => array_filter($this->cf->defs('purchase_invoice'), static fn ($d) => (int) $d['show_in_list'] === 1),
            'cfValues'  => $this->cf->valuesForMany('purchase_invoice', $ids),
        ]);
    }

    public function new()
    {
        if (! $this->canEdit()) {
            return redirect()->to('purchases')->with('error', 'Not allowed.');
        }

        return view('purchases/form', $this->formData(null));
    }

    public function edit(int $id)
    {
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return redirect()->to('purchases')->with('error', 'Invoice not found.');
        }
        if (! $this->canEdit()) {
            return redirect()->to('purchases/' . $id)->with('error', 'Not allowed.');
        }

        $editablePosted = $inv['status'] === 'posted' && (float) $inv['paid_base'] <= 0.005;
        if ($inv['status'] !== 'draft' && ! $editablePosted) {
            return redirect()->to('purchases/' . $id)->with('error', $inv['status'] === 'void'
                ? 'Void invoices cannot be edited.'
                : 'Void the payments against this invoice before editing it.');
        }
        if ($editablePosted && ! (user_can('journal.void') && user_can('journal.post'))) {
            return redirect()->to('purchases/' . $id)->with('error', 'Editing a posted invoice needs the void and post permissions.');
        }

        $lines = model(PurchaseInvoiceLineModel::class)->where('invoice_id', $id)->orderBy('line_no')->findAll();

        return view('purchases/form', $this->formData($inv, $lines));
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
            return redirect()->to('purchases')->with('error', 'Not allowed.');
        }

        // Editing a posted invoice re-posts it atomically (un-post -> save -> post)
        // via PurchasePoster::reviseInvoice; drafts follow the normal save path.
        $editingPosted = false;
        if ($id !== null) {
            $existing = $this->invoices->find($id);
            if (! $existing) {
                return redirect()->to('purchases')->with('error', 'Invoice not found.');
            }
            if ($existing['status'] === 'posted') {
                if (! (user_can('journal.void') && user_can('journal.post'))) {
                    return redirect()->to('purchases/' . $id)->with('error', 'Editing a posted invoice needs the void and post permissions.');
                }
                $editingPosted = true;
            } elseif ($existing['status'] !== 'draft') {
                return redirect()->to('purchases/' . $id)->with('error', $existing['status'] === 'void'
                    ? 'Void invoices cannot be edited.'
                    : 'Void the payments against this invoice before editing it.');
            }
        }

        $cf     = (array) $this->request->getPost('cf');
        $cfErr  = $this->cf->validate('purchase_invoice', $cf);
        if ($cfErr) {
            return redirect()->back()->withInput()->with('errors', $cfErr);
        }

        $header = [
            'supplier_ref'  => $this->request->getPost('supplier_ref'),
            'supplier_id'   => $this->request->getPost('supplier_id'),
            'invoice_date'  => $this->request->getPost('invoice_date'),
            'due_date'      => $this->request->getPost('due_date'),
            'currency_id'   => $this->request->getPost('currency_id'),
            'exchange_rate' => $this->request->getPost('exchange_rate'),
            'description'   => $this->request->getPost('description'),
            'ppn_amount'    => $this->request->getPost('ppn_amount'),
            'pph_amount'    => $this->request->getPost('pph_amount'),
        ];
        $rawLines = $this->collectLines();

        $poster = new PurchasePoster();
        $res    = $editingPosted
            ? $poster->reviseInvoice((int) $id, $header, $rawLines)
            : $poster->saveInvoice($header, $rawLines, $id);
        if (! $res['ok']) {
            return redirect()->back()->withInput()->with('errors', $res['errors']);
        }

        $this->cf->save('purchase_invoice', $res['id'], $cf);

        if ($editingPosted) {
            return redirect()->to('purchases/' . $res['id'])->with('message', 'Invoice updated and re-posted.');
        }

        if ($this->request->getPost('action') === 'post' && user_can('journal.post')) {
            $p = $poster->postInvoice($res['id']);
            if (! $p['ok']) {
                return redirect()->to('purchases/' . $res['id'])->with('errors', $p['errors']);
            }

            return redirect()->to('purchases/' . $res['id'])->with('message', 'Invoice saved and posted.');
        }

        return redirect()->to('purchases/' . $res['id'])->with('message', 'Invoice saved as draft.');
    }

    public function show(int $id)
    {
        $inv = $this->invoices
            ->select('purchase_invoices.*, suppliers.name AS supplier_name, currencies.code AS currency_code, currencies.is_base AS currency_is_base')
            ->join('suppliers', 'suppliers.id = purchase_invoices.supplier_id', 'left')
            ->join('currencies', 'currencies.id = purchase_invoices.currency_id', 'left')
            ->find($id);
        if (! $inv) {
            return redirect()->to('purchases')->with('error', 'Invoice not found.');
        }

        $allocs = db_connect()->table('purchase_payment_allocations ppa')
            ->select('ppa.amount_base, pp.payment_no, pp.payment_date, pp.id AS payment_id, pp.status')
            ->join('purchase_payments pp', 'pp.id = ppa.payment_id')
            ->where('ppa.invoice_id', $id)->where('pp.status', 'posted')
            ->orderBy('pp.payment_date', 'ASC')->get()->getResultArray();

        return view('purchases/show', [
            'title'    => $inv['internal_no'],
            'inv'      => $inv,
            'lines'    => model(PurchaseInvoiceLineModel::class)->forInvoice($id),
            'allocs'   => $allocs,
            'cfDefs'   => $this->cf->defs('purchase_invoice'),
            'cfValues' => $this->cf->valuesFor('purchase_invoice', $id),
        ]);
    }

    public function post(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('purchases/' . $id)->with('error', 'You cannot post.');
        }
        $r = (new PurchasePoster())->postInvoice($id);

        return $r['ok']
            ? redirect()->to('purchases/' . $id)->with('message', 'Invoice posted.')
            : redirect()->to('purchases/' . $id)->with('errors', $r['errors']);
    }

    public function void(int $id)
    {
        if (! user_can('journal.void')) {
            return redirect()->to('purchases/' . $id)->with('error', 'You cannot void.');
        }
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '') {
            return redirect()->to('purchases/' . $id)->with('error', 'A reason is required.');
        }
        $r = (new PurchasePoster())->voidInvoice($id, $reason);

        return $r['ok']
            ? redirect()->to('purchases/' . $id)->with('message', 'Invoice voided.')
            : redirect()->to('purchases/' . $id)->with('errors', $r['errors']);
    }

    public function delete(int $id)
    {
        if (! user_can('journal.delete')) {
            return redirect()->to('purchases/' . $id)->with('error', 'You cannot delete.');
        }
        $inv = $this->invoices->find($id);
        if (! $inv) {
            return redirect()->to('purchases')->with('error', 'Invoice not found.');
        }
        if ($inv['status'] !== 'draft' && ! user_can('journal.void')) {
            return redirect()->to('purchases/' . $id)->with('error', 'You need the void permission to delete a posted invoice.');
        }

        $r = (new PurchasePoster())->deleteInvoice($id);
        if ($r['ok']) {
            $this->cf->deleteFor('purchase_invoice', $id);

            return redirect()->to('purchases')->with('message', 'Invoice deleted.');
        }

        return redirect()->to('purchases/' . $id)->with('errors', $r['errors']);
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
        $bud   = $this->request->getPost('line_budget') ?? [];
        $rmk   = $this->request->getPost('line_remark') ?? [];
        $csrc  = $this->request->getPost('line_cost_source') ?? [];

        $out = [];
        foreach ($acc as $i => $a) {
            $out[] = [
                'account_id'    => $a,
                'job_id'        => $job[$i] ?? null,
                'description'   => $desc[$i] ?? null,
                'amount'        => $amt[$i] ?? 0,
                'budget_amount' => ($bud[$i] ?? '') !== '' ? $bud[$i] : null,
                'service_date'  => $svc[$i] ?? null,
                'units'         => $unit[$i] ?? null,
                'party_name'    => $party[$i] ?? null,
                'booking_ref'   => $book[$i] ?? null,
                'nights'        => $nts[$i] ?? null,
                'cost_remark'   => $rmk[$i] ?? null,
                'cost_source'   => $csrc[$i] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed>|null $inv
     * @param array<int,mixed>         $lines
     *
     * @return array<string,mixed>
     */
    private function formData(?array $inv, array $lines = []): array
    {
        return [
            'title'      => $inv ? 'Edit ' . $inv['internal_no'] : 'New Purchase Invoice',
            'inv'        => $inv,
            'lines'      => $lines,
            'suppliers'  => model(SupplierModel::class)->active(),
            'accounts'   => model(AccountModel::class)->postable(),
            'jobs'       => model(JobModel::class)->open(),
            'currencies' => model(CurrencyModel::class)->active(),
            'baseId'     => model(CurrencyModel::class)->baseId(),
            'canPost'    => user_can('journal.post'),
            'ppnRate'    => (float) acc_setting('ppnRate'),
            'pphRate'    => (float) acc_setting('pph23Rate'),
            'cfDefs'     => $this->cf->defs('purchase_invoice'),
            'cfValues'   => $inv ? $this->cf->valuesFor('purchase_invoice', (int) $inv['id']) : [],
        ];
    }
}
