<?php

namespace App\Libraries\Import;

use App\Libraries\Accounting\PurchasePoster;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\JobModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SupplierModel;
use Config\Database;

/**
 * Imports a Jambix booking export (Report.xlsx) into the Purchase module.
 *
 * One row = one booked service. Rows are grouped by (SUPPLIER + DOSS NR) into a
 * purchase invoice carrying the *budget* cost (BUY IDR) so a job P&L can be run
 * before the supplier's own invoice arrives; the n8n cost-update flow later
 * flips each line from budget to actual.
 *
 * Column mapping (defaults, all overridable on the map step):
 *   TRAVEL DATE  -> invoice / journal date (per invoice: the earliest in group)
 *   DATE         -> line service_date
 *   PROGRAM      -> line description
 *   BUY IDR      -> line amount (0 allowed - "no cost yet", invoice stays draft)
 *   ID-Number    -> line booking_ref (per-line idempotency key)
 *   DOSSIER NAME -> line party_name  + job name
 *   DOSS NR      -> job code + invoice external_id stem
 *   CLIENT NAME  -> customer (job.customer_id)
 *   SUPPLIER     -> supplier (auto-created, name-normalised)
 *   # Nts/# Units-> line nights / units
 *   Res. Code    -> line supp_inv_ref + invoice supplier_ref
 *
 * Idempotent: an invoice whose external_id already exists is skipped whole; a
 * line whose booking_ref already exists anywhere is skipped.
 */
class JambixImporter
{
    /** logical field => [label, required?] */
    public const FIELDS = [
        'supplier'     => ['Supplier', true],
        'doss_nr'      => ['Dossier number (DOSS NR)', true],
        'travel_date'  => ['Travel date -> invoice date', true],
        'buy'          => ['Buy amount (BUY IDR)', true],
        'booking_id'   => ['Booking id (ID-Number)', true],
        'program'      => ['Program -> description', false],
        'service_date' => ['Service date (DATE)', false],
        'dossier_name' => ['Dossier name -> party / job name', false],
        'client'       => ['Client name -> customer', false],
        'nights'       => ['# Nights', false],
        'units'        => ['# Units', false],
        'res_code'     => ['Reservation code', false],
        'st_dossier'   => ['Dossier status', false],
        'st_product'   => ['Product status', false],
    ];

    /** product / dossier status values that mean "do not import this row" */
    public const SKIP_STATUS = ['cancelled', 'canceled', 'cancel', 'void', 'expired'];

    public const DEFAULT_FALLBACK_CODE = '52000';

    private SupplierModel $suppliers;
    private CustomerModel $customers;
    private JobModel $jobs;
    private AccountModel $accounts;
    private PurchaseInvoiceModel $invoices;

    public function __construct()
    {
        $this->suppliers = model(SupplierModel::class);
        $this->customers = model(CustomerModel::class);
        $this->jobs      = model(JobModel::class);
        $this->accounts  = model(AccountModel::class);
        $this->invoices  = model(PurchaseInvoiceModel::class);
    }

    // ---------------------------------------------------------------- grid helpers

    /** @param list<list<mixed>> $grid @return array<int,string> col index => header */
    public function headers(array $grid, int $headerRow): array
    {
        $row = $grid[$headerRow - 1] ?? [];
        $out = [];
        foreach ($row as $i => $val) {
            $txt     = trim((string) $val);
            $out[$i] = $txt !== '' ? $txt : ('Column ' . $this->colLetter($i));
        }
        $width = max(array_map('count', array_slice($grid, 0, 50) ?: [[]]));
        for ($i = count($out); $i < $width; $i++) {
            $out[$i] = 'Column ' . $this->colLetter($i);
        }

        return $out;
    }

    /** @param list<list<mixed>> $grid @return list<array{n:int,cells:list<mixed>}> */
    public function dataRows(array $grid, int $headerRow): array
    {
        $out = [];
        foreach ($grid as $idx => $cells) {
            if ($idx < $headerRow) {
                continue;
            }
            $out[] = ['n' => $idx + 1, 'cells' => $cells];
        }

        return $out;
    }

