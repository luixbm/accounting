<?php

namespace App\Controllers;

use App\Libraries\Accounting\Ledger;
use App\Libraries\CustomFields;
use App\Libraries\Report\ReportExporter;
use App\Models\CustomerModel;
use App\Models\JobModel;

class JobController extends BaseController
{
    private JobModel $jobs;
    private CustomFields $cf;

    public function __construct()
    {
        $this->jobs = model(JobModel::class);
        $this->cf   = new CustomFields();
    }

    private function guard(): bool
    {
        return user_can('masterdata.manage') || user_can('journal.create');
    }

    public function index()
    {
        $filters = sticky_filters('jobs', ['status', 'q', 'arr_from', 'arr_to', 'pl']);
        if ($filters instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return $filters;
        }
        $filters['arr_from'] = trim((string) ($filters['arr_from'] ?? ''));
        $filters['arr_to']   = trim((string) ($filters['arr_to'] ?? ''));
        $filters['pl']       = (string) ($filters['pl'] ?? '');

        $summaries = (new Ledger())->jobSummaries();

        // Resolve the P&L filter to an id set up front so the list query itself
        // stays paginated (jobs with no journal activity have no summary → net 0
        // → excluded from both loss and profit, which is what we want).
        if (in_array($filters['pl'], ['loss', 'profit'], true)) {
            $ids = [];
            foreach ($summaries as $jid => $s) {
                $net = $s['net'] ?? 0;
                if (($filters['pl'] === 'loss' && $net < -0.005) || ($filters['pl'] === 'profit' && $net > 0.005)) {
                    $ids[] = (int) $jid;
                }
            }
            $filters['ids'] = $ids ?: [0];
        }

        $xlsx = $this->request->getGet('format') === 'xlsx';

        // Grand totals across the whole filtered set (the on-screen table is paged)
        $refRows = $this->jobs->filteredRefTotals($filters);
        $grand   = ['revenue' => 0.0, 'cost' => 0.0, 'net' => 0.0, 'jbxnet' => 0.0];
        foreach ($refRows as $j) {
            $s = $summaries[(int) $j['id']] ?? ['revenue' => 0, 'cost' => 0, 'net' => 0];
            $grand['revenue'] += (float) $s['revenue'];
            $grand['cost']    += (float) $s['cost'];
            $grand['net']     += (float) $s['net'];
            $grand['jbxnet']  += (float) ($j['sales_ref'] ?? 0) - (float) ($j['buy_ref'] ?? 0);
        }
        $total    = count($refRows);
        $subtitle = $this->filterSummary($filters, $total);

        if ($xlsx) {
            return $this->exportXlsx($this->jobs->withCustomer($filters), $summaries, $subtitle);
        }

        return view('jobs/index', [
            'title'     => 'Jobs',
            'rows'      => $this->jobs->withCustomer($filters, 25),
            'pager'     => $this->jobs->pager,
            'total'     => $total,
            'grand'     => $grand,
            'filters'   => $filters,
            'summaries' => $summaries,
            'subtitle'  => $subtitle,
        ]);
    }

    /** Human-readable one-liner describing the active filters. */
    private function filterSummary(array $f, int $count): string
    {
        $bits = [$count . ' job' . ($count === 1 ? '' : 's')];
        if (($f['q'] ?? '') !== '') {
            $bits[] = 'search "' . $f['q'] . '"';
        }
        if (($f['arr_from'] ?? '') !== '' || ($f['arr_to'] ?? '') !== '') {
            $bits[] = 'arriving ' . ($f['arr_from'] ?: '…') . ' – ' . ($f['arr_to'] ?: '…');
        }
        if (($f['pl'] ?? '') === 'loss') {
            $bits[] = 'loss-making only';
        } elseif (($f['pl'] ?? '') === 'profit') {
            $bits[] = 'profitable only';
        }
        if (($f['status'] ?? '') !== '') {
            $bits[] = $f['status'];
        }

        return implode(' · ', $bits);
    }

