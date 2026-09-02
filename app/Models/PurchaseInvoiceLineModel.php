<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseInvoiceLineModel extends Model
{
    protected $table         = 'purchase_invoice_lines';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = false;
    protected $allowedFields = ['invoice_id', 'line_no', 'account_id', 'job_id', 'description', 'amount', 'amount_base', 'budget_amount', 'booking_ref', 'service_date', 'party_name', 'units', 'nights', 'cost_source', 'cost_remark', 'supp_inv_ref', 'supp_inv_date'];

    public function forInvoice(int $invoiceId): array
    {
        return $this->select('purchase_invoice_lines.*, accounts.code AS account_code, accounts.name AS account_name, jobs.code AS job_code')
            ->join('accounts', 'accounts.id = purchase_invoice_lines.account_id', 'left')
            ->join('jobs', 'jobs.id = purchase_invoice_lines.job_id', 'left')
            ->where('invoice_id', $invoiceId)
            ->orderBy('line_no', 'ASC')
            ->findAll();
    }
}
