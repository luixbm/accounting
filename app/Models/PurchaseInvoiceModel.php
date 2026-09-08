<?php

namespace App\Models;

class PurchaseInvoiceModel extends TenantModel
{
    protected $table         = 'purchase_invoices';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'internal_no', 'doc_type', 'supplier_ref', 'supplier_id', 'invoice_date', 'due_date',
        'currency_id', 'exchange_rate', 'description',
        'subtotal', 'ppn_amount', 'pph_amount', 'total', 'total_base', 'paid_base', 'paid',
        'status', 'journal_id', 'import_batch_id', 'external_id', 'source', 'created_by', 'posted_by', 'posted_at',
    ];

    public function listing(array $filters = [], int $perPage = 25)
    {
        $b = $this->select('purchase_invoices.*, suppliers.name AS supplier_name, currencies.code AS currency_code,
                (purchase_invoices.total_base - purchase_invoices.paid_base) AS outstanding_base')
            ->join('suppliers', 'suppliers.id = purchase_invoices.supplier_id', 'left')
            ->join('currencies', 'currencies.id = purchase_invoices.currency_id', 'left');

        if (! empty($filters['status'])) {
            $b->where('purchase_invoices.status', $filters['status']);
        }
        if (! empty($filters['supplier_id'])) {
            $b->where('purchase_invoices.supplier_id', $filters['supplier_id']);
        }
        if (! empty($filters['doc_type'])) {
            $b->where('purchase_invoices.doc_type', $filters['doc_type']);
        }
        if (! empty($filters['q'])) {
            $q    = trim((string) $filters['q']);
            $like = $this->db->escape('%' . $q . '%');
            $b->groupStart()
                ->like('purchase_invoices.internal_no', $q)
                ->orLike('purchase_invoices.supplier_ref', $q)
                ->orLike('purchase_invoices.description', $q)
                ->orLike('purchase_invoices.external_id', $q)
                ->orLike('suppliers.name', $q)
                // also match text written on any line of the invoice
                ->orWhere(
                    "purchase_invoices.id IN (
                        SELECT l.invoice_id FROM purchase_invoice_lines l WHERE l.description LIKE {$like}
                    )",
                    null,
                    false
                )
                ->groupEnd();
        }
        if (! empty($filters['from'])) {
            $b->where('purchase_invoices.invoice_date >=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $b->where('purchase_invoices.invoice_date <=', $filters['to']);
        }

        return $b->orderBy('purchase_invoices.invoice_date', 'DESC')->orderBy('purchase_invoices.id', 'DESC')
            ->paginate($perPage);
    }

    /** Posted / partly-paid invoices for a supplier, oldest first. */
    public function openForSupplier(int $supplierId): array
    {
        return $this->select('purchase_invoices.*, currencies.code AS currency_code,
                (purchase_invoices.total_base - purchase_invoices.paid_base) AS outstanding_base,
                (purchase_invoices.total - purchase_invoices.paid) AS outstanding')
            ->join('currencies', 'currencies.id = purchase_invoices.currency_id', 'left')
            ->whereIn('purchase_invoices.status', ['posted', 'partial'])
            ->where('purchase_invoices.supplier_id', $supplierId)
            ->where('(purchase_invoices.total - purchase_invoices.paid) >', 0.005)
            ->orderBy('purchase_invoices.invoice_date', 'ASC')->orderBy('purchase_invoices.id', 'ASC')
            ->findAll();
    }

    public function nextNo(string $docType = 'invoice'): string
    {
        $stem = ($docType === 'credit_note' ? 'PCN-' : 'PI-') . date('ym') . '-';
        $row  = $this->like('internal_no', $stem, 'after')->orderBy('internal_no', 'DESC')->first();
        $seq  = $row ? (int) substr($row['internal_no'], -4) + 1 : 1;

        return $stem . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