    /**
     * @param list<array<string,mixed>>  $rows
     * @param array<int,array<string,mixed>> $summaries
     */
    private function exportXlsx(array $rows, array $summaries, string $subtitle)
    {
        $tR = $tC = $tN = $tJ = 0.0;
        $out = [];
        foreach ($rows as $j) {
            $s   = $summaries[$j['id']] ?? ['revenue' => 0, 'cost' => 0, 'net' => 0];
            $rev = (float) $s['revenue'];
            $cst = (float) $s['cost'];
            $net = (float) $s['net'];
            $has = ($j['sales_ref'] ?? null) !== null || ($j['buy_ref'] ?? null) !== null;
            $jn  = (float) ($j['sales_ref'] ?? 0) - (float) ($j['buy_ref'] ?? 0);
            $jm  = (float) ($j['sales_ref'] ?? 0) != 0.0 ? $jn / (float) $j['sales_ref'] * 100 : null;

            $out[] = [
                'code'       => $j['code'],
                'name'       => $j['name'],
                'customer'   => $j['customer_name'],
                'arrival'    => $j['start_date'],
                'revenue'    => $rev,
                'cost'       => $cst,
                'net'        => $net,
                'margin'     => $rev != 0.0 ? number_format($net / $rev * 100, 1) . '%' : '',
                'jbx_net'    => $has ? $jn : '',
                'jbx_margin' => $jm === null ? '' : number_format($jm, 1) . '%',
                'status'     => ucfirst((string) $j['status']),
            ];
            $tR += $rev;
            $tC += $cst;
            $tN += $net;
            $tJ += $has ? $jn : 0.0;
        }
        $out[] = [
            '_style' => 'total', 'code' => '', 'name' => 'TOTAL', 'customer' => '', 'arrival' => '',
            'revenue' => $tR, 'cost' => $tC, 'net' => $tN, 'margin' => $tR != 0.0 ? number_format($tN / $tR * 100, 1) . '%' : '',
            'jbx_net' => $tJ, 'jbx_margin' => '', 'status' => '',
        ];

        return ReportExporter::download([
            'title'   => 'Jobs',
            'meta'    => ['Company' => company_name(), 'Filter' => $subtitle, 'Generated' => date('Y-m-d H:i')],
            'columns' => [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'arrival', 'label' => 'Arrival'],
                ['key' => 'revenue', 'label' => 'Revenue', 'money' => true, 'align' => 'right'],
                ['key' => 'cost', 'label' => 'Cost', 'money' => true, 'align' => 'right'],
                ['key' => 'net', 'label' => 'Net', 'money' => true, 'align' => 'right'],
                ['key' => 'margin', 'label' => 'Margin', 'align' => 'right'],
                ['key' => 'jbx_net', 'label' => 'Jambix Net', 'money' => true, 'align' => 'right'],
                ['key' => 'jbx_margin', 'label' => 'Jambix %', 'align' => 'right'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $out,
        ]);
    }

    public function new()
    {
        if (! $this->guard()) {
            return redirect()->to('jobs')->with('error', 'Not allowed.');
        }

        return view('jobs/form', [
            'title'     => 'New Job',
            'job'       => null,
            'customers' => model(CustomerModel::class)->active(),
            'cfDefs'    => $this->cf->defs('job'),
            'cfValues'  => [],
        ]);
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to('jobs')->with('error', 'Not allowed.');
        }
        $data = $this->payload();
        if (! empty($data['code']) && $this->jobs->codeTaken($data['code'])) {
            return redirect()->back()->withInput()->with('errors', ['Job code ' . $data['code'] . ' is already used in this company.']);
        }
        $cf    = (array) $this->request->getPost('cf');
        $cfErr = $this->cf->validate('job', $cf);
        if ($cfErr) {
            return redirect()->back()->withInput()->with('errors', $cfErr);
        }
        if (! $this->jobs->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->jobs->errors());
        }
        $this->cf->save('job', (int) $this->jobs->getInsertID(), $cf);

