<?php

namespace App\Models;

use CodeIgniter\Model;

class JobModel extends TenantModel
{
    protected $table         = 'jobs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'code', 'name', 'customer_id', 'description', 'status', 'start_date', 'end_date',
        'pax', 'category', 'sales_ref', 'buy_ref', 'created_on', 'jambix_status', 'source', 'import_batch_id',
    ];

    protected $validationRules = [
        'code' => 'required|max_length[30]',
        'name' => 'required|max_length[150]',
    ];

    public function open()
    {
        return $this->where('status', 'open')->orderBy('code', 'ASC')->findAll();
    }

    public function withCustomer(array $filters = [])
    {
        $b = $this->select('jobs.*, customers.name AS customer_name')
            ->join('customers', 'customers.id = jobs.customer_id', 'left');
        if (! empty($filters['status'])) {
            $b->where('jobs.status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $b->groupStart()->like('jobs.code', $filters['q'])->orLike('jobs.name', $filters['q'])->groupEnd();
        }
        if (! empty($filters['arr_from'])) {
            $b->where('jobs.start_date >=', $filters['arr_from']);
        }
        if (! empty($filters['arr_to'])) {
            $b->where('jobs.start_date <=', $filters['arr_to']);
        }

        return $b->orderBy('jobs.start_date', 'ASC')->orderBy('jobs.code', 'ASC')->findAll();
    }
}
