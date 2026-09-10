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
                'pLabel' => $this->rlang('col_supplier', 'Supplier'), 'noun' => $this->rlang('noun_purchase', 'Purchase'),
                'payNoun' => $this->rlang('noun_payment', 'Payment'), 'k' => 'p',
            ]
            : [
                'inv' => 'sales_invoices', 'line' => 'sales_invoice_lines',
                'pay' => 'sales_receipts', 'alloc' => 'sales_receipt_allocations', 'allocFk' => 'receipt_id',
                'party' => 'customers', 'pid' => 'customer_id', 'ref' => 'customer_ref',
                'paid' => 'received_base', 'payNo' => 'receipt_no', 'payDate' => 'receipt_date',
                'pLabel' => $this->rlang('col_customer', 'Customer'), 'noun' => $this->rlang('noun_sales', 'Sales'),
                'payNoun' => $this->rlang('noun_receipt', 'Receipt'), 'k' => 's',
            ];
    }

    /** Localised report string, falling back to English. */
    private function rlang(string $key, string $fallback): string
    {
        $s = lang('Report.' . $key);

        return $s === 'Report.' . $key ? $fallback : $s;
    }

    /** A `<select>` field for the `_period` `$extra` slot. `$options` = value => label. */
    private function selectField(string $label, string $name, array $options, string $current): string
    {
        $opts = '';
        foreach ($options as $val => $text) {
            $sel = (string) $val === $current ? ' selected' : '';
            $opts .= '<option value="' . esc((string) $val, 'attr') . '"' . $sel . '>' . esc($text) . '</option>';
        }

        return '<div class="field" style="max-width:200px"><label>' . esc($label) . '</label>'
            . '<select name="' . esc($name, 'attr') . '">' . $opts . '</select></div>';
    }

    /**
     * Traveller count per customer for a date window, sourced from the dossier
     * (jobs.pax) and counted once per job — a job's pax is not multiplied by how
     * many sales lines reference it. Keyed by customer name (matching `monthly`).
     *
     * @return array<string,int>
     */
    private function paxByCustomer(string $from, string $to, string $clientGroup = ''): array
    {
        $sql = "SELECT c.name AS party, SUM(t.pax) AS pax
                FROM (
                    SELECT DISTINCT j.id, j.pax, i.customer_id
                    FROM sales_invoice_lines sl
                    JOIN sales_invoices i ON i.id = sl.invoice_id
                    JOIN jobs j           ON j.id = sl.job_id
                    WHERE i.company_id = ? AND i.status <> 'draft' AND j.pax IS NOT NULL
                      AND i.invoice_date >= ? AND i.invoice_date <= ?
                ) t
                JOIN customers c ON c.id = t.customer_id";
        $params = [$this->co(), $from, $to];
        if ($clientGroup !== '') {
            $sql .= ' WHERE c.client_group = ?';
            $params[] = $clientGroup;
        }
        $sql .= ' GROUP BY c.id';

        $out = [];
        foreach ($this->db->query($sql, $params)->getResultArray() as $r) {
            $out[$r['party']] = (int) $r['pax'];
        }

        return $out;
    }

    /** Distinct non-empty customers.client_group for the active company. */
    private function clientGroups(): array
    {
        $rows = $this->db->table('customers')
            ->distinct()->select('client_group')
            ->where('company_id', $this->co())
            ->where('client_group IS NOT NULL')->where("client_group <>", '')
            ->orderBy('client_group', 'ASC')
            ->get()->getResultArray();

        return array_column($rows, 'client_group');
    }

    private function respond(string $title, array $f, array $columns, array $rows, array $periodOpts = [], string $subtitle = '')
    {
        if ($this->request->getGet('format') === 'xlsx') {
            return ReportExporter::download([
                'title'   => $title,
                'meta'    => ['Company' => company_legal_name(), 'Period' => $f['label'] . '  (' . $f['from'] . ' — ' . $f['to'] . ')'],
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
            ->select("p.name AS party, i.internal_no, i.invoice_date, i.doc_type, i.{$c['ref']} AS ref,
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
            $sgn   = ($r['doc_type'] ?? 'invoice') === 'credit_note' ? -1 : 1;
            $out[] = ['no' => $r['internal_no'] . ($sgn < 0 ? ' · CN' : ''), 'date' => date_id($r['invoice_date']), 'ref' => $r['ref'],
                'st' => $sgn * (float) $r['subtotal'], 'ppn' => $sgn * (float) $r['ppn_amount'], 'pph' => $sgn * (float) $r['pph_amount'], 'total' => $sgn * (float) $r['total_base']];
            $sub   += $sgn * (float) $r['total_base'];
            $grand += $sgn * (float) $r['total_base'];
        }
        if ($curParty !== null) {
            $out[] = ['_style' => 'subtotal', 'no' => 'Subtotal ' . $curParty, 'total' => $sub];
        }
        $out[] = ['_style' => 'total', 'no' => 'GRAND TOTAL', 'total' => $grand];

        return $this->respond($this->rlang($c['k'] . '-register', $c['noun'] . ' Register'), $f, [
            ['key' => 'no', 'label' => $this->rlang('col_no', 'No.')], ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'ref', 'label' => $this->rlang('col_ref', 'Ref')],
            ['key' => 'st', 'label' => $this->rlang('col_subtotal', 'Subtotal'), 'money' => true, 'blankZero' => true],
            ['key' => 'ppn', 'label' => 'PPN', 'money' => true, 'blankZero' => true],
            ['key' => 'pph', 'label' => 'PPh', 'money' => true, 'blankZero' => true],
            ['key' => 'total', 'label' => $this->rlang('col_total', 'Total') . ' (' . base_code() . ')', 'money' => true],
        ], $out);
    }

    // ---------------------------------------------------------------- monthly

    public function monthly(string $kind)
    {
        $c       = $this->cfg($kind);
        $f       = ReportFilter::resolve();
        $isSales = $kind === 'sales';
        $group   = $isSales ? trim((string) $this->request->getGet('client_group')) : '';

        $q = $this->db->table($c['inv'] . ' i')
            ->select("p.name AS party, MONTH(i.invoice_date) AS m, SUM((CASE WHEN i.doc_type = 'credit_note' THEN -1 ELSE 1 END) * i.total_base) AS t")
            ->join($c['party'] . ' p', "p.id = i.{$c['pid']}", 'left')
            ->where('i.company_id', $this->co())
            ->where('i.status !=', 'draft')
            ->where('YEAR(i.invoice_date)', $f['year'])
            ->groupBy(['p.id', 'm'])
            ->orderBy('p.name', 'ASC');
        if ($group !== '') {
            $q->where('p.client_group', $group);
        }
        $rows = $q->get()->getResultArray();

        $byParty = [];
        foreach ($rows as $r) {
            $byParty[$r['party']] ??= array_fill(1, 12, 0.0) + ['t' => 0.0];
            $byParty[$r['party']][(int) $r['m']] += (float) $r['t'];
            $byParty[$r['party']]['t']           += (float) $r['t'];
        }
        ksort($byParty);

        $pax = $isSales
            ? $this->paxByCustomer($f['year'] . '-01-01', $f['year'] . '-12-31', $group)
            : [];

        $cols = [['key' => 'party', 'label' => $c['pLabel']]];
        for ($m = 1; $m <= 12; $m++) {
            $cols[] = ['key' => 'm' . $m, 'label' => date('M', mktime(0, 0, 0, $m, 1)), 'money' => true, 'blankZero' => true];
        }
        $cols[] = ['key' => 't', 'label' => $this->rlang('col_total', 'Total'), 'money' => true];
        if ($isSales) {
            $cols[] = ['key' => 'pax', 'label' => $this->rlang('col_pax', 'Pax')];
            $cols[] = ['key' => 'avg', 'label' => $this->rlang('col_avg_pax', 'Avg rev / pax'), 'money' => true, 'blankZero' => true];
        }

        $out     = [];
        $totRow  = ['_style' => 'total', 'party' => 'TOTAL', 't' => 0.0];
        $totPax  = 0;
        foreach ($byParty as $party => $vals) {
            $row = ['party' => $party, 't' => $vals['t']];
            for ($m = 1; $m <= 12; $m++) {
                $row['m' . $m]    = $vals[$m];
                $totRow['m' . $m] = ($totRow['m' . $m] ?? 0) + $vals[$m];
            }
            $totRow['t'] += $vals['t'];
            if ($isSales) {
                $px         = $pax[$party] ?? 0;
                $row['pax'] = $px ?: '';
                $row['avg'] = $px ? $vals['t'] / $px : '';
                $totPax    += $px;
            }
            $out[] = $row;
        }
        if ($isSales) {
            $totRow['pax'] = $totPax ?: '';
            $totRow['avg'] = $totPax ? $totRow['t'] / $totPax : '';
        }
        $out[] = $totRow;

        $opts = ['showCompare' => false];
        if ($isSales && ($groups = $this->clientGroups())) {
            $opts['extra'] = $this->selectField(
                $this->rlang('col_client_group', 'Client group'),
                'client_group',
                ['' => '(all)'] + array_combine($groups, $groups),
                $group
            );
        }

        return $this->respond($this->rlang($c['k'] . '-monthly', $c['noun'] . ' Monthly') . ' — ' . $c['pLabel'] . ' (' . $f['year'] . ')', $f, $cols, $out, $opts);
    }

    // ---------------------------------------------------------------- outstanding

    public function outstanding(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['inv'] . ' i')
            ->select("i.internal_no, i.invoice_date, i.due_date, i.doc_type, p.name AS party,
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
            $sgn  = ($r['doc_type'] ?? 'invoice') === 'credit_note' ? -1 : 1;
            $out[] = ['no' => $r['internal_no'] . ($sgn < 0 ? ' · CN' : ''), 'date' => date_id($r['invoice_date']), 'due' => date_id($r['due_date']),
                'party' => $r['party'], 'total' => $sgn * (float) $r['total_base'], 'paid' => $sgn * (float) $r['paid'],
                'os' => $sgn * (float) $r['outstanding'], 'days' => $sgn > 0 && $days > 0 ? $days . 'd' : ''];
            $tot += $sgn * (float) $r['outstanding'];
        }
        $out[] = ['_style' => 'total', 'party' => 'TOTAL OUTSTANDING', 'os' => $tot];

        return $this->respond($this->rlang('outstanding_' . $c['k'], 'Outstanding ' . $c['noun'] . ' Invoices'), $f, [
            ['key' => 'no', 'label' => $this->rlang('col_no', 'No.')], ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'due', 'label' => $this->rlang('col_due', 'Due')],
            ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'total', 'label' => $this->rlang('col_total', 'Total'), 'money' => true],
            ['key' => 'paid', 'label' => $kind === 'purchase' ? $this->rlang('col_paid', 'Paid') : $this->rlang('col_received', 'Received'), 'money' => true, 'blankZero' => true],
            ['key' => 'os', 'label' => $this->rlang('col_outstanding', 'Outstanding'), 'money' => true],
            ['key' => 'days', 'label' => $this->rlang('col_overdue', 'Overdue'), 'align' => 'right'],
        ], $out, ['showAsOf' => false]);
    }

    // ---------------------------------------------------------------- line detail

    public function detail(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['line'] . ' l')
            ->select('i.internal_no, i.invoice_date, i.doc_type, p.name AS party, a.code AS acc_code, a.name AS acc_name,
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
            $sgn   = ($r['doc_type'] ?? 'invoice') === 'credit_note' ? -1 : 1;
            $out[] = ['no' => $r['internal_no'] . ($sgn < 0 ? ' · CN' : ''), 'date' => date_id($r['invoice_date']), 'party' => $r['party'],
                'acc' => trim($r['acc_code'] . ' ' . $r['acc_name']), 'job' => $r['job'],
                'desc' => $r['description'], 'amt' => $sgn * (float) $r['amount_base']];
            $tot += $sgn * (float) $r['amount_base'];
        }
        $out[] = ['_style' => 'total', 'desc' => 'TOTAL', 'amt' => $tot];

        return $this->respond($this->rlang($c['k'] . '-detail', $c['noun'] . ' Invoice Detail'), $f, [
            ['key' => 'no', 'label' => $this->rlang('col_invoice', 'Invoice')], ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'acc', 'label' => $this->rlang('col_account', 'Account')], ['key' => 'job', 'label' => $this->rlang('col_job', 'Job')],
            ['key' => 'desc', 'label' => $this->rlang('col_description', 'Description')], ['key' => 'amt', 'label' => 'Amount (Rp)', 'money' => true],
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

        return $this->respond($this->rlang($c['k'] === 'p' ? 'p-payments' : 's-receipts', $c['payNoun'] . ' List'), $f, [
            ['key' => 'no', 'label' => $this->rlang('col_no', 'No.')], ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'bank', 'label' => $this->rlang('col_bank', 'Bank')], ['key' => 'amt', 'label' => 'Amount (Rp)', 'money' => true],
            ['key' => 'ref', 'label' => $this->rlang('col_reference', 'Reference')], ['key' => 'status', 'label' => $this->rlang('col_status', 'Status')],
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
                  AND i.doc_type <> 'credit_note'
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
                $clientPayment = $this->rlang('pl_no_sales_invoice', 'No sales invoice');
            } elseif ((int) $r['cp_inv_received'] > 0) {
                $clientPayment = $this->rlang('col_paid', 'Paid');
            } elseif ((float) $r['cp_dep_unapplied'] > 0.005) {
                $clientPayment = $this->rlang('pl_deposit_unapplied', 'Deposit unapplied');
            } else {
                $clientPayment = $this->rlang('pl_not_paid', 'Not paid');
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
                'category'        => $r['job_id'] ? 'COS' : $this->rlang('pl_operational', 'Operational'),
                'client_payment'  => $clientPayment,
            ];
        }

        $cols = [
            ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')],
            ['key' => 'po_no', 'label' => $this->rlang('col_pl_po_no', 'PO No')],
            ['key' => 'number', 'label' => $this->rlang('col_pl_number', 'Number')],
            ['key' => 'supplier', 'label' => $this->rlang('col_supplier', 'Supplier')],
            ['key' => 'supplier_code', 'label' => $this->rlang('col_pl_supplier_code', 'Supplier ID')],
            ['key' => 'dossier_code', 'label' => $this->rlang('col_pl_dossier_code', 'Code#')],
            ['key' => 'description', 'label' => $this->rlang('col_description', 'Description')],
            ['key' => 'currency', 'label' => $this->rlang('col_pl_currency', 'Currency Code')],
            ['key' => 'remarks', 'label' => $this->rlang('col_pl_remarks', 'Remarks')],
            ['key' => 'budget', 'label' => $this->rlang('col_pl_budget', 'Budget'), 'money' => true, 'blankZero' => true],
            ['key' => 'paid', 'label' => $this->rlang('col_pl_payment', 'Payment'), 'money' => true, 'blankZero' => true],
            ['key' => 'balance', 'label' => $this->rlang('col_balance', 'Balance'), 'money' => true],
            ['key' => 'service_date', 'label' => $this->rlang('col_pl_service_date', 'Service Date')],
            ['key' => 'dossier_name', 'label' => $this->rlang('col_pl_dossier_name', 'Dossier Name')],
            ['key' => 'client_name', 'label' => $this->rlang('col_pl_client_name', 'Client Name')],
            ['key' => 'bank_name', 'label' => $this->rlang('col_pl_bank_name', 'Bank Name')],
            ['key' => 'bank_account_nr', 'label' => $this->rlang('col_pl_bank_acc_nr', 'Bank Account Nr')],
            ['key' => 'account_name', 'label' => $this->rlang('col_pl_account_name', 'Account Name')],
            ['key' => 'email', 'label' => $this->rlang('col_pl_email', 'Supplier Contact Email')],
            ['key' => 'notes', 'label' => $this->rlang('col_pl_notes', 'Notes')],
            ['key' => 'promise_date', 'label' => $this->rlang('col_pl_promise_date', 'Promise Date')],
            ['key' => 'booking_id', 'label' => $this->rlang('col_pl_booking_id', 'Booking ID')],
            ['key' => 'category', 'label' => $this->rlang('col_pl_category', 'Category')],
            ['key' => 'client_payment', 'label' => $this->rlang('col_pl_client_payment', 'Client Payment')],
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

        return $this->respond($this->rlang('p-paylist', 'Payment List'), $f, $cols, $out, ['extra' => $extra, 'hidePeriodPickers' => true], $sub);
    }

    // ---------------------------------------------------------------- invoice paid (allocations)

    public function invoicePaid(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['alloc'] . ' al')
            ->select("pp.{$c['payNo']} AS payno, pp.{$c['payDate']} AS pdate, i.internal_no AS inv, i.doc_type,
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
            $sgn   = ($r['doc_type'] ?? 'invoice') === 'credit_note' ? -1 : 1;
            $out[] = ['payno' => $r['payno'], 'date' => date_id($r['pdate']), 'inv' => $r['inv'] . ($sgn < 0 ? ' · CN' : ''),
                'party' => $r['party'], 'amt' => $sgn * (float) $r['amount_base']];
            $tot += $sgn * (float) $r['amount_base'];
        }
        $out[] = ['_style' => 'total', 'party' => 'TOTAL', 'amt' => $tot];

        return $this->respond($this->rlang($c['k'] . '-invoice-paid', $c['noun'] . ' Invoice Paid'), $f, [
            ['key' => 'payno', 'label' => $c['payNoun'] . ' No.'], ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')],
            ['key' => 'inv', 'label' => $this->rlang('col_invoice', 'Invoice')], ['key' => 'party', 'label' => $c['pLabel']],
            ['key' => 'amt', 'label' => $this->rlang('col_applied', 'Applied') . ' (' . base_code() . ')', 'money' => true],
        ], $out);
    }

    // ---------------------------------------------------------------- party list

    public function parties(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['party'] . ' p')
            ->select("p.code, p.name, p.email, p.phone, p.npwp, p.is_active,
                (SELECT COALESCE(SUM((CASE WHEN i.doc_type = 'credit_note' THEN -1 ELSE 1 END) * (i.total_base - i.{$c['paid']})),0) FROM {$c['inv']} i
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

        return $this->respond($this->rlang($c['k'] === 'p' ? 'p-suppliers' : 's-customers', $c['pLabel'] . ' List'), $f, [
            ['key' => 'code', 'label' => $this->rlang('col_code', 'Code')], ['key' => 'name', 'label' => $this->rlang('col_name', 'Name')],
            ['key' => 'email', 'label' => 'Email'], ['key' => 'phone', 'label' => $this->rlang('col_phone', 'Phone')],
            ['key' => 'npwp', 'label' => 'NPWP'], ['key' => 'status', 'label' => $this->rlang('col_status', 'Status')],
            ['key' => 'bal', 'label' => ($kind === 'purchase' ? $this->rlang('col_payable', 'Payable') : $this->rlang('col_receivable', 'Receivable')) . ' (' . base_code() . ')', 'money' => true],
        ], $out, ['showCompare' => false]);
    }

    // ---------------------------------------------------------------- aging detail (per invoice)

    public function agingDetail(string $kind)
    {
        $c = $this->cfg($kind);
        $f = ReportFilter::resolve();

        $rows = $this->db->table($c['inv'] . ' i')
            ->select("i.internal_no, i.invoice_date, i.due_date, i.doc_type, p.name AS party,
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
            $sgn  = ($r['doc_type'] ?? 'invoice') === 'credit_note' ? -1 : 1;
            $amt  = $sgn * (float) $r['outstanding'];
            $row  = ['inv' => $r['internal_no'] . ($sgn < 0 ? ' · CN' : ''), 'date' => date_id($r['invoice_date']), 'due' => date_id($r['due_date']),
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

        return $this->respond($this->rlang(($kind === 'purchase' ? 'p' : 's') . '-aging', ($kind === 'purchase' ? 'AP' : 'AR') . ' Aging (detail)'), $f, [
            ['key' => 'inv', 'label' => $this->rlang('col_invoice', 'Invoice')], ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'due', 'label' => $this->rlang('col_due', 'Due')],
            ['key' => 'cur', 'label' => $this->rlang('v_current', 'Current'), 'money' => true, 'blankZero' => true],
            ['key' => 'b30', 'label' => '1-30', 'money' => true, 'blankZero' => true],
            ['key' => 'b60', 'label' => '31-60', 'money' => true, 'blankZero' => true],
            ['key' => 'b90', 'label' => '61-90', 'money' => true, 'blankZero' => true],
            ['key' => 'b90p', 'label' => $this->rlang('v_over90', '> 90'), 'money' => true, 'blankZero' => true],
            ['key' => 'tot', 'label' => $this->rlang('col_total', 'Total'), 'money' => true],
        ], $out, ['showAsOf' => true]);
    }

    // ---------------------------------------------------------------- sales overview (YTD vs prior year)

    /**
     * Per customer (or client group / country): revenue and pax for the chosen
     * window vs the same window one year earlier, plus averages. Sales only.
     * Mirrors the client's "Overview-YTD" spreadsheet.
     */
    public function salesOverview()
    {
        $f  = ReportFilter::resolve();
        $by = (string) $this->request->getGet('by');
        $by = in_array($by, ['customer', 'group', 'country'], true) ? $by : 'customer';

        $pyFrom = date('Y-m-d', strtotime($f['from'] . ' -1 year'));
        $pyTo   = date('Y-m-d', strtotime($f['to'] . ' -1 year'));

        $revenue = function (string $from, string $to): array {
            $out = [];
            $rows = $this->db->table('sales_invoices i')
                ->select('c.name AS party, c.client_group, c.country, SUM(i.total_base) AS rev')
                ->join('customers c', 'c.id = i.customer_id', 'left')
                ->where('i.company_id', $this->co())
                ->where('i.status !=', 'draft')
                ->where('i.invoice_date >=', $from)->where('i.invoice_date <=', $to)
                ->groupBy('c.id')
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $out[$r['party'] ?: '(no customer)'] = [
                    'rev'   => (float) $r['rev'],
                    'group' => $r['client_group'] ?: '(ungrouped)',
                    'country' => $r['country'] ?: '(no country)',
                ];
            }

            return $out;
        };

        $revNow = $revenue($f['from'], $f['to']);
        $revPy  = $revenue($pyFrom, $pyTo);
        $paxNow = $this->paxByCustomer($f['from'], $f['to']);
        $paxPy  = $this->paxByCustomer($pyFrom, $pyTo);

        // fold to the chosen grain
        $key = static function (string $name) use ($by, $revNow, $revPy): string {
            if ($by === 'customer') {
                return $name;
            }
            $meta = $revNow[$name] ?? $revPy[$name] ?? null;

            return $meta[$by] ?? ($by === 'group' ? '(ungrouped)' : '(no country)');
        };

        $agg = [];
        $bump = static function (array &$agg, string $k) {
            $agg[$k] ??= ['rev' => 0.0, 'rev_py' => 0.0, 'pax' => 0, 'pax_py' => 0];
        };
        foreach ($revNow as $name => $m) {
            $k = $key($name);
            $bump($agg, $k);
            $agg[$k]['rev'] += $m['rev'];
        }
        foreach ($revPy as $name => $m) {
            $k = $key($name);
            $bump($agg, $k);
            $agg[$k]['rev_py'] += $m['rev'];
        }
        foreach ($paxNow as $name => $px) {
            $k = $key($name);
            $bump($agg, $k);
            $agg[$k]['pax'] += $px;
        }
        foreach ($paxPy as $name => $px) {
            $k = $key($name);
            $bump($agg, $k);
            $agg[$k]['pax_py'] += $px;
        }

        uasort($agg, static fn ($a, $b) => $b['rev'] <=> $a['rev']);

        $pct = static fn (float $now, float $prev): string => abs($prev) < 0.005
            ? ($now > 0.005 ? 'new' : '—')
            : sprintf('%+.1f%%', ($now - $prev) / $prev * 100);

        $out = [];
        $tot = ['rev' => 0.0, 'rev_py' => 0.0, 'pax' => 0, 'pax_py' => 0];
        foreach ($agg as $k => $v) {
            $tot['rev'] += $v['rev'];
            $tot['rev_py'] += $v['rev_py'];
            $tot['pax'] += $v['pax'];
            $tot['pax_py'] += $v['pax_py'];
            $out[] = [
                'party'     => $k,
                'rev'       => $v['rev'],
                'rev_py'    => $v['rev_py'],
                'rev_delta' => $pct($v['rev'], $v['rev_py']),
                'pax'       => $v['pax'] ?: '',
                'pax_py'    => $v['pax_py'] ?: '',
                'pax_delta' => $pct((float) $v['pax'], (float) $v['pax_py']),
                'avg'       => $v['pax'] ? $v['rev'] / $v['pax'] : '',
                'avg_py'    => $v['pax_py'] ? $v['rev_py'] / $v['pax_py'] : '',
            ];
        }
        $out[] = [
            '_style'    => 'total',
            'party'     => 'TOTAL',
            'rev'       => $tot['rev'],
            'rev_py'    => $tot['rev_py'],
            'rev_delta' => $pct($tot['rev'], $tot['rev_py']),
            'pax'       => $tot['pax'] ?: '',
            'pax_py'    => $tot['pax_py'] ?: '',
            'pax_delta' => $pct((float) $tot['pax'], (float) $tot['pax_py']),
            'avg'       => $tot['pax'] ? $tot['rev'] / $tot['pax'] : '',
            'avg_py'    => $tot['pax_py'] ? $tot['rev_py'] / $tot['pax_py'] : '',
        ];

        $partyLabel = $by === 'group'
            ? $this->rlang('col_client_group', 'Client group')
            : ($by === 'country' ? $this->rlang('col_country', 'Country') : $this->rlang('col_customer', 'Customer'));

        $cols = [
            ['key' => 'party', 'label' => $partyLabel],
            ['key' => 'rev', 'label' => $this->rlang('col_rev_ytd', 'Revenue YTD') . ' (' . base_code() . ')', 'money' => true, 'blankZero' => true],
            ['key' => 'rev_py', 'label' => $this->rlang('col_rev_py', 'Revenue last year'), 'money' => true, 'blankZero' => true],
            ['key' => 'rev_delta', 'label' => $this->rlang('col_delta_pct', 'Δ %'), 'align' => 'right'],
            ['key' => 'pax', 'label' => $this->rlang('col_pax', 'Pax'), 'align' => 'right'],
            ['key' => 'pax_py', 'label' => $this->rlang('col_pax_py', 'Pax last year'), 'align' => 'right'],
            ['key' => 'pax_delta', 'label' => $this->rlang('col_delta_pct', 'Δ %'), 'align' => 'right'],
            ['key' => 'avg', 'label' => $this->rlang('col_avg_pax', 'Avg rev / pax'), 'money' => true, 'blankZero' => true],
            ['key' => 'avg_py', 'label' => $this->rlang('col_avg_pax_py', 'Avg / pax last year'), 'money' => true, 'blankZero' => true],
        ];

        $extra = $this->selectField($this->rlang('v_group_by', 'Group by'), 'by', [
            'customer' => $this->rlang('grp_by_customer', 'By customer'),
            'group'    => $this->rlang('grp_by_group', 'By client group'),
            'country'  => $this->rlang('grp_by_country', 'By country'),
        ], $by);

        $sub = $f['label'] . ' vs ' . date_id($pyFrom) . ' – ' . date_id($pyTo);

        return $this->respond($this->rlang('s-overview', 'Sales Overview (YTD vs last year)'), $f, $cols, $out, ['showCompare' => false, 'extra' => $extra], $sub);
    }
}
