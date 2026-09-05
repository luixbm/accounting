<?php

namespace App\Models;

use CodeIgniter\Model;

class JournalModel extends TenantModel
{
    protected $table         = 'journals';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'journal_no', 'entry_date', 'reference', 'description', 'source',
        'currency_id', 'exchange_rate', 'status',
        'total_debit', 'total_credit', 'reversal_of', 'import_batch_id',
        'created_by', 'posted_by', 'posted_at',
        'voided_by', 'voided_at', 'void_reason',
    ];

    protected $validationRules = [
        'entry_date'  => 'required|valid_date[Y-m-d]',
        'description' => 'required|max_length[255]',
        'currency_id' => 'required|is_natural_no_zero',
    ];

    public const SOURCES = [
        'general'      => 'General Journal',
        'cash_receipt' => 'Cash / Bank Receipt',
        'cash_payment' => 'Cash / Bank Payment',
        'sales'        => 'Sales',
        'purchase'     => 'Purchase',
        'memorial'     => 'Memorial / Adjustment',
        'opening'      => 'Opening Balance',
        'adjustment'   => 'Year-end Adjustment',
    ];

    public function listing(array $filters = [], int $perPage = 25)
    {
        $b = $this->select('journals.*, currencies.code AS currency_code')
            ->join('currencies', 'currencies.id = journals.currency_id', 'left');

        if (! empty($filters['status'])) {
            $b->where('journals.status', $filters['status']);
        }
        if (! empty($filters['source'])) {
            $b->where('journals.source', $filters['source']);
        }
        if (! empty($filters['from'])) {
            $b->where('journals.entry_date >=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $b->where('journals.entry_date <=', $filters['to']);
        }
        if (! empty($filters['q'])) {
            $q    = trim((string) $filters['q']);
            $like = $this->db->escape('%' . $q . '%');
            $b->groupStart()
                ->like('journals.journal_no', $q)
                ->orLike('journals.description', $q)
                ->orLike('journals.reference', $q)
                // also match content on any line: memo, account code/name, party name
                ->orWhere(
                    "journals.id IN (
                        SELECT jl.journal_id FROM journal_lines jl
                        LEFT JOIN accounts a ON a.id = jl.account_id
                        LEFT JOIN customers c ON c.id = jl.customer_id
                        LEFT JOIN suppliers s ON s.id = jl.supplier_id
                        WHERE jl.memo LIKE {$like}
                           OR a.code LIKE {$like} OR a.name LIKE {$like}
                           OR c.name LIKE {$like} OR s.name LIKE {$like}
                    )",
                    null,
                    false
                )
                ->groupEnd();
        }

        return $b->orderBy('journals.entry_date', 'DESC')
            ->orderBy('journals.id', 'DESC')
            ->paginate($perPage);
    }

    public function nextSequence(string $prefix): int
    {
        $row = $this->like('journal_no', $prefix, 'after')
            ->orderBy('journal_no', 'DESC')
            ->first();
        if (! $row) {
            return 1;
        }
        $tail = (int) substr($row['journal_no'], -4);

        return $tail + 1;
    }
}
