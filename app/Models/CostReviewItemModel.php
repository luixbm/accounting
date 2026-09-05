<?php

namespace App\Models;

class CostReviewItemModel extends TenantModel
{
    protected $table         = 'cost_review_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'batch_id', 'line_no', 'payload', 'party_name', 'description',
        'requested_amount', 'matched_budget', 'variance', 'over_budget',
        'match_type', 'match_status', 'match_message',
        'invoice_id', 'invoice_internal_no', 'line_id', 'job_id',
        'service_date', 'promise_date', 'confirmed_at', 'confirmed_by',
    ];
}
