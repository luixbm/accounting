<?php

namespace App\Models;

class CustomFieldModel extends TenantModel
{
    protected $table         = 'custom_fields';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'entity', 'field_key', 'label', 'type', 'options', 'help',
        'is_required', 'is_active', 'show_in_list', 'sort_order',
    ];

    protected $validationRules = [
        'entity'    => 'required',
        'field_key' => 'required|max_length[40]',
        'label'     => 'required|max_length[120]',
        'type'      => 'required|in_list[text,textarea,number,date,select,checkbox]',
    ];

    public function forEntity(string $entity, bool $activeOnly = true)
    {
        $b = $this->where('entity', $entity);
        if ($activeOnly) {
            $b->where('is_active', 1);
        }

        return $b->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }
}
