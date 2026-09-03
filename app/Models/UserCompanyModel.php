<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The `user_companies` allow-list. No rows for a user = access to every active
 * company (unrestricted); one or more rows = restricted to those.
 *
 * @see \App\Database\Migrations\CreateUserCompanies
 */
class UserCompanyModel extends Model
{
    protected $table         = 'user_companies';
    protected $primaryKey    = 'user_id'; // composite in the DB; not used for find()
    protected $returnType    = 'array';
    protected $useTimestamps  = false;
    protected $allowedFields = ['user_id', 'company_id'];

    /** @return list<int> company ids assigned to the user ([] = unrestricted) */
    public function idsFor(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return array_map(
            static fn ($r) => (int) $r['company_id'],
            $this->select('company_id')->where('user_id', $userId)->findAll()
        );
    }

    /** True when the user may work in this company. */
    public function allows(int $userId, int $companyId): bool
    {
        $ids = $this->idsFor($userId);

        return $ids === [] || in_array($companyId, $ids, true);
    }

    /** Replace the user's whole assignment set. */
    public function setFor(int $userId, array $companyIds): void
    {
        $this->where('user_id', $userId)->delete();

        $rows = [];
        foreach (array_unique(array_map('intval', $companyIds)) as $cid) {
            if ($cid > 0) {
                $rows[] = ['user_id' => $userId, 'company_id' => $cid];
            }
        }
        if ($rows !== []) {
            $this->insertBatch($rows);
        }
    }
}
