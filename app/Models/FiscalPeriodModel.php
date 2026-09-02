<?php

namespace App\Models;

use CodeIgniter\Model;

class FiscalPeriodModel extends TenantModel
{
    protected $table         = 'fiscal_periods';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = false;
    protected $allowedFields = ['year', 'month', 'status', 'closed_at', 'closed_by'];

    /**
     * True when the given Y-m-d date falls in a period explicitly marked closed.
     */
    public function isDateLocked(string $date): bool
    {
        $ts    = strtotime($date);
        $year  = (int) date('Y', $ts);
        $month = (int) date('n', $ts);

        $row = $this->where('year', $year)->where('month', $month)->first();

        return $row !== null && $row['status'] === 'closed';
    }

    public function ensure(int $year, int $month): array
    {
        $row = $this->where('year', $year)->where('month', $month)->first();
        if ($row) {
            return $row;
        }
        $id = $this->insert(['year' => $year, 'month' => $month, 'status' => 'open'], true);

        return $this->find($id);
    }

    public function grid(int $year): array
    {
        $rows = $this->where('year', $year)->findAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['month']] = $r;
        }

        return $out;
    }
}
