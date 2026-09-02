<?php

namespace App\Libraries\Api;

use App\Libraries\Accounting\PurchasePoster;
use App\Models\AccountModel;
use App\Models\PurchaseInvoiceLineModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SupplierModel;
use Config\Database;

/**
 * Applies *actual* supplier costs onto purchase-invoice lines that were imported
 * from Jambix with a *budget* figure, then re-posts each affected invoice.
 *
 * An n8n flow extracts figures from a supplier's own invoice and POSTs them here.
 * Each item is matched to exactly one line:
 *   1. by `booking_ref` (the Jambix ID-Number) - exact, preferred;
 *   2. else by `supplier` + `service_date` - exact date, then a +/- window,
 *      narrowed by `party_name` / `budget` when several lines match.
 * Anything that resolves to 0 or >1 lines is reported, never guessed.
 *
 * On a match the line's amount becomes the actual cost, `cost_source` flips to
 * `actual`, and `supp_inv_ref` / `supp_inv_date` record where the figure came
 * from. Updates are grouped per invoice and applied with one revise (posted) or
 * save+post (a draft that now has a real cost).
 */
class PurchaseCostUpdate
{
    private PurchaseInvoiceModel $invoices;
    private PurchaseInvoiceLineModel $lines;
    private SupplierModel $suppliers;
    private AccountModel $accounts;
    private $db;

