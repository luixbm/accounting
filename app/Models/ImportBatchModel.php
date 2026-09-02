<?php

namespace App\Models;

use CodeIgniter\Model;

class ImportBatchModel extends TenantModel
{
    protected $table         = 'import_batches';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'kind', 'filename', 'stored_path', 'sheet', 'status', 'options',
        'row_count', 'journal_count', 'skipped_count', 'created_by', 'committed_at',
    ];

    /** @return array<string,mixed> */
    public function options(array $batch): array
    {
        $o = json_decode((string) ($batch['options'] ?? ''), true);

        return is_array($o) ? $o : [];
    }

    public function setOptions(int $id, array $options): bool
    {
        return $this->update($id, ['options' => json_encode($options)]);
    }

    public function recent(int $limit = 30)
    {
        return $this->orderBy('id', 'DESC')->findAll($limit);
    }
}
