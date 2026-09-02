<?php

namespace App\Controllers;

use App\Libraries\Report\ReportExporter;
use App\Libraries\Report\ReportFilter;
use Config\Database;

/**
 * Sales and Purchase list reports. `kind` (sales|purchase) comes from the
 * route so one controller serves both mirrored sets.
 */
class TradeReportController extends BaseController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /** @return array<string,string> */
    private function cfg(string $kind): array
    {
        return $kind === 'purchase'
            ? [
                'inv' => 'purchase_invoices', 'line' => 'purchase_invoice_lines',
                'pay' => 'purchase_payments', 'alloc' => 'purchase_payment_allocations', 'allocFk' => 'payment_id',
                'party' => 'suppliers', 'pid' => 'supplier_id', 'ref' => 'supplier_ref',
                'paid' => 'paid_base', 'payNo' => 'payment_no', 'payDate' => 'payment_date',
                'pLabel' => 'Supplier', 'noun' => 'Purchase', 'payNoun' => 'Payment',
            ]
            : [
                'inv' => 'sales_invoices', 'line' => 'sales_invoice_lines',
                'pay' => 'sales_receipts', 'alloc' => 'sales_receipt_allocations', 'allocFk' => 'receipt_id',
                'party' => 'customers', 'pid' => 'customer_id', 'ref' => 'customer_ref',
                'paid' => 'received_base', 'payNo' => 'receipt_no', 'payDate' => 'receipt_date',
                'pLabel' => 'Customer', 'noun' => 'Sales', 'payNoun' => 'Receipt',
            ];
    }

    private function respond(string $title, array $f, array $columns, array $rows, array $periodOpts = [], string $subtitle = '')
    {
        if ($this->request->getGet('format') === 'xlsx') {
            return ReportExporter::download([
                'title'   => $title,
                'meta'    => ['Company' => company_name(), 'Period' => $f['label'] . '  (' . $f['from'] . ' — ' . $f['to'] . ')'],
                'columns' => $columns,
                'rows'    => $rows,
            ]);
        }

        return view('reports/_list', compact('title', 'f', 'columns', 'rows', 'periodOpts', 'subtitle'));
    }

    private function co(): int
    {
        return active_company_id();
    }

    // ---------------------------------------------------------------- register

    public function register(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['inv'] . ' i')
            ->select("p.name AS party, i.internal_no, i.invoice_date, i.{$c['ref']} AS ref,
                      i.subtotal, i.ppn_amount, i.pph_amount, i.total_base")
            ->join($c['party'] . ' p', "p.id = i.{$c['pid']}", 'left')
            ->where('i.company_id', $this->co())
            ->where('i.status !=', 'draft')
            ->where('i.invoice_date >=', $f['from'])->where('i.invoice_date <=', $f['to'])
            ->orderBy('p.name', 'ASC')->orderBy('i.invoice_date', 'ASC')
            ->get()->getResultArray();

        $out       = [];
        $curParty  = null;
        $sub       = 0.0;
        $grand     = 0.0;
        foreach ($rows as $r) {
            if ($r['party'] !== $curParty) {
                if ($curParty !== null) {
                    $out[] = ['_style' => 'subtotal', 'no' => 'Subtotal ' . $curParty, 'total' => $sub];
                }
                $curParty = $r['party'];
                $sub      = 0.0;
                $out[]    = ['_style' => 'section', '_label' => $r['party'] ?: '(no ' . strtolower($c['pLabel']) . ')'];
            }
            $out[] = ['no' => $r['internal_no'], 'date' => date_id($r['invoice_date']), 'ref' => $r['ref'],
                'st' => $r['subtotal'], 'ppn' => $r['ppn_amount'], 'pph' => $r['pph_amount'], 'total' => $r['total_base']];
            $sub   += (float) $r['total_base'];
            $grand += (float) $r['total_base'];
        }
        if ($curParty !== null) {
            $out[] = ['_style' => 'subtotal', 'no' => 'Subtotal ' . $curParty, 'total' => $sub];
        }
        $out[] = ['_style' => 'total', 'no' => 'GRAND TOTAL', 'total' => $grand];

        return $this->respond($c['noun'] . ' Register', $f, [
            ['key' => 'no', 'label' => 'No.'], ['key' => 'date', 'label' => 'Date'], ['key' => 'ref', 'label' => 'Ref'],
            ['key' => 'st', 'label' => 'Subtotal', 'money' => true, 'blankZero' => true],
            ['key' => 'ppn', 'label' => 'PPN', 'money' => true, 'blankZero' => true],
            ['key' => 'pph', 'label' => 'PPh', 'money' => true, 'blankZero' => true],
            ['key' => 'total', 'label' => 'Total (Rp)', 'money' => true],
        ], $out);
    }

    // ---------------------------------------------------------------- monthly

    public function monthly(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['inv'] . ' i')
            ->select("p.name AS party, MONTH(i.invoice_date) AS m, SUM(i.total_base) AS t")
            ->join($c['party'] . ' p', "p.id = i.{$c['pid']}", 'left')
            ->where('i.company_id', $this->co())
            ->where('i.status !=', 'draft')
            ->where('YEAR(i.invoice_date)', $f['year'])
            ->groupBy(['p.id', 'm'])
            ->orderBy('p.name', 'ASC')
            ->get()->getResultArray();

        $byParty = [];
        foreach ($rows as $r) {
            $byParty[$r['party']] ??= array_fill(1, 12, 0.0) + ['t' => 0.0];
            $byParty[$r['party']][(int) $r['m']] += (float) $r['t'];
            $byParty[$r['party']]['t']           += (float) $r['t'];
        }
        ksort($byParty);

        $cols = [['key' => 'party', 'label' => $c['pLabel']]];
        for ($m = 1; $m <= 12; $m++) {
            $cols[] = ['key' => 'm' . $m, 'label' => date('M', mktime(0, 0, 0, $m, 1)), 'money' => true, 'blankZero' => true];
        }
        $cols[] = ['key' => 't', 'label' => 'Total', 'money' => true];

        $out    = [];
        $totRow = ['_style' => 'total', 'party' => 'TOTAL', 't' => 0.0];
        foreach ($byParty as $party => $vals) {
            $row = ['party' => $party, 't' => $vals['t']];
            for ($m = 1; $m <= 12; $m++) {
                $row['m' . $m]    = $vals[$m];
                $totRow['m' . $m] = ($totRow['m' . $m] ?? 0) + $vals[$m];
            }
            $totRow['t'] += $vals['t'];
            $out[]        = $row;
        }
        $out[] = $totRow;

        return $this->respond($c['noun'] . ' — Monthly by ' . $c['pLabel'] . ' (' . $f['year'] . ')', $f, $cols, $out, ['showCompare' => false]);
    }

    // ---------------------------------------------------------------- outstanding

    public function outstanding(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['inv'] . ' i')
            ->select("i.internal_no, i.invoice_date, i.due_date, p.name AS party,
                      i.total_base, i.{$c['paid']} AS paid, (i.total_base - i.{$c['paid']}) AS outstanding")
            ->join($c['party'] . ' p', "p.id = i.{$c['pid']}", 'left')
            ->where('i.company_id', $this->co())
            ->whereIn('i.status', ['posted', 'partial'])
            ->where('i.invoice_date <=', $f['to'])
            ->where('(i.total_base - i.' . $c['paid'] . ') >', 0.005)
            ->orderBy('p.name', 'ASC')->orderBy('i.due_date', 'ASC')
            ->get()->getResultArray();

        $today = strtotime($f['to']);
        $out   = [];
        $tot   = 0.0;
        foreach ($rows as $r) {
            $due  = $r['due_date'] ?: $r['invoice_date'];
            $days = (int) floor(($today - strtotime($due)) / 86400);
            $out[] = ['no' => $r['internal_no'], 'date' => date_id($r['invoice_date']), 'due' => date_id($r['due_date']),
                'party' => $r['party'], 'total' => $r['total_base'], 'paid' => $r['paid'],
                'os' => $r['outstanding'], 'days' => $days > 0 ? $days . 'd' : ''];
            $tot += (float) $r['outstanding'];
        }
        $out[] = ['_style' => 'total', 'party' => 'TOTAL OUTSTANDING', 'os' => $tot];

        return $this->respond('Outstanding ' . $c['noun'] . ' Invoices', $f, [
            ['key' => 'no', 'label' => 'No.'], ['key' => 'date', 'label' => 'Date'], ['key' => 'due', 'label' => 'Due'],
            ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'total', 'label' => 'Total', 'money' => true],
            ['key' => 'paid', 'label' => $kind === 'purchase' ? 'Paid' : 'Received', 'money' => true, 'blankZero' => true],
            ['key' => 'os', 'label' => 'Outstanding', 'money' => true],
            ['key' => 'days', 'label' => 'Overdue', 'align' => 'right'],
        ], $out, ['showAsOf' => false]);
    }

    // ---------------------------------------------------------------- line detail

    public function detail(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['line'] . ' l')
            ->select('i.internal_no, i.invoice_date, p.name AS party, a.code AS acc_code, a.name AS acc_name,
                      jb.code AS job, l.description, l.amount_base')
            ->join($c['inv'] . ' i', 'i.id = l.invoice_id')
            ->join($c['party'] . ' p', "p.id = i.{$c['pid']}", 'left')
            ->join('accounts a', 'a.id = l.account_id', 'left')
            ->join('jobs jb', 'jb.id = l.job_id', 'left')
            ->where('i.company_id', $this->co())
            ->where('i.status !=', 'draft')
            ->where('i.invoice_date >=', $f['from'])->where('i.invoice_date <=', $f['to'])
            ->orderBy('i.invoice_date', 'ASC')->orderBy('i.internal_no', 'ASC')->orderBy('l.line_no', 'ASC')
            ->get()->getResultArray();

        $out = [];
        $tot = 0.0;
        foreach ($rows as $r) {
            $out[] = ['no' => $r['internal_no'], 'date' => date_id($r['invoice_date']), 'party' => $r['party'],
                'acc' => trim($r['acc_code'] . ' ' . $r['acc_name']), 'job' => $r['job'],
                'desc' => $r['description'], 'amt' => $r['amount_base']];
            $tot += (float) $r['amount_base'];
        }
        $out[] = ['_style' => 'total', 'desc' => 'TOTAL', 'amt' => $tot];

        return $this->respond($c['noun'] . ' Invoice Detail', $f, [
            ['key' => 'no', 'label' => 'Invoice'], ['key' => 'date', 'label' => 'Date'], ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'acc', 'label' => 'Account'], ['key' => 'job', 'label' => 'Job'],
            ['key' => 'desc', 'label' => 'Description'], ['key' => 'amt', 'label' => 'Amount (Rp)', 'money' => true],
        ], $out);
    }

    // ---------------------------------------------------------------- payment / receipt list

    public function payments(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['pay'] . ' pp')
            ->select("pp.{$c['payNo']} AS no, pp.{$c['payDate']} AS pdate, p.name AS party,
                      ba.name AS bank, pp.amount_base, pp.reference, pp.status")
            ->join($c['party'] . ' p', "p.id = pp.{$c['pid']}", 'left')
            ->join('accounts ba', 'ba.id = pp.bank_account_id', 'left')
            ->where('pp.company_id', $this->co())
            ->where("pp.{$c['payDate']} >=", $f['from'])->where("pp.{$c['payDate']} <=", $f['to'])
            ->orderBy("pp.{$c['payDate']}", 'ASC')
            ->get()->getResultArray();

        $out = [];
        $tot = 0.0;
        foreach ($rows as $r) {
            $out[] = ['no' => $r['no'], 'date' => date_id($r['pdate']), 'party' => $r['party'],
                'bank' => $r['bank'], 'amt' => $r['amount_base'], 'ref' => $r['reference'], 'status' => ucfirst($r['status'])];
            if ($r['status'] !== 'void') {
                $tot += (float) $r['amount_base'];
            }
        }
        $out[] = ['_style' => 'total', 'party' => 'TOTAL (excl. void)', 'amt' => $tot];

        return $this->respond($c['payNoun'] . ' List', $f, [
            ['key' => 'no', 'label' => 'No.'], ['key' => 'date', 'label' => 'Date'], ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'bank', 'label' => 'Bank'], ['key' => 'amt', 'label' => 'Amount (Rp)', 'money' => true],
            ['key' => 'ref', 'label' => 'Reference'], ['key' => 'status', 'label' => 'Status'],
        ], $out);
    }

    // ---------------------------------------------------------------- invoice paid (allocations)

    public function invoicePaid(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['alloc'] . ' al')
            ->select("pp.{$c['payNo']} AS payno, pp.{$c['payDate']} AS pdate, i.internal_no AS inv,
                      p.name AS party, al.amount_base")
            ->join($c['pay'] . ' pp', "pp.id = al.{$c['allocFk']}")
            ->join($c['inv'] . ' i', 'i.id = al.invoice_id')
            ->join($c['party'] . ' p', "p.id = pp.{$c['pid']}", 'left')
            ->where('pp.company_id', $this->co())
            ->where('pp.status', 'posted')
            ->where("pp.{$c['payDate']} >=", $f['from'])->where("pp.{$c['payDate']} <=", $f['to'])
            ->orderBy("pp.{$c['payDate']}", 'ASC')
            ->get()->getResultArray();

        $out = [];
        $tot = 0.0;
        foreach ($rows as $r) {
            $out[] = ['payno' => $r['payno'], 'date' => date_id($r['pdate']), 'inv' => $r['inv'],
                'party' => $r['party'], 'amt' => $r['amount_base']];
            $tot += (float) $r['amount_base'];
        }
        $out[] = ['_style' => 'total', 'party' => 'TOTAL', 'amt' => $tot];

        return $this->respond($c['noun'] . ' Invoice Paid', $f, [
            ['key' => 'payno', 'label' => $c['payNoun'] . ' No.'], ['key' => 'date', 'label' => 'Date'],
            ['key' => 'inv', 'label' => 'Invoice'], ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'amt', 'label' => 'Applied (Rp)', 'money' => true],
        ], $out);
    }

    // ---------------------------------------------------------------- party list

    public function parties(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['party'] . ' p')
            ->select("p.code, p.name, p.email, p.phone, p.npwp, p.is_active,
                (SELECT COALESCE(SUM(i.total_base - i.{$c['paid']}),0) FROM {$c['inv']} i
                 WHERE i.{$c['pid']} = p.id AND i.status IN ('posted','partial')) AS balance")
            ->where('p.company_id', $this->co())
            ->orderBy('p.name', 'ASC')
            ->get()->getResultArray();

        $out = [];
        $tot = 0.0;
        foreach ($rows as $r) {
            $out[] = ['code' => $r['code'], 'name' => $r['name'], 'email' => $r['email'],
                'phone' => $r['phone'], 'npwp' => $r['npwp'],
                'status' => $r['is_active'] ? 'Active' : 'Inactive', 'bal' => $r['balance']];
            $tot += (float) $r['balance'];
        }
        $out[] = ['_style' => 'total', 'name' => 'TOTAL', 'bal' => $tot];

        return $this->respond($c['pLabel'] . ' List', $f, [
            ['key' => 'code', 'label' => 'Code'], ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'], ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'npwp', 'label' => 'NPWP'], ['key' => 'status', 'label' => 'Status'],
            ['key' => 'bal', 'label' => ($kind === 'purchase' ? 'Payable' : 'Receivable') . ' (Rp)', 'money' => true],
        ], $out, ['showCompare' => false]);
    }

    // ---------------------------------------------------------------- aging detail (per invoice)

    public function agingDetail(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['inv'] . ' i')
            ->select("i.internal_no, i.invoice_date, i.due_date, p.name AS party,
                      (i.total_base - i.{$c['paid']}) AS outstanding")
            ->join($c['party'] . ' p', "p.id = i.{$c['pid']}", 'left')
            ->where('i.company_id', $this->co())
            ->whereIn('i.status', ['posted', 'partial'])
            ->where('i.invoice_date <=', $f['asOf'])
            ->where('(i.total_base - i.' . $c['paid'] . ') >', 0.005)
            ->orderBy('p.name', 'ASC')->orderBy('i.due_date', 'ASC')
            ->get()->getResultArray();

        $asOf     = strtotime($f['asOf']);
        $out      = [];
        $curParty = null;
        $sub      = ['cur' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0, 'tot' => 0];
        $grand    = $sub;
        $emit     = static function (string $label, array $s) {
            return ['_style' => 'subtotal', 'inv' => $label, 'cur' => $s['cur'], 'b30' => $s['b30'],
                'b60' => $s['b60'], 'b90' => $s['b90'], 'b90p' => $s['b90p'], 'tot' => $s['tot']];
        };

        foreach ($rows as $r) {
            if ($r['party'] !== $curParty) {
                if ($curParty !== null) {
                    $out[] = $emit('Subtotal ' . $curParty, $sub);
                }
                $curParty = $r['party'];
                $sub      = ['cur' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0, 'tot' => 0];
                $out[]    = ['_style' => 'section', '_label' => $r['party'] ?: '(no ' . strtolower($c['pLabel']) . ')'];
            }
            $due  = $r['due_date'] ?: $r['invoice_date'];
            $age  = (int) floor(($asOf - strtotime($due)) / 86400);
            $bk   = $age <= 0 ? 'cur' : ($age <= 30 ? 'b30' : ($age <= 60 ? 'b60' : ($age <= 90 ? 'b90' : 'b90p')));
            $amt  = (float) $r['outstanding'];
            $row  = ['inv' => $r['internal_no'], 'date' => date_id($r['invoice_date']), 'due' => date_id($r['due_date']),
                'cur' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0, 'tot' => $amt];
            $row[$bk]         = $amt;
            $sub[$bk]        += $amt;
            $sub['tot']      += $amt;
            $grand[$bk]      += $amt;
            $grand['tot']    += $amt;
            $out[]            = $row;
        }
        if ($curParty !== null) {
            $out[] = $emit('Subtotal ' . $curParty, $sub);
        }
        $out[] = $emit('GRAND TOTAL', $grand) + ['_style' => 'total'];

        return $this->respond(($kind === 'purchase' ? 'AP' : 'AR') . ' Aging — Detail', $f, [
            ['key' => 'inv', 'label' => 'Invoice'], ['key' => 'date', 'label' => 'Date'], ['key' => 'due', 'label' => 'Due'],
            ['key' => 'cur', 'label' => 'Current', 'money' => true, 'blankZero' => true],
            ['key' => 'b30', 'label' => '1-30', 'money' => true, 'blankZero' => true],
            ['key' => 'b60', 'label' => '31-60', 'money' => true, 'blankZero' => true],
            ['key' => 'b90', 'label' => '61-90', 'money' => true, 'blankZero' => true],
            ['key' => 'b90p', 'label' => '> 90', 'money' => true, 'blankZero' => true],
            ['key' => 'tot', 'label' => 'Total', 'money' => true],
        ], $out, ['showAsOf' => true]);
    }
}