    /**
     * Best-guess column map from the header labels.
     *
     * @param array<int,string> $headers
     *
     * @return array<string,int>
     */
    public function guessMap(array $headers): array
    {
        $norm  = static fn (string $s): string => preg_replace('/[^a-z0-9]+/', '', strtolower($s));
        $alias = [
            'supplier'      => 'supplier',
            'dossnr'        => 'doss_nr',
            'dossiernr'     => 'doss_nr',
            'dossiernumber' => 'doss_nr',
            'traveldate'    => 'travel_date',
            'buyidr'        => 'buy',
            'buy'           => 'buy',
            'idnumber'      => 'booking_id',
            'bookingid'     => 'booking_id',
            'program'       => 'program',
            'date'          => 'service_date',
            'servicedate'   => 'service_date',
            'dossiername'   => 'dossier_name',
            'clientname'    => 'client',
            'client'        => 'client',
            'nts'           => 'nights',
            'nights'        => 'nights',
            'units'         => 'units',
            'rescode'       => 'res_code',
            'reservationcode' => 'res_code',
            'stdossier'     => 'st_dossier',
            'stproduct'     => 'st_product',
        ];

        $map = [];
        foreach ($headers as $i => $label) {
            $key = $alias[$norm($label)] ?? null;
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    // ---------------------------------------------------------------- parse

    /**
     * @param list<list<mixed>>   $grid
     * @param array<string,int>   $map
     * @param array<string,mixed> $opt   headerRow
     *
     * @return array{invoices: list<array<string,mixed>>, summary: array<string,int|float>, errors: list<string>}
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $get       = static fn (array $cells, ?int $col): string => $col === null ? '' : trim((string) ($cells[$col] ?? ''));

        // existing state for idempotency
        $seenExt = [];
        foreach ($this->invoices->builder()->select('external_id')->where('external_id IS NOT NULL')->get()->getResultArray() as $r) {
            $seenExt[(string) $r['external_id']] = true;
        }
        $seenBooking = [];
        foreach (Database::connect()->table('purchase_invoice_lines pil')
            ->select('pil.booking_ref')
            ->join('purchase_invoices pi', 'pi.id = pil.invoice_id')
            ->where('pi.company_id', active_company_id())
            ->where('pil.booking_ref IS NOT NULL')
            ->get()->getResultArray() as $r) {
            $seenBooking[(string) $r['booking_ref']] = true;
        }

        $existingSupplier = [];
        foreach ($this->suppliers->findAll() as $s) {
            $existingSupplier[$this->norm($s['name'])] = (int) $s['id'];
        }
        $existingCustomer = [];
        foreach ($this->customers->findAll() as $c) {
            $existingCustomer[$this->norm($c['name'])] = (int) $c['id'];
        }
        $existingJob = [];
        foreach ($this->jobs->findAll() as $j) {
            $existingJob[(string) $j['code']] = (int) $j['id'];
        }

        /** @var array<string,array<string,mixed>> $groups keyed by external_id */
        $groups   = [];
        $skipRows = 0;
        $newSup   = [];
        $newCus   = [];
        $newJob   = [];

        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $c = $r['cells'];

            $supplier = $this->norm($get($c, $map['supplier'] ?? null), false);
            $dossNr   = $get($c, $map['doss_nr'] ?? null);
            $booking  = $get($c, $map['booking_id'] ?? null);
            if ($supplier === '' && $dossNr === '' && $booking === '') {
                continue; // blank row
            }

            $stP = strtolower($get($c, $map['st_product'] ?? null));
            $stD = strtolower($get($c, $map['st_dossier'] ?? null));
            if (in_array($stP, self::SKIP_STATUS, true) || in_array($stD, self::SKIP_STATUS, true)) {
                $skipRows++;

                continue;
            }

            $slug   = $this->slug($supplier);
            $extId  = $dossNr . '-' . $slug;
            $extId  = substr($extId, 0, 80);
            $client = $get($c, $map['client'] ?? null);
            $dosNm  = $get($c, $map['dossier_name'] ?? null);
            $travel = SpreadsheetReader::toDate($c[$map['travel_date'] ?? -1] ?? null);
            $svc    = SpreadsheetReader::toDate($c[$map['service_date'] ?? -1] ?? null);
            $amount = round(SpreadsheetReader::toNumber($c[$map['buy'] ?? -1] ?? null), 2);
            $resCd  = $get($c, $map['res_code'] ?? null);

            if (! isset($groups[$extId])) {
                $groups[$extId] = [
                    'external_id'   => $extId,
                    'supplier'      => $supplier,
                    'doss_nr'       => $dossNr,
                    'client'        => $client,
                    'dossier_name'  => $dosNm,
                    'supplier_ref'  => $resCd,
                    'invoice_date'  => $travel,
                    'bad_dates'     => $travel === null ? 1 : 0,
                    'lines'         => [],
                    'errors'        => [],
                ];
            }
            $g = &$groups[$extId];
            if ($travel !== null && ($g['invoice_date'] === null || $travel < $g['invoice_date'])) {
                $g['invoice_date'] = $travel;
            }
            if ($travel === null) {
                $g['bad_dates']++;
            }
            if ($g['supplier_ref'] === '' && $resCd !== '') {
                $g['supplier_ref'] = $resCd;
            }
            if ($g['client'] === '' && $client !== '') {
                $g['client'] = $client;
            }
            if ($g['dossier_name'] === '' && $dosNm !== '') {
                $g['dossier_name'] = $dosNm;
            }

            $dup = $booking !== '' && isset($seenBooking[$booking]);
            $g['lines'][] = [
                'n'            => $r['n'],
                'booking_ref'  => $booking,
                'service_date' => $svc,
                'description'  => $get($c, $map['program'] ?? null),
                'party_name'   => $dosNm,
                'units'        => $get($c, $map['units'] ?? null),
                'nights'       => $get($c, $map['nights'] ?? null),
                'amount'       => $amount,
                'supp_inv_ref' => $resCd !== '' ? $resCd : null,
                'dup'          => $dup,
            ];
            unset($g);
        }

        // finalise each invoice
        $invoices = [];
        $sum      = [
            'groups' => 0, 'invoices_new' => 0, 'invoices_exists' => 0, 'invoices_error' => 0,
            'lines_total' => 0, 'lines_dup' => 0, 'lines_zero' => 0, 'skipped_rows' => $skipRows,
            'suppliers_new' => 0, 'customers_new' => 0, 'jobs_new' => 0,
            'amount_total' => 0.0, 'will_post' => 0, 'will_draft' => 0,
        ];

        foreach ($groups as $g) {
            $sum['groups']++;
            $liveLines = array_values(array_filter($g['lines'], static fn ($l) => ! $l['dup']));
            $dupCount  = count($g['lines']) - count($liveLines);
            $amountTot = 0.0;
            $zero      = 0;
            foreach ($liveLines as $l) {
                $amountTot += $l['amount'];
                if ($l['amount'] <= 0) {
                    $zero++;
                }
            }
            $amountTot = round($amountTot, 2);

            $errs   = [];
            $exists = isset($seenExt[$g['external_id']]);
            if ($g['invoice_date'] === null) {
                $errs[] = 'No usable travel date in this group.';
            }
            if ($liveLines === [] && ! $exists) {
                $errs[] = 'Every line is already imported (booking id seen before).';
            }

            $action = $exists ? 'exists' : ($errs ? 'error' : 'new');
            $post   = $action === 'new' && $amountTot > 0;

            // master data that would be created
            $supKey = $this->norm($g['supplier']);
            $cusKey = $this->norm($g['client']);
            if ($action === 'new') {
                if ($g['supplier'] !== '' && ! isset($existingSupplier[$supKey]) && ! isset($newSup[$supKey])) {
                    $newSup[$supKey] = $g['supplier'];
                }
                if ($g['client'] !== '' && ! isset($existingCustomer[$cusKey]) && ! isset($newCus[$cusKey])) {
                    $newCus[$cusKey] = $g['client'];
                }
                if ($g['doss_nr'] !== '' && ! isset($existingJob[$g['doss_nr']]) && ! isset($newJob[$g['doss_nr']])) {
                    $newJob[$g['doss_nr']] = $g['dossier_name'] ?: $g['doss_nr'];
                }
            }

            $inv = [
                'external_id'   => $g['external_id'],
                'supplier'      => $g['supplier'],
                'supplier_new'  => $g['supplier'] !== '' && ! isset($existingSupplier[$supKey]),
                'client'        => $g['client'],
                'doss_nr'       => $g['doss_nr'],
                'dossier_name'  => $g['dossier_name'],
                'invoice_date'  => $g['invoice_date'],
                'supplier_ref'  => $g['supplier_ref'] ?: null,
                'lines'         => $g['lines'],
                'live_lines'    => $liveLines,
                'line_count'    => count($g['lines']),
                'lines_dup'     => $dupCount,
                'lines_zero'    => $zero,
                'amount_total'  => $amountTot,
                'action'        => $action,
                'will_post'     => $post,
                'errors'        => $errs,
            ];
            $invoices[] = $inv;

            $sum['lines_total'] += count($g['lines']);
            $sum['lines_dup'] += $dupCount;
            $sum['lines_zero'] += $zero;
            if ($action === 'new') {
                $sum['invoices_new']++;
                $sum['amount_total'] += $amountTot;
                $post ? $sum['will_post']++ : $sum['will_draft']++;
            } elseif ($action === 'exists') {
                $sum['invoices_exists']++;
            } else {
                $sum['invoices_error']++;
            }
        }

        $sum['suppliers_new'] = count($newSup);
        $sum['customers_new'] = count($newCus);
        $sum['jobs_new']      = count($newJob);
        $sum['amount_total']  = round($sum['amount_total'], 2);

        // newest invoices first is noisy for a preview; keep file order but push
        // "exists" rows to the end so the actionable ones read first.
        usort($invoices, static fn ($a, $b) => [$a['action'] === 'exists' ? 1 : 0] <=> [$b['action'] === 'exists' ? 1 : 0]);

        return ['invoices' => $invoices, 'summary' => $sum, 'errors' => []];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param array<string,mixed> $parsed  result of parse()
     *
     * @return array{invoices_posted:int, invoices_draft:int, invoices_skipped:int, lines_written:int, suppliers:int, customers:int, jobs:int, post_errors: list<string>}
     */
    public function commit(array $parsed, int $batchId, string $fallbackCode): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $fallback = $this->accounts->where('code', $fallbackCode)->first();
        if (! $fallback || (int) $fallback['is_group'] === 1) {
            return ['invoices_posted' => 0, 'invoices_draft' => 0, 'invoices_skipped' => 0, 'lines_written' => 0,
                'suppliers' => 0, 'customers' => 0, 'jobs' => 0,
                'post_errors' => ["Fallback cost account '{$fallbackCode}' is missing or is a header account."]];
        }
        $fallbackId = (int) $fallback['id'];
        $baseCurId  = (int) model(CurrencyModel::class)->baseId();

        // --- 1. master data ------------------------------------------------
        $supMap = [];
        foreach ($this->suppliers->findAll() as $s) {
            $supMap[$this->norm($s['name'])] = (int) $s['id'];
        }
        $cusMap = [];
        foreach ($this->customers->findAll() as $c) {
            $cusMap[$this->norm($c['name'])] = (int) $c['id'];
        }
        $jobMap = [];
        foreach ($this->jobs->findAll() as $j) {
            $jobMap[(string) $j['code']] = (int) $j['id'];
        }

        $madeSup = 0;
        $madeCus = 0;
        $madeJob = 0;
        foreach ($parsed['invoices'] as $inv) {
            if ($inv['action'] !== 'new') {
                continue;
            }
            $sk = $this->norm($inv['supplier']);
            if ($inv['supplier'] !== '' && ! isset($supMap[$sk])) {
                $supMap[$sk] = (int) $this->suppliers->insert([
                    'code' => $this->nextCode($this->suppliers, 'S'), 'name' => $inv['supplier'], 'is_active' => 1,
                ], true);
                $madeSup++;
            }
            $ck = $this->norm($inv['client']);
            if ($inv['client'] !== '' && ! isset($cusMap[$ck])) {
                $cusMap[$ck] = (int) $this->customers->insert([
                    'code' => $this->nextCode($this->customers, 'C'), 'name' => $inv['client'], 'is_active' => 1,
                ], true);
                $madeCus++;
            }
            $dn = (string) $inv['doss_nr'];
            if ($dn !== '' && ! isset($jobMap[$dn])) {
                $jobMap[$dn] = (int) $this->jobs->insert([
                    'code'        => $dn,
                    'name'        => $inv['dossier_name'] ?: $dn,
                    'customer_id' => $inv['client'] !== '' ? ($cusMap[$ck] ?? null) : null,
                    'start_date'  => $inv['invoice_date'] ?: null, // travel / arrival date
                    'status'      => 'open',
                ], true);
                $madeJob++;
            }
        }

        // --- 2. invoices -------------------------------------------------
        $poster  = new PurchasePoster();
        $posted  = 0;
        $draft   = 0;
        $skipped = 0;
        $linesW  = 0;
        $errors  = [];

        foreach ($parsed['invoices'] as $inv) {
            if ($inv['action'] !== 'new') {
                $skipped++;

                continue;
            }
            $supId = $supMap[$this->norm($inv['supplier'])] ?? null;
            if ($supId === null) {
                $skipped++;
                $errors[] = "{$inv['external_id']}: no supplier.";

                continue;
            }
            $jobId = $jobMap[(string) $inv['doss_nr']] ?? null;

            $rawLines = [];
            foreach ($inv['live_lines'] as $l) {
                $rawLines[] = [
                    'account_id'    => $fallbackId,
                    'job_id'        => $jobId,
                    'description'   => $l['description'] ?: null,
                    'amount'        => $l['amount'],
                    'budget_amount' => $l['amount'],
                    'booking_ref'   => $l['booking_ref'] ?: null,
                    'service_date'  => $l['service_date'],
                    'party_name'    => $l['party_name'] ?: null,
                    'units'         => $l['units'] ?: null,
                    'nights'        => $l['nights'] ?: null,
                    'cost_source'   => 'budget',
                    'supp_inv_ref'  => $l['supp_inv_ref'],
                ];
            }
            if ($rawLines === []) {
                $skipped++;

                continue;
            }

            $res = $poster->saveInvoice([
                'supplier_ref'  => $inv['supplier_ref'],
                'supplier_id'   => $supId,
                'invoice_date'  => $inv['invoice_date'],
                'due_date'      => null,
                'currency_id'   => $baseCurId,
                'exchange_rate' => 1,
                'description'   => trim(($inv['client'] ? $inv['client'] . ' — ' : '') . ($inv['dossier_name'] ?: '')) ?: null,
            ], $rawLines);

            if (! $res['ok']) {
                $skipped++;
                $errors[] = "{$inv['external_id']}: " . implode(' ', $res['errors']);

                continue;
            }
            $invId = (int) $res['id'];
            $this->invoices->update($invId, [
                'external_id'     => $inv['external_id'],
                'import_batch_id' => $batchId,
                'source'          => 'jambix',
            ]);
            $linesW += count($rawLines);

            if ($inv['amount_total'] > 0) {
                $p = $poster->postInvoice($invId);
                if ($p['ok']) {
                    $posted++;
                } else {
                    $draft++;
                    $errors[] = "{$inv['external_id']} saved as draft: " . implode(' ', $p['errors']);
                }
            } else {
                $draft++;
            }
        }

        return [
            'invoices_posted'  => $posted,
            'invoices_draft'   => $draft,
            'invoices_skipped' => $skipped,
            'lines_written'    => $linesW,
            'suppliers'        => $madeSup,
            'customers'        => $madeCus,
            'jobs'             => $madeJob,
            'post_errors'      => array_slice($errors, 0, 100),
        ];
    }

