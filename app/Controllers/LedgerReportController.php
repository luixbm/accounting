<?php

namespace App\Controllers;

use App\Libraries\Accounting\Ledger;
use App\Libraries\Report\ReportExporter;
use App\Libraries\Report\ReportFilter;
use App\Models\AccountModel;
use Config\Database;

/**
 * R4 report set: cross-cutting ledger / journal / job analytics that don't
 * belong to a single Sales or Purchase subledger. Every report renders
 * through the shared reports/_list.php view and supports ?format=xlsx.
 */
class LedgerReportController extends BaseController
{
    private $db;
    private Ledger $ledger;

    public function __construct()
    {
        $this->db     = Database::connect();
        $this->ledger = new Ledger();
    }

    private function co(): int
    {
        return active_company_id();
    }

    /** Localised report string, falling back to English. */
    private function rlang(string $key, string $fallback): string
    {
        $s = lang('Report.' . $key);

        return $s === 'Report.' . $key ? $fallback : $s;
    }

    /**
     * @param list<array<string,mixed>> $columns
     * @param list<array<string,mixed>> $rows
     */
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

    /** Build a <select> block for the filter bar's $extra slot. */
    private function selectField(string $label, string $name, array $options, $current, bool $multiple = false): string
    {
        $cur  = array_map('strval', (array) $current);
        $opts = '';
        foreach ($options as $val => $text) {
            $sel = in_array((string) $val, $cur, true) ? ' selected' : '';
            $opts .= '<option value="' . esc($val, 'attr') . '"' . $sel . '>' . esc($text) . '</option>';
        }
        $m = $multiple ? ' multiple size="6"' : '';
        $n = $multiple ? $name . '[]' : $name;

        return '<div class="field" style="max-width:220px"><label>' . esc($label) . '</label>'
            . '<select name="' . esc($n, 'attr') . '"' . $m . '>' . $opts . '</select></div>';
    }

    /** Free-text search field for the filter bar's $extra slot. */
    private function textField(string $label, string $name, string $value, string $placeholder = 'code / name'): string
    {
        return '<div class="field" style="max-width:200px"><label>' . esc($label) . '</label>'
            . '<input name="' . esc($name, 'attr') . '" value="' . esc($value, 'attr') . '" placeholder="' . esc($placeholder, 'attr') . '"></div>';
    }

    // ================================================================ Journal List

