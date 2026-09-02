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

    // ---------------------------------------------------------------- payment list (by promise date)

    /**
     * Flat "what to pay" list: one row per unpaid purchase-invoice line, with
     * the supplier's bank details and the invoice's promise-date custom field.
     * Purchase only. Columns mirror the client's Excel "Data Payment" template;
     * all downstream grouping / approval logic stays in their workbook.
     */
    public function paymentList()
    {
        $f      = ReportFilter::resolve();
        $pdFrom = trim((string) $this->request->getGet('pd_from'));
        $pdTo   = trim((string) $this->request->getGet('pd_to'));
        $co     = (int) $this->co();

        $sql = "SELECT
                    i.invoice_date                       AS date,
                    cvpo.value_text                      AS po_no,
                    i.internal_no                        AS number,
                    s.name                               AS supplier,
                    s.code                               AS supplier_code,
                    jb.code                              AS dossier_code,
                    l.description                        AS description,
                    cur.code                             AS currency,
                    l.cost_remark                        AS remarks,
                    l.budget_amount                      AS budget,
                    i.paid_base                          AS paid,
                    (i.total_base - i.paid_base)         AS balance,
                    l.service_date                       AS service_date,
                    jb.name                              AS dossier_name,
                    cust.name                            AS client_name,
                    cvbn.value_text                      AS bank_name,
                    cvan.value_text                      AS bank_account_nr,
                    cvbf.value_text                      AS account_name,
                    s.email                              AS email,
                    i.description                        AS notes,
                    cvpd.value_date                      AS promise_date,
                    l.booking_ref                        AS booking_id,
                    l.job_id                             AS job_id,
                    cp.inv_count                         AS cp_inv_count,
                    cp.inv_received                      AS cp_inv_received,
                    cd.dep_unapplied                     AS cp_dep_unapplied
                FROM purchase_invoice_lines l
                JOIN purchase_invoices i        ON i.id = l.invoice_id
                LEFT JOIN suppliers s           ON s.id = i.supplier_id
                LEFT JOIN jobs jb               ON jb.id = l.job_id
                LEFT JOIN customers cust        ON cust.id = jb.customer_id
                LEFT JOIN currencies cur        ON cur.id = i.currency_id
                LEFT JOIN custom_values cvpd    ON cvpd.entity = 'purchase_invoice' AND cvpd.record_id = i.id AND cvpd.field_key = 'promise_date'
                LEFT JOIN custom_values cvpo    ON cvpo.entity = 'purchase_invoice' AND cvpo.record_id = i.id AND cvpo.field_key = 'po_number'
                LEFT JOIN custom_values cvbn    ON cvbn.entity = 'supplier' AND cvbn.record_id = s.id AND cvbn.field_key = 'bank_name'
                LEFT JOIN custom_values cvan    ON cvan.entity = 'supplier' AND cvan.record_id = s.id AND cvan.field_key = 'account_nr'
                LEFT JOIN custom_values cvbf    ON cvbf.entity = 'supplier' AND cvbf.record_id = s.id AND cvbf.field_key = 'beneficiary_name'
                LEFT JOIN (
                    SELECT sil.job_id,
                           COUNT(DISTINCT si.id) AS inv_count,
                           SUM(CASE WHEN si.received_base > 0.005 THEN 1 ELSE 0 END) AS inv_received
                    FROM sales_invoice_lines sil
                    JOIN sales_invoices si ON si.id = sil.invoice_id
                    WHERE si.company_id = {$co} AND si.status <> 'void' AND sil.job_id IS NOT NULL
                    GROUP BY sil.job_id
                ) cp ON cp.job_id = l.job_id
                LEFT JOIN (
                    SELECT customer_id, SUM(unapplied) AS dep_unapplied
                    FROM sales_receipts
                    WHERE company_id = {$co} AND kind = 'deposit' AND status = 'posted'
                    GROUP BY customer_id
                ) cd ON cd.customer_id = jb.customer_id
                WHERE i.company_id = ?
                  AND i.status IN ('posted', 'partial')
                  AND ABS(i.total_base - i.paid_base) > 0.005";
        $params = [$this->co()];
        if ($pdFrom !== '') {
            $sql .= " AND cvpd.value_date >= ?";
            $params[] = $pdFrom;
        }
        if ($pdTo !== '') {
            $sql .= " AND cvpd.value_date <= ?";
            $params[] = $pdTo;
        }
        $sql .= " ORDER BY s.name, i.internal_no, l.line_no";

        $rows     = $this->db->query($sql, $params)->getResultArray();
        $invoices = [];
        $out      = [];
        foreach ($rows as $r) {
            $invoices[$r['number']] = true;

            if (! $r['job_id']) {
                $clientPayment = '';                       // operational cost - no dossier
            } elseif ((int) $r['cp_inv_count'] === 0) {
                $clientPayment = 'No sales invoice';
            } elseif ((int) $r['cp_inv_received'] > 0) {
                $clientPayment = 'Paid';
            } elseif ((float) $r['cp_dep_unapplied'] > 0.005) {
                $clientPayment = 'Deposit unapplied';
            } else {
                $clientPayment = 'Not paid';
            }

            $out[] = [
                'date'            => $r['date'],
                'po_no'           => $r['po_no'],
                'number'          => $r['number'],
                'supplier'        => $r['supplier'],
                'supplier_code'   => $r['supplier_code'],
                'dossier_code'    => $r['dossier_code'],
                'description'     => $r['description'],
                'currency'        => $r['currency'],
                'remarks'         => $r['remarks'],
                'budget'          => $r['budget'],
                'paid'            => $r['paid'],
                'balance'         => $r['balance'],
                'service_date'    => $r['service_date'],
                'dossier_name'    => $r['dossier_name'],
                'client_name'     => $r['client_name'],
                'bank_name'       => $r['bank_name'],
                'bank_account_nr' => $r['bank_account_nr'],
                'account_name'    => $r['account_name'],
                'email'           => $r['email'],
                'notes'           => $r['notes'],
                'promise_date'    => $r['promise_date'],
                'booking_id'      => $r['booking_id'],
                'category'        => $r['job_id'] ? 'COS' : 'Operational',
                'client_payment'  => $clientPayment,
            ];
        }

        $cols = [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'po_no', 'label' => 'PO No'],
            ['key' => 'number', 'label' => 'Number'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'supplier_code', 'label' => 'Supplier ID'],
            ['key' => 'dossier_code', 'label' => 'Code#'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'currency', 'label' => 'Code Currency'],
            ['key' => 'remarks', 'label' => 'Remarks'],
            ['key' => 'budget', 'label' => 'Budget', 'money' => true, 'blankZero' => true],
            ['key' => 'paid', 'label' => 'Payment', 'money' => true, 'blankZero' => true],
            ['key' => 'balance', 'label' => 'Balance', 'money' => true],
            ['key' => 'service_date', 'label' => 'Service Date'],
            ['key' => 'dossier_name', 'label' => 'Dossier Name'],
            ['key' => 'client_name', 'label' => 'Client Name'],
            ['key' => 'bank_name', 'label' => 'Bank Name'],
            ['key' => 'bank_account_nr', 'label' => 'Bank Account Nr'],
            ['key' => 'account_name', 'label' => 'Account Name'],
            ['key' => 'email', 'label' => 'Email Contact Info Supplier'],
            ['key' => 'notes', 'label' => 'Notes'],
            ['key' => 'promise_date', 'label' => 'Promise Date'],
            ['key' => 'booking_id', 'label' => 'Booking ID'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'client_payment', 'label' => 'Client Payment'],
        ];

        // the promise-date range replaces the year/period widget for this report
        $f['label'] = 'Promise date';
        $f['from']  = $pdFrom !== '' ? $pdFrom : '2000-01-01';
        $f['to']    = $pdTo !== '' ? $pdTo : date('Y-m-d');

        $extra = '<div class="field" style="max-width:150px"><label>Promise from</label>'
            . '<input type="date" name="pd_from" value="' . esc($pdFrom, 'attr') . '"></div>'
            . '<div class="field" style="max-width:150px"><label>Promise to</label>'
            . '<input type="date" name="pd_to" value="' . esc($pdTo, 'attr') . '"></div>';

        $sub = count($out) . ' line(s) · ' . count($invoices) . ' invoice(s) to pay'
            . ($pdFrom !== '' || $pdTo !== '' ? ' · promise ' . ($pdFrom ?: '…') . ' – ' . ($pdTo ?: '…') : '');

        return $this->respond('Payment List', $f, $cols, $out, ['extra' => $extra, 'hidePeriodPickers' => true], $sub);
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
