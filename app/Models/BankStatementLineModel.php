<?php

namespace App\Models;

class BankStatementLineModel extends TenantModel
{
    protected $table         = 'bank_statement_lines';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'statement_id', 'txn_date', 'description', 'reference', 'amount',
        'matched_line_id', 'match_type', 'sort_no',
    ];

    /** @return list<array<string,mixed>> */
    public function forStatement(int $statementId): array
    {
        return $this->where('statement_id', $statementId)
            ->orderBy('txn_date', 'ASC')->orderBy('sort_no', 'ASC')->orderBy('id', 'ASC')
            ->findAll();
    }
}
