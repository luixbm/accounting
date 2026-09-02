<?php

namespace App\Libraries\Import;

use App\Models\CustomerModel;
use App\Models\JobModel;
use Config\Database;

/**
 * Imports a Jambix job / dossier report (Reportjob.xlsx) into the Jobs module.
 *
 * One row = one dossier. Rows are upserted into `jobs` keyed on the dossier
 * number (job code); an existing job is updated in place, a new code creates a
 * job. The client column resolves / creates a customer by name.
 *
 * Column mapping (defaults, all overridable on the map step):
 *   Dosier Nr.  -> job code            (required)
 *   Name        -> job name            (required)
 *   Client      -> customer (by name, auto-created)
 *   Travel Date -> start_date (arrival / travel date)
 *   End Date    -> end_date
 *   Created on  -> created_on
 *   Pax         -> pax
 *   Sales       -> sales_ref  (Jambix quoted sales - reference only)
 *   Buy         -> buy_ref    (Jambix quoted cost  - reference only)
 *   Cat.        -> category   (B2C / B2B / RTN ...)
 *   Status      -> jambix_status (raw); job.status = closed when it reads
 *                  cancelled / done / complete / closed, else open
 *
 * Nothing here touches the ledger - these are reference / analysis fields.
 * The job P&L is still built from the posted purchase / sales journal lines.
 */
class JobImporter
{
    /** logical field => [label, required?] */
    public const FIELDS = [
        'code'        => ['Dossier number -> job code', true],
        'name'        => ['Dossier name -> job name', true],
        'client'      => ['Client -> customer', false],
        'travel_date' => ['Travel date -> arrival', false],
        'end_date'    => ['End date', false],
        'created_on'  => ['Created on', false],
        'pax'         => ['Pax', false],
        'sales'       => ['Sales amount', false],
        'buy'         => ['Buy amount', false],
        'category'    => ['Category (Cat.)', false],
        'status'      => ['Status', false],
    ];

    /** raw status text (lower-cased) that maps job.status -> closed */
    public const CLOSED_STATUS = ['cancelled', 'canceled', 'cancel', 'done', 'complete', 'completed', 'closed', 'finished', 'archived'];

    private JobModel $jobs;
    private CustomerModel $customers;

    public function __construct()
    {
        $this->jobs      = model(JobModel::class);
        $this->customers = model(CustomerModel::class);
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
            'dosiernr'      => 'code',
            'dossiernr'     => 'code',
            'dossiernumber' => 'code',
            'dossier'       => 'code',
            'jobcode'       => 'code',
            'name'          => 'name',
            'dossiername'   => 'name',
            'jobname'       => 'name',
            'client'        => 'client',
            'customer'      => 'client',
            'traveldate'    => 'travel_date',
            'arrivaldate'   => 'travel_date',
            'arrival'       => 'travel_date',
            'startdate'     => 'travel_date',
            'enddate'       => 'end_date',
            'departuredate' => 'end_date',
            'createdon'     => 'created_on',
            'created'       => 'created_on',
            'creationdate'  => 'created_on',
            'pax'           => 'pax',
            'ttlpax'        => 'pax',
            'totalpax'      => 'pax',
            'sales'         => 'sales',
            'salesamount'   => 'sales',
            'revenue'       => 'sales',
            'buy'           => 'buy',
            'buyidr'        => 'buy',
            'cost'          => 'buy',
            'purchase'      => 'buy',
            'cat'           => 'category',
            'category'      => 'category',
            'type'          => 'category',
            'status'        => 'status',
            'bookingstatus' => 'status',
        ];

        $map = [];
        foreach ($headers as $i => $label) {
            $key = $norm($label);
            if (isset($alias[$key]) && ! isset($map[$alias[$key]])) {
                $map[$alias[$key]] = $i;
            }
        }

