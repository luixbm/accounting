<?php

namespace App\Models;

class SalesInvoiceModel extends TenantModel
{
    protected $table         = 'sales_invoices';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'internal_no', 'doc_type', 'customer_ref', 'customer_id', 'invoice_date', 'due_date',
        'currency_id', 'exchange_rate', 'description',
        'subtotal', 'ppn_amount', 'pph_amount', 'total', 'total_base', 'received_base', 'received',
        'status', 'journal_id', 'import_batch_id', 'external_id', 'source', 'created_by', 'posted_by', 'posted_at',
        'einvoice_status', 'einvoice_uuid', 'einvoice_long_id', 'einvoice_submission_uid',
        'einvoice_submitted_at', 'einvoice_validated_at', 'einvoice_error',
    ];

    public function listing(array $filters = [], int $perPage = 25)
    {
        $b = $this->select('sales_invoices.*, customers.name AS customer_name, currencies.code AS currency_code,
                (sales_invoices.total_base - sales_invoices.received_base) AS outstanding_base')
            ->join('customers', 'customers.id = sales_invoices.customer_id', 'left')
            ->join('currencies', 'currencies.id = sales_invoices.currency_id', 'left');

        if (! empty($filters['status'])) {
            $b->where('sales_invoices.status', $filters['status']);
        }
        if (! empty($filters['customer_id'])) {
            $b->where('sales_invoices.customer_id', $filters['customer_id']);
        }
        if (! empty($filters['doc_type'])) {
            $b->where('sales_invoices.doc_type', $filters['doc_type']);
        }
        if (! empty($filters['q'])) {
            $b->groupStart()
                ->like('sales_invoices.internal_no', $filters['q'])
                ->orLike('sales_invoices.customer_ref', $filters['q'])
                ->orLike('sales_invoices.description', $filters['q'])
                ->groupEnd();
        }
        if (! empty($filters['from'])) {
            $b->where('sales_invoices.invoice_date >=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $b->where('sales_invoices.invoice_date <=', $filters['to']);
        }

        return $b->orderBy('sales_invoices.invoice_date', 'DESC')->orderBy('sales_invoices.id', 'DESC')
            ->paginate($perPage);
    }

    public function openForCustomer(int $customerId): array
    {
        return $this->select('sales_invoices.*, currencies.code AS currency_code,
                (sales_invoices.total_base - sales_invoices.received_base) AS outstanding_base,
                (sales_invoices.total - sales_invoices.received) AS outstanding')
            ->join('currencies', 'currencies.id = sales_invoices.currency_id', 'left')
            ->whereIn('sales_invoices.status', ['posted', 'partial'])
            ->where('sales_invoices.customer_id', $customerId)
            ->where('(sales_invoices.total - sales_invoices.received) >', 0.005)
            ->orderBy('sales_invoices.invoice_date', 'ASC')->orderBy('sales_invoices.id', 'ASC')
            ->findAll();
    }

    public function nextNo(string $docType = 'invoice'): string
    {
        $stem = ($docType === 'credit_note' ? 'SCN-' : 'SI-') . date('ym') . '-';
        $row  = $this->like('internal_no', $stem, 'after')->orderBy('internal_no', 'DESC')->first();
        $seq  = $row ? (int) substr($row['internal_no'], -4) + 1 : 1;

        return $stem . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
