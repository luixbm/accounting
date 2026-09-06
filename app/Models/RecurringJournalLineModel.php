<?php

namespace App\Models;

use CodeIgniter\Model;

/** One line of a recurring journal template. Scoped through its template. */
class RecurringJournalLineModel extends Model
{
    protected $table         = 'recurring_journal_lines';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'template_id', 'line_no', 'account_id', 'memo', 'debit', 'credit',
        'customer_id', 'supplier_id', 'job_id',
    ];

    /** @return list<array<string,mixed>> */
    public function forTemplate(int $templateId): array
    {
        return $this->where('template_id', $templateId)->orderBy('line_no', 'ASC')->findAll();
    }
}
