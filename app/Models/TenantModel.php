<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Base model for company-scoped tables. Every read is filtered to the active
 * company and every insert stamps its company_id, unless a scope override is
 * active (used by consolidated reporting).
 */
abstract class TenantModel extends Model
{
    /** @var list<int>|null  when set, reads span these companies instead of the active one */
    protected ?array $companyScopeOverride = null;

    protected function initialize(): void
    {
        parent::initialize();
        $this->allowedFields[] = 'company_id';
        $this->beforeInsert[]  = 'stampCompany';
    }

    /** beforeInsert callback */
    protected function stampCompany(array $data): array
    {
        if (! isset($data['data']['company_id']) || (int) $data['data']['company_id'] === 0) {
            $data['data']['company_id'] = active_company_id();
        }

        return $data;
    }

    protected function scopeTenant(): void
    {
        $col = $this->table . '.company_id';
        if ($this->companyScopeOverride !== null) {
            $this->builder()->whereIn($col, $this->companyScopeOverride ?: [0]);
        } else {
            $this->builder()->where($col, active_company_id());
        }
    }

    /**
     * True when another row in the active company already uses this code.
     * Per-company uniqueness check to replace the framework's global is_unique
     * rule (which also mishandles the {id} placeholder on update()).
     */
    public function codeTaken(string $code, ?int $exceptId = null, string $field = 'code'): bool
    {
        $b = $this->builder()
            ->where($this->table . '.company_id', active_company_id())
            ->where($this->table . '.' . $field, $code);
        if ($exceptId !== null) {
            $b->where($this->table . '.id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }

    /** Run a callback with reads scoped to a specific set of companies. */
    public function withCompanies(array $ids, callable $fn)
    {
        $prev                       = $this->companyScopeOverride;
        $this->companyScopeOverride = array_values(array_map('intval', $ids));

        try {
            return $fn($this);
        } finally {
            $this->companyScopeOverride = $prev;
        }
    }

    // --- scoped overrides -------------------------------------------------

    public function find($id = null)
    {
        $this->scopeTenant();

        return parent::find($id);
    }

    public function findAll(?int $limit = null, int $offset = 0)
    {
        $this->scopeTenant();

        return parent::findAll($limit, $offset);
    }

    public function first()
    {
        $this->scopeTenant();

        return parent::first();
    }

    public function countAllResults(bool $reset = true, bool $test = false)
    {
        $this->scopeTenant();

        return parent::countAllResults($reset, $test);
    }

    public function paginate(?int $perPage = null, string $group = 'default', ?int $page = null, int $segment = 0)
    {
        $this->scopeTenant();

        return parent::paginate($perPage, $group, $page, $segment);
    }

    public function update($id = null, $data = null): bool
    {
        if ($id !== null) {
            $this->builder()->where($this->table . '.company_id', active_company_id());
        }

        return parent::update($id, $data);
    }

    public function delete($id = null, bool $purge = false)
    {
        if ($id !== null) {
            $this->builder()->where($this->table . '.company_id', active_company_id());
        }

        return parent::delete($id, $purge);
    }
}
