<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanyModel extends Model
{
    protected $table         = 'companies';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'code', 'name', 'legal_name', 'npwp', 'address',
        'base_currency', 'logo_path', 'parent_id', 'is_active',
    ];

    protected $validationRules = [
        'id'   => 'permit_empty|is_natural_no_zero',
        'code' => 'required|max_length[20]|is_unique[companies.code,id,{id}]',
        'name' => 'required|max_length[150]',
    ];

    public function active()
    {
        return $this->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
    }
}