    public function journalList()
    {
        $f      = ReportFilter::resolve();
        $source = (string) ($this->request->getGet('source') ?? '');
        $status = (string) ($this->request->getGet('status') ?? '');

        $q = $this->db->table('journals j')
            ->select('j.journal_no, j.entry_date, j.reference, j.description, j.source, j.status,
                      SUM(jl.debit_base) AS d, SUM(jl.credit_base) AS c')
            ->join('journal_lines jl', 'jl.journal_id = j.id')
            ->where('j.company_id', $this->co())
            ->where('j.entry_date >=', $f['from'])->where('j.entry_date <=', $f['to'])
            ->groupBy('j.id')
            ->orderBy('j.entry_date', 'ASC')->orderBy('j.journal_no', 'ASC');

        if ($status !== '' && in_array($status, ['draft', 'posted', 'void'], true)) {
            $q->where('j.status', $status);
        } else {
            $q->whereIn('j.status', ['posted', 'void']);
        }
        if ($source !== '') {
            $q->where('j.source', $source);
        }

        $rows  = $q->get()->getResultArray();
        $out   = [];
        $td    = 0.0;
        $tc    = 0.0;
        foreach ($rows as $r) {
            $out[] = [
                'no' => $r['journal_no'], 'date' => date_id($r['entry_date']), 'ref' => $r['reference'],
                'desc' => $r['description'], 'src' => ucfirst($r['source']), 'status' => ucfirst($r['status']),
                'd' => $r['d'], 'c' => $r['c'],
            ];
            $td += (float) $r['d'];
            $tc += (float) $r['c'];
        }
        $out[] = ['_style' => 'total', 'desc' => 'TOTAL (' . count($rows) . ' journals)', 'd' => $td, 'c' => $tc];

        $sources = $this->db->table('journals')->distinct()->select('source')
            ->where('company_id', $this->co())->orderBy('source')->get()->getResultArray();
        $srcOpts = ['' => 'All sources'];
        foreach ($sources as $s) {
            $srcOpts[$s['source']] = ucfirst($s['source']);
        }
        $extra = $this->selectField('Source', 'source', $srcOpts, $source)
            . $this->selectField('Status', 'status', ['' => 'Posted + Void', 'posted' => 'Posted', 'void' => 'Void', 'draft' => 'Draft'], $status);

        return $this->respond($this->rlang('journal-list', 'Journal List'), $f, [
            ['key' => 'no', 'label' => $this->rlang('col_journal_no', 'Journal No.')], ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')],
            ['key' => 'ref', 'label' => $this->rlang('col_reference', 'Reference')], ['key' => 'desc', 'label' => $this->rlang('col_description', 'Description')],
            ['key' => 'src', 'label' => $this->rlang('col_source', 'Source')], ['key' => 'status', 'label' => $this->rlang('col_status', 'Status')],
            ['key' => 'd', 'label' => $this->rlang('col_debit', 'Debit') . ' (' . base_code() . ')', 'money' => true],
            ['key' => 'c', 'label' => $this->rlang('col_credit', 'Credit') . ' (' . base_code() . ')', 'money' => true],
        ], $out, ['extra' => $extra]);
    }

    // ================================================================ Realized Gain / Loss

    public function realizedFx()
    {
        $f   = ReportFilter::resolve();
        $ids = \App\Libraries\Accounting\ControlAccounts::allIds('fx');

        $rows = $ids ? $this->db->table('journal_lines jl')
            ->select('j.entry_date, j.journal_no, j.reference, jl.memo, a.code, a.name,
                      jl.debit_base, jl.credit_base')
            ->join('journals j', 'j.id = jl.journal_id')
            ->join('accounts a', 'a.id = jl.account_id')
            ->where('j.company_id', $this->co())
            ->whereIn('j.status', ['posted', 'void'])
            ->whereIn('jl.account_id', $ids)
            ->where('j.entry_date >=', $f['from'])->where('j.entry_date <=', $f['to'])
            ->orderBy('j.entry_date', 'ASC')->orderBy('j.journal_no', 'ASC')
            ->get()->getResultArray() : [];

        $out    = [];
        $tGain  = 0.0;
        $tLoss  = 0.0;
        foreach ($rows as $r) {
            // one FX account per currency holds both sides: a credit is a gain, a debit a loss
            $net = (float) $r['credit_base'] - (float) $r['debit_base'];
            $g   = $net > 0 ? $net : 0.0;
            $l   = $net < 0 ? -$net : 0.0;
            $out[]  = [
                'date' => date_id($r['entry_date']), 'no' => $r['journal_no'],
                'ref' => $r['reference'], 'memo' => $r['memo'],
                'acc' => trim($r['code'] . ' ' . $r['name']),
                'gain' => $g, 'loss' => $l, 'net' => $g - $l,
            ];
            $tGain += $g;
            $tLoss += $l;
        }
        $out[] = ['_style' => 'total', 'memo' => 'NET REALIZED GAIN / (LOSS)',
            'gain' => $tGain, 'loss' => $tLoss, 'net' => $tGain - $tLoss];

        $sub = $ids ? '' : 'No realized FX accounts configured — see Setup → Control Accounts.';
        $bc  = base_code();

        return $this->respond($this->rlang('realized-fx', 'Realized Gain / Loss'), $f, [
            ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'no', 'label' => $this->rlang('col_journal', 'Journal')],
            ['key' => 'ref', 'label' => $this->rlang('col_reference', 'Reference')], ['key' => 'memo', 'label' => $this->rlang('col_memo', 'Memo')],
            ['key' => 'acc', 'label' => $this->rlang('col_account', 'Account')],
            ['key' => 'gain', 'label' => $this->rlang('col_gain', 'Gain') . " ({$bc})", 'money' => true, 'blankZero' => true],
            ['key' => 'loss', 'label' => $this->rlang('col_loss', 'Loss') . " ({$bc})", 'money' => true, 'blankZero' => true],
            ['key' => 'net', 'label' => $this->rlang('col_net', 'Net') . " ({$bc})", 'money' => true],
        ], $out, [], $sub);
    }

    // ================================================================ GL Details (multi-account)

    public function glMulti()
    {
        $f       = ReportFilter::resolve();
        $accts   = model(AccountModel::class)->postable();
        $picked  = array_values(array_filter(array_map('intval', (array) $this->request->getGet('account_id'))));

        // No explicit pick: show every account that actually moved in the window.
        if (! $picked) {
            $mv     = $this->ledger->movements($f['from'], $f['to']);
            $picked = array_keys(array_filter($mv, static fn ($m) => abs($m['debit'] - $m['credit']) >= 0.005 || $m['debit'] + $m['credit'] >= 0.005));
        }
        $pickedSet = array_flip($picked);

        $out       = [];
        $gd = $gc  = 0.0;
        foreach ($accts as $a) {
            if (! isset($pickedSet[(int) $a['id']])) {
                continue;
            }
            $data = $this->ledger->generalLedger((int) $a['id'], $f['from'], $f['to']);
            if (! $data['lines'] && abs($data['opening']) < 0.005) {
                continue;
            }
            $out[] = ['_style' => 'section', '_label' => trim($a['code'] . ' ' . $a['name'])];
            $out[] = ['memo' => 'Opening balance', 'bal' => $data['opening'], '_style' => 'subtotal'];
            $pd = $pc = 0.0;
            foreach ($data['lines'] as $l) {
                $out[] = [
                    'date' => date_id($l['entry_date']), 'no' => $l['journal_no'], 'memo' => $l['memo'],
                    'party' => $l['customer_name'] ?? $l['supplier_name'] ?? '',
                    'd' => $l['debit'], 'c' => $l['credit'], 'bal' => $l['balance'],
                ];
                $pd += (float) $l['debit'];
                $pc += (float) $l['credit'];
            }
            $out[] = ['_style' => 'subtotal', 'memo' => 'Movement + closing', 'd' => $pd, 'c' => $pc, 'bal' => $data['closing']];
            $gd += $pd;
            $gc += $pc;
        }
        $out[] = ['_style' => 'total', 'memo' => 'GRAND TOTAL MOVEMENT', 'd' => $gd, 'c' => $gc];

        $accOpts = [];
        foreach ($accts as $a) {
            $accOpts[$a['id']] = $a['code'] . ' ' . $a['name'];
        }
        $extra = $this->selectField('Accounts (none = all with movement)', 'account_id', $accOpts, $picked, true);

        return $this->respond($this->rlang('gl-details', 'General Ledger Details'), $f, [
            ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'no', 'label' => $this->rlang('col_journal', 'Journal')],
            ['key' => 'memo', 'label' => $this->rlang('col_memo', 'Memo')], ['key' => 'party', 'label' => $this->rlang('col_party', 'Party')],
            ['key' => 'd', 'label' => $this->rlang('col_debit', 'Debit') . ' (' . base_code() . ')', 'money' => true, 'blankZero' => true],
            ['key' => 'c', 'label' => $this->rlang('col_credit', 'Credit') . ' (' . base_code() . ')', 'money' => true, 'blankZero' => true],
            ['key' => 'bal', 'label' => $this->rlang('col_balance', 'Balance') . ' (' . base_code() . ')', 'money' => true],
        ], $out, ['extra' => $extra]);
    }

