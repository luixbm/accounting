<?php

namespace App\Models;

class BankStatementModel extends TenantModel
{
    protected $table         = 'bank_statements';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'bank_account_id', 'statement_date', 'opening_balance', 'closing_balance',
        'filename', 'stored_path', 'sheet', 'options', 'status', 'note',
        'reconciled_at', 'created_by',
    ];

    /** @return array<string,mixed> */
    public function options(array $row): array
    {
        $o = json_decode((string) ($row['options'] ?? ''), true);

        return is_array($o) ? $o : [];
    }

    public function setOptions(int $id, array $options): bool
    {
        return $this->update($id, ['options' => json_encode($options)]);
    }

    public function recent(int $limit = 40)
    {
        return $this->orderBy('statement_date', 'DESC')->orderBy('id', 'DESC')->findAll($limit);
    }
}