    public function __construct()
    {
        $this->invoices  = model(PurchaseInvoiceModel::class);
        $this->lines     = model(PurchaseInvoiceLineModel::class);
        $this->suppliers = model(SupplierModel::class);
        $this->accounts  = model(AccountModel::class);
        $this->db        = Database::connect();
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array{status:string, code:int, payload:array<string,mixed>}
     */
    public function apply(array $body): array
    {
        $items = $body['costs'] ?? $body['lines'] ?? null;
        if (! is_array($items) || $items === []) {
            return $this->err('Provide a non-empty "costs" array.');
        }
        if (count($items) > 2000) {
            return $this->err('At most 2000 cost items per request.');
        }
        $dryRun         = ! empty($body['dry_run']);
        $defaultWindow  = isset($body['window_days']) ? max(0, min(15, (int) $body['window_days'])) : 2;

        // --- 1. resolve every item to a line (or a reason) ----------------
        $results   = [];
        $perInvoice = []; // invoice_id => [ ['line'=>row, 'cost'=>float, 'acct_id'=>?int, 'sref'=>?string, 'sdate'=>?string, 'result_ix'=>int], ... ]

        foreach (array_values($items) as $ix => $raw) {
            $item = is_array($raw) ? $raw : [];
            $res  = ['index' => $ix];

            $cost = $item['cost'] ?? $item['amount'] ?? null;
            if (! is_numeric($cost) || (float) $cost < 0) {
                $results[] = $res + ['status' => 'error', 'message' => 'cost must be a number >= 0.'];

                continue;
            }
            $cost = round((float) $cost, 2);

            $acctId = null;
            if (! empty($item['account_code'])) {
                $a = $this->accounts->where('code', trim((string) $item['account_code']))->first();
                if (! $a || (int) $a['is_group'] === 1 || (int) $a['is_active'] === 0) {
                    $results[] = $res + ['status' => 'error', 'message' => "account_code '{$item['account_code']}' is unknown, a header, or inactive."];

                    continue;
                }
                $acctId = (int) $a['id'];
            }

            $window = isset($item['window_days']) ? max(0, min(15, (int) $item['window_days'])) : $defaultWindow;
            $found  = $this->resolve($item, $window);
            if (isset($found['status'])) {
                $results[] = $res + $found;

                continue;
            }
            $line   = $found['line'];
            $budget = $line['budget_amount'] !== null ? (float) $line['budget_amount'] : (float) $line['amount'];
            $res += [
                'match'       => $found['match'],
                'booking_ref' => $line['booking_ref'],
                'invoice'     => [
                    'id'          => (int) $line['inv_id'],
                    'internal_no' => $line['internal_no'],
                    'external_id' => $line['external_id'],
                    'status'      => $line['inv_status'],
                ],
                'budget'      => $budget,
                'old_amount'  => (float) $line['amount'],
                'new_amount'  => $cost,
                'variance'    => round($cost - $budget, 2),
                'over_budget' => $cost - $budget > 0.005,
            ];

            // already at this actual figure -> nothing to do
            if ($line['cost_source'] === 'actual' && abs((float) $line['amount'] - $cost) < 0.005
                && ($acctId === null || $acctId === (int) $line['account_id'])) {
                $results[] = $res + ['status' => 'unchanged'];

                continue;
            }

            $results[] = $res + ['status' => $dryRun ? 'would_apply' : 'applied'];
            $perInvoice[(int) $line['inv_id']][] = [
                'line'      => $line,
                'cost'      => $cost,
                'acct_id'   => $acctId,
                'sref'      => isset($item['supplier_invoice_ref']) ? trim((string) $item['supplier_invoice_ref']) ?: null : null,
                'sdate'     => $this->date($item['supplier_invoice_date'] ?? null),
                'remark'    => isset($item['remark']) ? trim((string) $item['remark']) ?: null : null,
                'result_ix' => count($results) - 1,
            ];
        }

        // --- 2. apply, one revise per invoice ---------------------------
        $reposted = [];
        if (! $dryRun) {
            $poster = new PurchasePoster();
            foreach ($perInvoice as $invId => $changes) {
                $r = $this->reviseOne($poster, (int) $invId, $changes);
                if ($r['ok']) {
                    $reposted[] = [
                        'id'            => (int) $invId,
                        'internal_no'   => $r['internal_no'],
                        'status'        => $r['status'],
                        'lines_changed' => count($changes),
                    ];
                } else {
                    foreach ($changes as $c) {
                        $results[$c['result_ix']]['status']  = 'error';
                        $results[$c['result_ix']]['message'] = 'Could not re-post ' . $r['internal_no'] . ': ' . implode(' ', $r['errors']);
                    }
                }
            }
        }

        $tally      = array_count_values(array_column($results, 'status'));
        $overBudget = count(array_filter($results, static fn ($r) => ! empty($r['over_budget']) && in_array($r['status'] ?? '', ['applied', 'would_apply'], true)));

        return [
            'status' => 'ok',
            'code'   => 200,
            'payload' => [
                'dry_run' => $dryRun,
                'summary' => [
                    'received'          => count($items),
                    'applied'           => $tally['applied'] ?? 0,
                    'would_apply'       => $tally['would_apply'] ?? 0,
                    'unchanged'         => $tally['unchanged'] ?? 0,
                    'ambiguous'         => $tally['ambiguous'] ?? 0,
                    'not_found'         => $tally['not_found'] ?? 0,
                    'errors'            => $tally['error'] ?? 0,
                    'over_budget'       => $overBudget,
                    'invoices_reposted' => count($reposted),
                ],
                'results'  => $results,
                'invoices' => $reposted,
            ],
        ];
    }

    // ------------------------------------------------------------------ matching

    /**
     * @param array<string,mixed> $item
     *
     * @return array{line: array<string,mixed>, match: string}|array{status: string, message?: string, candidates?: list<string>}
     */
    private function resolve(array $item, int $window): array
    {
        $company = active_company_id();
        $sel     = 'pil.*, pi.id AS inv_id, pi.status AS inv_status, pi.internal_no, pi.external_id,'
            . ' pi.supplier_id, pi.currency_id, pi.exchange_rate';

        // --- 1. booking_ref
        $bref = isset($item['booking_ref']) ? trim((string) $item['booking_ref']) : '';
        if ($bref !== '') {
            $rows = $this->db->table('purchase_invoice_lines pil')
                ->select($sel)
                ->join('purchase_invoices pi', 'pi.id = pil.invoice_id')
                ->where('pi.company_id', $company)
                ->where('pi.status !=', 'void')
                ->where('pil.booking_ref', $bref)
                ->get()->getResultArray();
            if (count($rows) === 1) {
                return ['line' => $rows[0], 'match' => 'booking_ref'];
            }
            if ($rows === []) {
                return ['status' => 'not_found', 'message' => "No live line has booking_ref '{$bref}'."];
            }

            return ['status' => 'ambiguous', 'message' => 'Several lines share that booking_ref.', 'candidates' => array_column($rows, 'booking_ref')];
        }

        // --- 2. supplier + service_date
        $supRef = trim((string) ($item['supplier'] ?? $item['supplier_ref'] ?? $item['supplier_name'] ?? ''));
        $date   = $this->date($item['service_date'] ?? null);
        if ($supRef === '' || $date === null) {
            return ['status' => 'error', 'message' => 'Provide booking_ref, or supplier + service_date.'];
        }
        $supIds = $this->supplierIds($supRef);
        if ($supIds === []) {
            return ['status' => 'not_found', 'message' => "Unknown supplier '{$supRef}'."];
        }

        $base = fn () => $this->db->table('purchase_invoice_lines pil')
            ->select($sel)
            ->join('purchase_invoices pi', 'pi.id = pil.invoice_id')
            ->where('pi.company_id', $company)
            ->where('pi.status !=', 'void')
            ->whereIn('pi.supplier_id', $supIds)
            ->where('pil.cost_source !=', 'actual');

        $rows = $base()->where('pil.service_date', $date)->get()->getResultArray();
        $how  = 'supplier+service_date';
        if ($rows === [] && $window > 0) {
            $from = date('Y-m-d', strtotime($date . " -{$window} days"));
            $to   = date('Y-m-d', strtotime($date . " +{$window} days"));
            $rows = $base()->where('pil.service_date >=', $from)->where('pil.service_date <=', $to)->get()->getResultArray();
            $how  = "supplier+service_date~{$window}d";
        }

        if ($rows === []) {
            return ['status' => 'not_found', 'message' => "No un-actualised line for that supplier near {$date}."];
        }
        if (count($rows) > 1 && ! empty($item['party_name'])) {
            $needle   = mb_strtolower(trim((string) $item['party_name']));
            $narrowed = array_values(array_filter($rows, static function ($r) use ($needle) {
                $hay = mb_strtolower((string) $r['party_name']);

                return $hay !== '' && (str_contains($hay, $needle) || str_contains($needle, $hay));
            }));
            if ($narrowed !== []) {
                $rows = $narrowed;
            }
        }
        if (count($rows) > 1 && isset($item['budget']) && is_numeric($item['budget'])) {
            $b        = round((float) $item['budget'], 2);
            $narrowed = array_values(array_filter($rows, static fn ($r) => abs((float) $r['amount'] - $b) < 0.005));
            if ($narrowed !== []) {
                $rows = $narrowed;
            }
        }

        if (count($rows) === 1) {
            return ['line' => $rows[0], 'match' => $how];
        }

        return [
            'status'     => 'ambiguous',
            'message'    => count($rows) . ' lines matched; add party_name / budget, or use booking_ref.',
            'candidates' => array_slice(array_column($rows, 'booking_ref'), 0, 25),
        ];
    }

    /** @return list<int> */
    private function supplierIds(string $ref): array
    {
        $exact = $this->suppliers->groupStart()->where('code', $ref)->orWhere('name', $ref)->groupEnd()->findColumn('id');
        if ($exact) {
            return array_map('intval', $exact);
        }
        $norm = mb_strtolower(trim(preg_replace('/\s+/', ' ', $ref)));
        $out  = [];
        foreach ($this->suppliers->findAll() as $s) {
            if (mb_strtolower(trim(preg_replace('/\s+/', ' ', $s['name']))) === $norm) {
                $out[] = (int) $s['id'];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ writing

    /**
     * @param list<array<string,mixed>> $changes
     *
     * @return array{ok:bool, internal_no:string, status?:string, errors?:list<string>}
     */
    private function reviseOne(PurchasePoster $poster, int $invId, array $changes): array
    {
        $inv = $this->invoices->find($invId);
        if (! $inv) {
            return ['ok' => false, 'internal_no' => "#{$invId}", 'errors' => ['Invoice vanished.']];
        }
        if ((float) $inv['paid_base'] > 0.005) {
            return ['ok' => false, 'internal_no' => $inv['internal_no'], 'errors' => ['Invoice already has payments — void them first.']];
        }

        $byLineId = [];
        foreach ($changes as $c) {
            $byLineId[(int) $c['line']['id']] = $c;
        }

        $rawLines = [];
        foreach ($this->lines->where('invoice_id', $invId)->orderBy('line_no')->findAll() as $l) {
            $c            = $byLineId[(int) $l['id']] ?? null;
            $rawLines[] = [
                'account_id'    => $c && $c['acct_id'] ? $c['acct_id'] : (int) $l['account_id'],
                'job_id'        => $l['job_id'],
                'description'   => $l['description'],
                'amount'        => $c ? $c['cost'] : (float) $l['amount'],
                // budget is frozen at import - carry it through every revise unchanged
                'budget_amount' => $l['budget_amount'] !== null ? (float) $l['budget_amount'] : (float) $l['amount'],
                'booking_ref'   => $l['booking_ref'],
                'service_date'  => $l['service_date'],
                'party_name'    => $l['party_name'],
                'units'         => $l['units'],
                'nights'        => $l['nights'],
                'cost_source'   => $c ? 'actual' : $l['cost_source'],
                'cost_remark'   => $c ? ($c['remark'] ?? $l['cost_remark']) : $l['cost_remark'],
                'supp_inv_ref'  => $c ? ($c['sref'] ?? $l['supp_inv_ref']) : $l['supp_inv_ref'],
                'supp_inv_date' => $c ? ($c['sdate'] ?? $l['supp_inv_date']) : $l['supp_inv_date'],
            ];
        }

        $header = [
            'supplier_ref'  => $inv['supplier_ref'],
            'supplier_id'   => $inv['supplier_id'],
            'invoice_date'  => $inv['invoice_date'],
            'due_date'      => $inv['due_date'],
            'currency_id'   => $inv['currency_id'],
            'exchange_rate' => $inv['exchange_rate'],
            'description'   => $inv['description'],
            'ppn_amount'    => $inv['ppn_amount'],
            'pph_amount'    => $inv['pph_amount'],
        ];

        if ($inv['status'] === 'posted') {
            $r = $poster->reviseInvoice($invId, $header, $rawLines);
            if (! $r['ok']) {
                return ['ok' => false, 'internal_no' => $inv['internal_no'], 'errors' => $r['errors']];
            }

            return ['ok' => true, 'internal_no' => $inv['internal_no'], 'status' => 'posted'];
        }

        // draft: re-save, then post if it now carries a real cost
        $save = $poster->saveInvoice($header, $rawLines, $invId);
        if (! $save['ok']) {
            return ['ok' => false, 'internal_no' => $inv['internal_no'], 'errors' => $save['errors']];
        }
        $fresh = $this->invoices->find($invId);
        if ((float) $fresh['subtotal'] > 0) {
            $p = $poster->postInvoice($invId);
            if (! $p['ok']) {
                return ['ok' => false, 'internal_no' => $inv['internal_no'], 'errors' => $p['errors']];
            }

            return ['ok' => true, 'internal_no' => $inv['internal_no'], 'status' => 'posted'];
        }

        return ['ok' => true, 'internal_no' => $inv['internal_no'], 'status' => 'draft'];
    }

    // ------------------------------------------------------------------ misc

    private function date($v): ?string
    {
        if (! is_string($v) && ! is_numeric($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' && ($t = strtotime($s)) ? date('Y-m-d', $t) : null;
    }

    /** @return array{status:string, code:int, payload:array<string,mixed>} */
    private function err(string $msg): array
    {
        return ['status' => 'error', 'code' => 422, 'payload' => ['message' => $msg]];
    }
}
