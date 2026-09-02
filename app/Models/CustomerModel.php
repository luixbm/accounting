<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends TenantModel
{
    protected $table         = 'customers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = ['code', 'name', 'email', 'phone', 'npwp', 'address', 'is_active'];

    protected $validationRules = [
        'code'  => 'required|max_length[20]',
        'name'  => 'required|max_length[150]',
        'email' => 'permit_empty|valid_email',
    ];

    public function active()
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
