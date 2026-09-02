<?php

namespace App\Models;

use CodeIgniter\Model;

class JournalLineModel extends TenantModel
{
    protected $table         = 'journal_lines';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'journal_id', 'line_no', 'account_id', 'memo',
        'debit', 'credit', 'debit_base', 'credit_base',
        'customer_id', 'supplier_id', 'job_id',
    ];

    public function forJournal(int $journalId): array
    {
        return $this->select('journal_lines.*, accounts.code AS account_code, accounts.name AS account_name,
                accounts.subledger AS account_subledger,
                customers.name AS customer_name, suppliers.name AS supplier_name,
                jobs.code AS job_code, jobs.name AS job_name')
            ->join('accounts', 'accounts.id = journal_lines.account_id')
            ->join('customers', 'customers.id = journal_lines.customer_id', 'left')
            ->join('suppliers', 'suppliers.id = journal_lines.supplier_id', 'left')
            ->join('jobs', 'jobs.id = journal_lines.job_id', 'left')
            ->where('journal_id', $journalId)
            ->orderBy('line_no', 'ASC')
            ->findAll();
    }
}
