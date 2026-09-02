<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountAliasModel extends TenantModel
{
    protected $table         = 'account_aliases';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = ['source_label', 'account_id'];

    public static function normalise(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $label)));
    }

    /** @return array<string,int> normalised label => account_id */
    public function map(): array
    {
        $out = [];
        foreach ($this->findAll() as $row) {
            $out[$row['source_label']] = (int) $row['account_id'];
        }

        return $out;
    }

    public function put(string $label, int $accountId): void
    {
        $norm     = self::normalise($label);
        $existing = $this->where('source_label', $norm)->first();
        if ($existing) {
            $this->update($existing['id'], ['account_id' => $accountId]);
        } else {
            $this->insert(['source_label' => $norm, 'account_id' => $accountId]);
        }
    }
}
