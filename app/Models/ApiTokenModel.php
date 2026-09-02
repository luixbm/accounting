<?php

namespace App\Models;

use Config\Database;

class ApiTokenModel extends TenantModel
{
    protected $table         = 'api_tokens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'name', 'token_hash', 'prefix', 'abilities', 'last_used_at', 'created_by', 'revoked_at',
    ];

    /** Raw-token prefix shown in the UI and embedded in the token itself. */
    public const RAW_PREFIX = 'sa_';

    /**
     * Look a token up by hash across every company (auth happens before the
     * company context exists), bypassing the tenant scope.
     *
     * @return array<string,mixed>|null
     */
    public function byHash(string $hash): ?array
    {
        return Database::connect()->table($this->table)
            ->where('token_hash', $hash)
            ->get()->getRowArray() ?: null;
    }

    /**
     * Create a token for the active company.
     *
     * @param list<string> $abilities
     *
     * @return array{raw:string, row:array<string,mixed>}
     */
    public function issue(string $name, array $abilities): array
    {
        $secret = bin2hex(random_bytes(24)); // 48 hex chars
        $raw    = self::RAW_PREFIX . $secret;

        $data = [
            'name'       => trim($name) ?: 'API token',
            'token_hash' => hash('sha256', $raw),
            'prefix'     => substr($raw, 0, 11),
            'abilities'  => implode(',', array_values(array_unique($abilities))) ?: 'read',
            'created_by' => auth()->id(),
        ];
        $id = (int) $this->insert($data, true);

        return ['raw' => $raw, 'row' => $this->find($id)];
    }

    /** @return list<array<string,mixed>> active company's tokens: live first, then newest */
    public function forCompany(): array
    {
        return $this->orderBy('(revoked_at IS NULL)', 'DESC', false)->orderBy('id', 'DESC')->findAll();
    }

    public function touch(int $id): void
    {
        Database::connect()->table($this->table)
            ->where('id', $id)
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);
    }
}
