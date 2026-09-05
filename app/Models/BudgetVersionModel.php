<?php

namespace App\Models;

/** Per-company named budget version, e.g. "Realistic 2026". */
class BudgetVersionModel extends TenantModel
{
    protected $table         = 'budget_versions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'year', 'is_default', 'note', 'created_by'];

    protected $validationRules = [
        'name' => 'required|max_length[60]',
        'year' => 'required|is_natural_no_zero',
    ];

    /** @return array<string,mixed>|null the default version for a year in the active company */
    public function defaultFor(int $year): ?array
    {
        return $this->where('year', $year)->where('is_default', 1)->first();
    }

    /** Clear the default flag on every other version of the same year. */
    public function clearDefault(int $year, int $exceptId): void
    {
        $this->where('year', $year)->where('id !=', $exceptId)->set('is_default', 0)->update();
    }
}