        return $map;
    }

    // ---------------------------------------------------------------- parse

    /**
     * @param list<list<mixed>>   $grid
     * @param array<string,int>   $map
     * @param array<string,mixed> $opt
     *
     * @return array{jobs: list<array<string,mixed>>, summary: array<string,mixed>}
     */
    public function parse(array $grid, array $map, array $opt): array
    {
        $headerRow = (int) ($opt['headerRow'] ?? 1);
        $get       = static fn (array $cells, ?int $col): string => $col === null ? '' : trim((string) ($cells[$col] ?? ''));

        $existing = [];
        foreach ($this->jobs->findAll() as $j) {
            $existing[$this->key($j['code'])] = $j;
        }
        $customerByName = [];
        foreach ($this->customers->findAll() as $c) {
            $customerByName[$this->key($c['name'])] = (int) $c['id'];
        }

        $out     = [];
        $seen    = [];
        $newCus  = [];
        $sum     = [
            'rows'       => 0, 'blank' => 0,
            'jobs_new'   => 0, 'jobs_update' => 0, 'jobs_error' => 0, 'jobs_dup' => 0,
            'cust_new'   => 0,
            'sales_total' => 0.0, 'buy_total' => 0.0, 'pax_total' => 0,
        ];

        foreach ($this->dataRows($grid, $headerRow) as $r) {
            $c    = $r['cells'];
            $code = $get($c, $map['code'] ?? null);
            $name = $get($c, $map['name'] ?? null);
            $client = $get($c, $map['client'] ?? null);

            if ($code === '' && $name === '' && $client === '') {
                $sum['blank']++;

                continue;
            }
            $sum['rows']++;

            $rawStatus = $get($c, $map['status'] ?? null);
            $sales     = round(SpreadsheetReader::toNumber($c[$map['sales'] ?? -1] ?? null), 2);
            $buy       = round(SpreadsheetReader::toNumber($c[$map['buy'] ?? -1] ?? null), 2);
            $paxRaw    = $get($c, $map['pax'] ?? null);
            $pax       = $paxRaw === '' ? null : (int) round(SpreadsheetReader::toNumber($paxRaw));

            $row = [
                'n'            => $r['n'],
                'code'         => mb_substr($code, 0, 30),
                'name'         => mb_substr($name !== '' ? $name : $code, 0, 150),
                'client'       => $client,
                'start_date'   => SpreadsheetReader::toDate($c[$map['travel_date'] ?? -1] ?? null),
                'end_date'     => SpreadsheetReader::toDate($c[$map['end_date'] ?? -1] ?? null),
                'created_on'   => SpreadsheetReader::toDate($c[$map['created_on'] ?? -1] ?? null),
                'pax'          => $pax,
                'sales_ref'    => $sales,
                'buy_ref'      => $buy,
                'category'     => mb_substr($get($c, $map['category'] ?? null), 0, 10) ?: null,
                'jambix_status' => mb_substr($rawStatus, 0, 20) ?: null,
                'status'       => in_array(strtolower($rawStatus), self::CLOSED_STATUS, true) ? 'closed' : 'open',
                'errors'       => [],
            ];

            if ($row['code'] === '') {
                $row['errors'][] = 'No dossier number.';
            }
            if ($row['name'] === '') {
                $row['errors'][] = 'No dossier name.';
            }

            $k = $this->key($row['code']);
            if ($row['code'] !== '' && isset($seen[$k])) {
                $row['errors'][] = 'Duplicate dossier number in this file (row ' . $seen[$k] . ').';
            }
            if ($row['code'] !== '') {
                $seen[$k] = $r['n'];
            }

            // customer resolution preview
            $row['client_id'] = null;
            $row['client_new'] = false;
            if ($client !== '') {
                $ck = $this->key($client);
                if (isset($customerByName[$ck])) {
                    $row['client_id'] = $customerByName[$ck];
                } else {
                    $row['client_new'] = true;
                    $newCus[$ck]       = $client;
                }
            }

            if ($row['errors']) {
                $row['action'] = 'error';
                $sum['jobs_error']++;
            } elseif (isset($existing[$k])) {
                $row['action']  = 'update';
                $row['job_id']  = (int) $existing[$k]['id'];
                $sum['jobs_update']++;
            } else {
                $row['action'] = 'new';
                $sum['jobs_new']++;
            }

            if ($row['action'] !== 'error') {
                $sum['sales_total'] += $sales;
                $sum['buy_total']   += $buy;
                $sum['pax_total']   += (int) $pax;
            }

            $out[] = $row;
        }

        $sum['cust_new']    = count($newCus);
        $sum['sales_total'] = round($sum['sales_total'], 2);
        $sum['buy_total']   = round($sum['buy_total'], 2);

        // actionable rows first, errors and "update" after "new" for a readable preview
        $rank = ['new' => 0, 'update' => 1, 'error' => 2];
        usort($out, static fn ($a, $b) => [$rank[$a['action']], $a['n']] <=> [$rank[$b['action']], $b['n']]);

        return ['jobs' => $out, 'summary' => $sum];
    }

    // ---------------------------------------------------------------- commit

    /**
     * @param array<string,mixed> $parsed result of parse()
     *
     * @return array{created:int, updated:int, skipped:int, customers:int, errors: list<string>}
     */
    public function commit(array $parsed, int $batchId): array
    {
        $db  = Database::connect();
        $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'customers' => 0, 'errors' => []];

        $customerByName = [];
        foreach ($this->customers->findAll() as $c) {
            $customerByName[$this->key($c['name'])] = (int) $c['id'];
        }
        $createdIds = [];

        $db->transStart();

        foreach ($parsed['jobs'] as $row) {
            if (($row['action'] ?? '') === 'error') {
                $res['skipped']++;

                continue;
            }

            $customerId = null;
            if (($row['client'] ?? '') !== '') {
                $ck = $this->key($row['client']);
                if (isset($customerByName[$ck])) {
                    $customerId = $customerByName[$ck];
                } else {
                    $customerId = (int) $this->customers->insert([
                        'code'      => $this->nextCustomerCode(),
                        'name'      => mb_substr($row['client'], 0, 150),
                        'is_active' => 1,
                    ], true);
                    $customerByName[$ck] = $customerId;
                    $res['customers']++;
                }
            }

            $fields = [
                'name'          => $row['name'],
                'customer_id'   => $customerId,
                'status'        => $row['status'],
                'start_date'    => $row['start_date'],
                'end_date'      => $row['end_date'],
                'pax'           => $row['pax'],
                'category'      => $row['category'],
                'sales_ref'     => $row['sales_ref'],
                'buy_ref'       => $row['buy_ref'],
                'created_on'    => $row['created_on'],
                'jambix_status' => $row['jambix_status'],
                'source'        => 'jambix',
            ];

            if (($row['action'] ?? '') === 'update' && ! empty($row['job_id'])) {
                // never downgrade an arrival date that is already set by a fuller
                // source unless the import actually carries one
                if ($fields['start_date'] === null) {
                    unset($fields['start_date']);
                }
                $this->jobs->update((int) $row['job_id'], $fields);
                $res['updated']++;
            } else {
                $id = (int) $this->jobs->insert($fields + [
                    'code'            => $row['code'],
                    'import_batch_id' => $batchId,
                ], true);
                $createdIds[] = $id;
                $res['created']++;
            }
        }

        $db->transComplete();

        $res['created_ids'] = $createdIds;

        return $res;
    }

    /**
     * Delete jobs this batch created, as long as nothing has since been booked
     * against them. Jobs the batch only *updated* are left as they are.
     *
     * @return array{deleted:int, kept:int}
     */
    public function revert(int $batchId): array
    {
        $db   = Database::connect();
        $rows = $this->jobs->where('import_batch_id', $batchId)->findAll();

        $deleted = 0;
        $kept    = 0;
        foreach ($rows as $j) {
            $id  = (int) $j['id'];
            $ref = (int) $db->table('journal_lines')->where('job_id', $id)->countAllResults()
                + (int) $db->table('purchase_invoice_lines')->where('job_id', $id)->countAllResults()
                + (int) $db->table('sales_invoice_lines')->where('job_id', $id)->countAllResults();
            if ($ref > 0) {
                $kept++;

                continue;
            }
            $this->jobs->delete($id);
            $deleted++;
        }

        return ['deleted' => $deleted, 'kept' => $kept];
    }

    // ---------------------------------------------------------------- misc

    private function key(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    private function nextCustomerCode(): string
    {
        $last = $this->customers->like('code', 'C', 'after')->orderBy('code', 'DESC')->first();
        $n    = $last ? ((int) preg_replace('/\D/', '', (string) $last['code']) + 1) : 1;

        return 'C' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function colLetter(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $i--;
            $s = chr(65 + ($i % 26)) . $s;
            $i = intdiv($i, 26);
        }

        return $s;
    }
}
