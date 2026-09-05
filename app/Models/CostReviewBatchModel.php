<?php

namespace App\Models;

class CostReviewBatchModel extends TenantModel
{
    protected $table         = 'cost_review_batches';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'source', 'file_name', 'file_url', 'vendor',
        'item_count', 'confirmed_count', 'status', 'confirmed_at',
    ];
}