        return redirect()->to('jobs')->with('message', 'Job created.');
    }

    public function edit(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('jobs')->with('error', 'Not allowed.');
        }
        $job = $this->jobs->find($id);
        if (! $job) {
            return redirect()->to('jobs')->with('error', 'Job not found.');
        }

        return view('jobs/form', [
            'title'     => 'Edit ' . $job['code'],
            'job'       => $job,
            'customers' => model(CustomerModel::class)->active(),
            'cfDefs'    => $this->cf->defs('job'),
            'cfValues'  => $this->cf->valuesFor('job', $id),
        ]);
    }

    public function update(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('jobs')->with('error', 'Not allowed.');
        }
        if (! $this->jobs->find($id)) {
            return redirect()->to('jobs')->with('error', 'Job not found.');
        }
        $data = $this->payload();
        if (! empty($data['code']) && $this->jobs->codeTaken($data['code'], $id)) {
            return redirect()->back()->withInput()->with('errors', ['Job code ' . $data['code'] . ' is already used in this company.']);
        }
        $cf    = (array) $this->request->getPost('cf');
        $cfErr = $this->cf->validate('job', $cf);
        if ($cfErr) {
            return redirect()->back()->withInput()->with('errors', $cfErr);
        }
        if (! $this->jobs->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->jobs->errors());
        }
        $this->cf->save('job', $id, $cf);

        return redirect()->to('jobs')->with('message', 'Job updated.');
    }

    public function show(int $id)
    {
        $job = $this->jobs->select('jobs.*, customers.name AS customer_name')
            ->join('customers', 'customers.id = jobs.customer_id', 'left')
            ->find($id);
        if (! $job) {
            return redirect()->to('jobs')->with('error', 'Job not found.');
        }

        $from = $this->request->getGet('from') ?: null;
        $to   = $this->request->getGet('to') ?: null;
        $ledger = new Ledger();

        return view('jobs/show', [
            'title'          => $job['code'] . ' — Profit & Loss',
            'job'            => $job,
            'from'           => $from,
            'to'             => $to,
            'pl'             => $ledger->jobProfitLoss($id, $from, $to),
            'partyBreakdown' => $ledger->jobPartyBreakdown($id, $from, $to),
            'cfDefs'         => $this->cf->defs('job'),
            'cfValues'       => $this->cf->valuesFor('job', $id),
        ]);
    }

    /**
     * Read-only JSON job search for the quick-look drawer on the purchase /
     * sales invoice forms. Matches code, name or customer; newest arrivals
     * first; capped so a blank query still returns something useful.
     */
    public function lookup()
    {
        if (! $this->guard()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden']);
        }

        $q = trim((string) $this->request->getGet('q'));

        $b = $this->jobs
            ->select('jobs.code, jobs.name, jobs.status, jobs.start_date, jobs.end_date, jobs.pax, customers.name AS customer_name')
            ->join('customers', 'customers.id = jobs.customer_id', 'left')
            ->orderBy('jobs.start_date', 'DESC')->orderBy('jobs.code', 'DESC');

        if ($q !== '') {
            $b->groupStart()
                ->like('jobs.code', $q)->orLike('jobs.name', $q)->orLike('customers.name', $q)
                ->groupEnd();
        }

        return $this->response->setJSON(['jobs' => $b->findAll(50)]);
    }

    public function toggle(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('jobs')->with('error', 'Not allowed.');
        }
        $job = $this->jobs->find($id);
        if ($job) {
            $this->jobs->update($id, ['status' => $job['status'] === 'open' ? 'closed' : 'open']);
        }

        return redirect()->to('jobs')->with('message', 'Job status changed.');
    }

    private function payload(): array
    {
        return [
            'code'        => trim((string) $this->request->getPost('code')),
            'name'        => trim((string) $this->request->getPost('name')),
            'customer_id' => $this->request->getPost('customer_id') ?: null,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'status'      => $this->request->getPost('status') === 'closed' ? 'closed' : 'open',
            'start_date'  => $this->request->getPost('start_date') ?: null,
            'end_date'    => $this->request->getPost('end_date') ?: null,
        ];
    }
}