    /**
     * Undo a committed batch: hard-delete its invoices (and their journals) as
     * long as nothing has been paid against them. Master data is left in place.
     *
     * @return array{deleted:int, kept:int}
     */
    public function revert(int $batchId): array
    {
        $poster  = new PurchasePoster();
        $deleted = 0;
        $kept    = 0;
        $ids     = $this->invoices->builder()->select('id')->where('import_batch_id', $batchId)->get()->getResultArray();
        foreach ($ids as $row) {
            $r = $poster->deleteInvoice((int) $row['id']);
            ($r['ok'] ?? false) ? $deleted++ : $kept++;
        }

        return ['deleted' => $deleted, 'kept' => $kept];
    }

    // ---------------------------------------------------------------- misc

    /** Collapse internal whitespace; optionally lower-case for use as a lookup key. */
    private function norm(string $s, bool $forKey = true): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return $forKey ? mb_strtolower($s) : $s;
    }

    private function slug(string $s): string
    {
        $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $s), '-'));

        return substr($s, 0, 60) ?: 'x';
    }

    /** highest numeric suffix seen for this prefix, cached per import run */
    private array $codeSeq = [];

    private function nextCode($model, string $prefix): string
    {
        if (! isset($this->codeSeq[$prefix])) {
            $max = 0;
            foreach ($model->like('code', $prefix, 'after')->findColumn('code') ?? [] as $code) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $code, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
            $this->codeSeq[$prefix] = $max;
        }

        return $prefix . str_pad((string) (++$this->codeSeq[$prefix]), 4, '0', STR_PAD_LEFT);
    }

    private function colLetter(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $i--;
            $s = chr(65 + $i % 26) . $s;
            $i = intdiv($i, 26);
        }

        return $s;
    }
}
