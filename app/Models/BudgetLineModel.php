<?php

namespace App\Models;

use CodeIgniter\Model;

/** One budget figure: version × account × month. Scoped through its version. */
class BudgetLineModel extends Model
{
    protected $table         = 'budget_lines';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['version_id', 'account_id', 'period_month', 'amount'];
}