    // ================================================================ Payment by Bank

    public function paymentByBank()
    {
        $f = ReportFilter::resolve();

        $recv = $this->db->table('sales_receipts r')
            ->select("r.receipt_date AS d, r.receipt_no AS no, r.reference, r.status,
                      c.name AS party, r.amount_base AS amt, ba.code AS bcode, ba.name AS bname")
            ->join('accounts ba', 'ba.id = r.bank_account_id', 'left')
            ->join('customers c', 'c.id = r.customer_id', 'left')
            ->where('r.company_id', $this->co())
            ->where('r.receipt_date >=', $f['from'])->where('r.receipt_date <=', $f['to'])
            ->get()->getResultArray();

        $paid = $this->db->table('purchase_payments p')
            ->select("p.payment_date AS d, p.payment_no AS no, p.reference, p.status,
                      s.name AS party, p.amount_base AS amt, ba.code AS bcode, ba.name AS bname")
            ->join('accounts ba', 'ba.id = p.bank_account_id', 'left')
            ->join('suppliers s', 's.id = p.supplier_id', 'left')
            ->where('p.company_id', $this->co())
            ->where('p.payment_date >=', $f['from'])->where('p.payment_date <=', $f['to'])
            ->get()->getResultArray();

        $all = [];
        foreach ($recv as $r) {
            $all[] = $r + ['typ' => 'Receipt'];
        }
        foreach ($paid as $r) {
            $all[] = $r + ['typ' => 'Payment'];
        }
        usort($all, static function ($a, $b) {
            return [$a['bcode'], $a['d'], $a['no']] <=> [$b['bcode'], $b['d'], $b['no']];
        });

        $out      = [];
        $curBank  = null;
        $sIn = $sOut = 0.0;
        $gIn = $gOut = 0.0;
        $flush = static function (&$out, $label, $in, $outv) {
            $out[] = ['_style' => 'subtotal', 'no' => $label, 'in' => $in, 'out' => $outv, 'net' => $in - $outv];
        };
        foreach ($all as $r) {
            $bank = trim(($r['bcode'] ?? '') . ' ' . ($r['bname'] ?? '')) ?: '(no bank)';
            if ($bank !== $curBank) {
                if ($curBank !== null) {
                    $flush($out, 'Subtotal ' . $curBank, $sIn, $sOut);
                }
                $curBank = $bank;
                $sIn     = $sOut = 0.0;
                $out[]   = ['_style' => 'section', '_label' => $bank];
            }
            $void = $r['status'] === 'void';
            $in   = ($r['typ'] === 'Receipt' && ! $void) ? (float) $r['amt'] : 0.0;
            $ov   = ($r['typ'] === 'Payment' && ! $void) ? (float) $r['amt'] : 0.0;
            $out[] = [
                'date' => date_id($r['d']), 'no' => $r['no'], 'typ' => $r['typ'],
                'party' => $r['party'], 'ref' => $r['reference'],
                'in' => $in, 'out' => $ov, 'net' => '',
                'status' => $void ? 'Void' : ucfirst((string) $r['status']),
            ];
            $sIn += $in;
            $sOut += $ov;
            $gIn += $in;
            $gOut += $ov;
        }
        if ($curBank !== null) {
            $flush($out, 'Subtotal ' . $curBank, $sIn, $sOut);
        }
        $out[] = ['_style' => 'total', 'no' => 'GRAND TOTAL', 'in' => $gIn, 'out' => $gOut, 'net' => $gIn - $gOut];

        return $this->respond($this->rlang('payment-bank', 'Payment by Bank'), $f, [
            ['key' => 'date', 'label' => $this->rlang('col_date', 'Date')], ['key' => 'no', 'label' => $this->rlang('col_no', 'No.')],
            ['key' => 'typ', 'label' => $this->rlang('col_type', 'Type')], ['key' => 'party', 'label' => $this->rlang('col_party', 'Party')],
            ['key' => 'ref', 'label' => $this->rlang('col_reference', 'Reference')],
            ['key' => 'in', 'label' => $this->rlang('col_money_in', 'Money In') . ' (' . base_code() . ')', 'money' => true, 'blankZero' => true],
            ['key' => 'out', 'label' => $this->rlang('col_money_out', 'Money Out') . ' (' . base_code() . ')', 'money' => true, 'blankZero' => true],
            ['key' => 'net', 'label' => $this->rlang('col_net', 'Net') . ' (' . base_code() . ')', 'money' => true, 'blankZero' => true],
            ['key' => 'status', 'label' => $this->rlang('col_status', 'Status')],
        ], $out);
    }

    // ================================================================ Job List

    public function jobList()
    {
        $f        = ReportFilter::resolve();
        $status   = (string) ($this->request->getGet('job_status') ?? '');
        $q        = trim((string) $this->request->getGet('job_q'));
        $plFilter = (string) ($this->request->getGet('job_pl') ?? '');

        // the period picker is the ARRIVAL-date window; P&L is computed all-time
        $jq = $this->db->table('jobs j')
            ->select('j.id, j.code, j.name, j.status, j.start_date, c.name AS customer')
            ->join('customers c', 'c.id = j.customer_id', 'left')
            ->where('j.company_id', $this->co())
            ->where('j.start_date >=', $f['from'])
            ->where('j.start_date <=', $f['to'])
            ->orderBy('j.start_date', 'ASC')->orderBy('j.code', 'ASC');
        if ($status !== '') {
            $jq->where('j.status', $status);
        }
        if ($q !== '') {
            $jq->groupStart()->like('j.code', $q)->orLike('j.name', $q)->groupEnd();
        }
        $jobs = $jq->get()->getResultArray();

        $out = [];
        $T   = ['rev' => 0.0, 'dc' => 0.0, 'gp' => 0.0, 'exp' => 0.0, 'net' => 0.0];
        foreach ($jobs as $j) {
            $pl  = $this->ledger->jobProfitLoss((int) $j['id']);
            if ($plFilter === 'loss' && $pl['net'] >= -0.005) {
                continue;
            }
            if ($plFilter === 'profit' && $pl['net'] <= 0.005) {
                continue;
            }
            $exp = $pl['groups']['expense']['total'] + $pl['groups']['other_expense']['total'] - $pl['groups']['other_income']['total'];
            $out[] = [
                'code' => $j['code'], 'job' => $j['name'], 'customer' => $j['customer'],
                'arrival' => $j['start_date'] ? date_id($j['start_date']) : '',
                'status' => ucfirst($j['status']),
                'rev' => $pl['revenue'], 'dc' => $pl['direct_cost'], 'gp' => $pl['gross_profit'],
                'exp' => $exp, 'net' => $pl['net'],
                'margin' => $pl['revenue'] != 0.0 ? number_format($pl['net'] / $pl['revenue'] * 100, 1) . '%' : '',
            ];
            $T['rev'] += $pl['revenue'];
            $T['dc']  += $pl['direct_cost'];
            $T['gp']  += $pl['gross_profit'];
            $T['exp'] += $exp;
            $T['net'] += $pl['net'];
        }
        $out[] = ['_style' => 'total', 'job' => 'TOTAL', 'rev' => $T['rev'], 'dc' => $T['dc'],
            'gp' => $T['gp'], 'exp' => $T['exp'], 'net' => $T['net'],
            'margin' => $T['rev'] != 0.0 ? number_format($T['net'] / $T['rev'] * 100, 1) . '%' : ''];

        $shown = count($out) - 1; // minus the TOTAL row
        $extra = $this->textField('Search job', 'job_q', $q)
            . $this->selectField('P&L', 'job_pl', ['' => 'All', 'loss' => 'Loss-making only', 'profit' => 'Profitable only'], $plFilter)
            . $this->selectField('Job status', 'job_status', ['' => 'All', 'open' => 'Open', 'closed' => 'Closed'], $status);

        return $this->respond($this->rlang('job-list', 'Job List'), $f, [
            ['key' => 'code', 'label' => $this->rlang('col_code', 'Code')], ['key' => 'job', 'label' => $this->rlang('col_job', 'Job')],
            ['key' => 'customer', 'label' => $this->rlang('col_customer', 'Customer')], ['key' => 'arrival', 'label' => $this->rlang('col_arrival', 'Arrival')],
            ['key' => 'status', 'label' => $this->rlang('col_status', 'Status')],
            ['key' => 'rev', 'label' => $this->rlang('col_revenue', 'Revenue'), 'money' => true, 'blankZero' => true],
            ['key' => 'dc', 'label' => $this->rlang('col_direct_cost', 'Direct Cost'), 'money' => true, 'blankZero' => true],
            ['key' => 'gp', 'label' => $this->rlang('col_gross_profit', 'Gross Profit'), 'money' => true, 'blankZero' => true],
            ['key' => 'exp', 'label' => $this->rlang('col_expense', 'Expense'), 'money' => true, 'blankZero' => true],
            ['key' => 'net', 'label' => $this->rlang('col_net', 'Net') . ' (' . base_code() . ')', 'money' => true],
            ['key' => 'margin', 'label' => $this->rlang('col_margin', 'Margin'), 'align' => 'right'],
        ], $out, ['extra' => $extra],
            $shown . ($plFilter === 'loss' ? ' loss-making' : ($plFilter === 'profit' ? ' profitable' : '')) . ' job(s) arriving ' . date_id($f['from']) . ' – ' . date_id($f['to']));
    }

    // ================================================================ Job P&L — multi

    public function jobPnlMulti()
    {
        $f   = ReportFilter::resolve();
        $q   = trim((string) $this->request->getGet('job_q'));
        $cap = 25;

        // the period picker is the ARRIVAL window; the search narrows further
        $jq = $this->db->table('jobs j')
            ->select('j.id, j.code, j.name, j.start_date, c.name AS customer')
            ->join('customers c', 'c.id = j.customer_id', 'left')
            ->where('j.company_id', $this->co())
            ->where('j.start_date >=', $f['from'])
            ->where('j.start_date <=', $f['to'])
            ->orderBy('j.start_date', 'ASC')->orderBy('j.code', 'ASC');
        if ($q !== '') {
            $jq->groupStart()->like('j.code', $q)->orLike('j.name', $q)->groupEnd();
        }
        $matched = $jq->get()->getResultArray();
        $jobs    = array_slice($matched, 0, $cap);
        $jobIds  = array_map(static fn ($j) => (int) $j['id'], $jobs);

        // sales per (job, customer) and purchase per (job, supplier)
        $byJob = []; // jobId => ['sales' => [name=>amt], 'purchase' => [name=>amt]]
        if ($jobIds) {
            foreach ($this->db->table('sales_invoice_lines sil')
                ->select('sil.job_id AS jid, c.name AS nm, SUM(sil.amount_base) AS amt')
                ->join('sales_invoices si', 'si.id = sil.invoice_id')
                ->join('customers c', 'c.id = si.customer_id', 'left')
                ->whereIn('sil.job_id', $jobIds)
                ->groupBy(['sil.job_id', 'c.name'])->get()->getResultArray() as $r) {
                $byJob[(int) $r['jid']]['sales'][$r['nm'] ?: '(no customer)'] = (float) $r['amt'];
            }
            foreach ($this->db->table('purchase_invoice_lines pil')
                ->select('pil.job_id AS jid, s.name AS nm, SUM(pil.amount_base) AS amt')
                ->join('purchase_invoices pi', 'pi.id = pil.invoice_id')
                ->join('suppliers s', 's.id = pi.supplier_id', 'left')
                ->whereIn('pil.job_id', $jobIds)
                ->groupBy(['pil.job_id', 's.name'])->get()->getResultArray() as $r) {
                $byJob[(int) $r['jid']]['purchase'][$r['nm'] ?: '(no supplier)'] = (float) $r['amt'];
            }
        }

        $pct = static fn (float $net, float $sales): string => $sales != 0.0 ? number_format($net / $sales * 100, 1) . '%' : '';

        $out = [];
        $GS  = 0.0;
        $GP  = 0.0;
        foreach ($jobs as $n => $j) {
            $jid = (int) $j['id'];
            if ($n > 0) {
                $out[] = ['party' => '']; // spacer between job blocks
            }
            $out[] = ['_style' => 'section', '_label' => sprintf(
                'Dossier Nr %s | Name : %s | Customer : %s | Arrival Date : %s',
                $j['code'],
                $j['name'] ?: '—',
                $j['customer'] ?: '—',
                $j['start_date'] ? date('d M Y', strtotime($j['start_date'])) : '—'
            )];

            $names = array_unique(array_merge(
                array_keys($byJob[$jid]['sales'] ?? []),
                array_keys($byJob[$jid]['purchase'] ?? [])
            ));
            natcasesort($names);

            $jS = 0.0;
            $jP = 0.0;
            foreach ($names as $nm) {
                $s = $byJob[$jid]['sales'][$nm] ?? 0.0;
                $p = $byJob[$jid]['purchase'][$nm] ?? 0.0;
                if (abs($s) < 0.005 && abs($p) < 0.005) {
                    continue;
                }
                $out[] = ['party' => $nm, 'sales' => $s, 'purchase' => $p];
                $jS += $s;
                $jP += $p;
            }
            $jNet  = $jS - $jP;
            $out[] = ['_style' => 'subtotal', 'party' => 'TOTAL', 'sales' => $jS, 'purchase' => $jP,
                'net' => $jNet, 'margin' => $pct($jNet, $jS)];
            $GS += $jS;
            $GP += $jP;
        }

        if (count($jobs) > 1) {
            $out[] = ['party' => ''];
            $out[] = ['_style' => 'total', 'party' => 'GRAND TOTAL — ' . count($jobs) . ' jobs',
                'sales' => $GS, 'purchase' => $GP, 'net' => $GS - $GP, 'margin' => $pct($GS - $GP, $GS)];
        }

        $cols = [
            ['key' => 'party', 'label' => $this->rlang('col_cust_supp', 'Customer / Supplier')],
            ['key' => 'sales', 'label' => $this->rlang('col_sales', 'Sales'), 'money' => true, 'blankZero' => true],
            ['key' => 'purchase', 'label' => $this->rlang('col_purchase', 'Purchase'), 'money' => true, 'blankZero' => true],
            ['key' => 'net', 'label' => $this->rlang('col_net', 'Net'), 'money' => true, 'blankZero' => true],
            ['key' => 'margin', 'label' => $this->rlang('col_margin', 'Margin'), 'align' => 'right'],
        ];

        $extra = $this->textField('Search job', 'job_q', $q);
        $sub   = $matched
            ? count($jobs) . (count($matched) > $cap ? ' of ' . count($matched) : '') . ' job(s) arriving '
              . date_id($f['from']) . ' – ' . date_id($f['to'])
              . (count($matched) > $cap ? ' — refine the search to see the rest' : '')
            : 'No jobs match the arrival window / search.';

        return $this->respond($this->rlang('job-detail', 'Job P&L — Sales vs Purchase'), $f, $cols, $out, ['extra' => $extra], $sub);
    }
}
