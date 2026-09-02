<?php

namespace App\Controllers;

use App\Models\FiscalPeriodModel;
use App\Models\JournalModel;

class PeriodController extends BaseController
{
    public function index(?int $year = null)
    {
        $year ??= (int) date('Y');
        $model = model(FiscalPeriodModel::class);

        $grid     = $model->grid($year);
        $journals = model(JournalModel::class);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $from  = sprintf('%04d-%02d-01', $year, $m);
            $to    = date('Y-m-t', strtotime($from));
            $count = $journals->where('entry_date >=', $from)->where('entry_date <=', $to)->countAllResults();
            $months[$m] = [
                'label'   => date('F', strtotime($from)),
                'status'  => $grid[$m]['status'] ?? 'open',
                'count'   => $count,
                'from'    => $from,
            ];
        }

        return view('periods/index', [
            'title'  => 'Accounting Periods',
            'year'   => $year,
            'months' => $months,
            'canClose' => user_can('period.close'),
        ]);
    }

    public function close()
    {
        if (! user_can('period.close')) {
            return redirect()->to('periods')->with('error', 'Not allowed.');
        }
        $year  = (int) $this->request->getPost('year');
        $month = (int) $this->request->getPost('month');
        $model = model(FiscalPeriodModel::class);
        $model->ensure($year, $month);
        $row = $model->where('year', $year)->where('month', $month)->first();
        $model->update($row['id'], [
            'status'    => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
            'closed_by' => auth()->id(),
        ]);

        return redirect()->to('periods/' . $year)->with('message', sprintf('%s %d closed. Journals in that month can no longer be posted or voided.', date('F', mktime(0, 0, 0, $month, 1)), $year));
    }

    public function reopen()
    {
        if (! user_can('period.close')) {
            return redirect()->to('periods')->with('error', 'Not allowed.');
        }
        $year  = (int) $this->request->getPost('year');
        $month = (int) $this->request->getPost('month');
        $model = model(FiscalPeriodModel::class);
        $row   = $model->where('year', $year)->where('month', $month)->first();
        if ($row) {
            $model->update($row['id'], ['status' => 'open', 'closed_at' => null, 'closed_by' => null]);
        }

        return redirect()->to('periods/' . $year)->with('message', 'Period reopened.');
    }
}
