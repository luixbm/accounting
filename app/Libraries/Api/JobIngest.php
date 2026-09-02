<?php

namespace App\Libraries\Api;

use App\Models\CustomerModel;
use App\Models\JobModel;

/**
 * Upsert Jambix job / dossier rows through the REST API. Keyed on the dossier
 * number (job code) within the token's company: a new code creates a job, an
 * existing one is patched. The client resolves / creates a customer by name.
 *
 * Sales / buy are stored as Jambix reference figures only - the job P&L reports
 * keep reading the posted purchase / sales transactions. Nothing here posts to
 * the ledger.
 */
class JobIngest
{
    private JobModel $jobs;
    private CustomerModel $customers;

    /** raw status text (lower-cased) that maps job.status -> closed */
    private const CLOSED_STATUS = ['cancelled', 'canceled', 'cancel', 'done', 'complete', 'completed', 'closed', 'finished', 'archived'];

    public function __construct()
    {
        $this->jobs      = model(JobModel::class);
        $this->customers = model(CustomerModel::class);
    }

    /**
     * Accepts either a single job object or `{ "jobs": [ ... ] }`.
     *
     * @param array<string,mixed> $body
     *
     * @return array{status:string, code:int, results?:list<array<string,mixed>>, errors?:list<string>, summary?:array<string,int>}
     */
    public function upsert(array $body): array
    {
        $list = [];
        if (isset($body['jobs']) && is_array($body['jobs'])) {
            $list = array_values(array_filter($body['jobs'], 'is_array'));
        } elseif ($body !== []) {
            $list = [$body];
        }
        if ($list === []) {
            return ['status' => 'error', 'code' => 422, 'errors' => ['Send a job object or a non-empty "jobs" array.']];
        }
        if (count($list) > 1000) {
            return ['status' => 'error', 'code' => 422, 'errors' => ['At most 1000 jobs per request.']];
        }

        $results = [];
        $summary = ['created' => 0, 'updated' => 0, 'failed' => 0, 'customers' => 0];

        foreach ($list as $i => $spec) {
            $one = $this->one($spec, $summary);
            $one['index'] = $i;
            $results[]    = $one;
            $summary[$one['result'] === 'created' ? 'created' : ($one['result'] === 'updated' ? 'updated' : 'failed')]++;
        }

        // 207-ish semantics folded into 200/422: 200 if anything succeeded
        $ok = $summary['created'] + $summary['updated'] > 0;

        return [
            'status'  => $ok ? 'ok' : 'error',
            'code'    => $ok ? 200 : 422,
            'results' => $results,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string,mixed> $spec
     * @param array<string,int>   $summary  mutated: customers count
     *
     * @return array{result:string, code:string, job_id?:int, errors?:list<string>}
     */
    private function one(array $spec, array &$summary): array
    {
        $code = trim((string) ($spec['code'] ?? $spec['dossier_nr'] ?? $spec['doss_nr'] ?? ''));
        if ($code === '') {
            return ['result' => 'error', 'code' => '', 'errors' => ['code (dossier number) is required.']];
        }
        $code = mb_substr($code, 0, 30);
        $name = trim((string) ($spec['name'] ?? $spec['dossier_name'] ?? ''));

        $rawStatus = trim((string) ($spec['status'] ?? ''));
        $fields    = array_filter([
            'start_date'    => $this->date($spec['arrival_date'] ?? $spec['travel_date'] ?? $spec['start_date'] ?? null),
            'end_date'      => $this->date($spec['end_date'] ?? null),
            'created_on'    => $this->date($spec['created_on'] ?? null),
            'category'      => $this->str($spec['category'] ?? $spec['cat'] ?? null, 10),
            'jambix_status' => $this->str($rawStatus, 20),
        ], static fn ($v) => $v !== null);

        if (array_key_exists('pax', $spec) && $spec['pax'] !== null && $spec['pax'] !== '') {
            $fields['pax'] = (int) round((float) $spec['pax']);
        }
        foreach (['sales' => 'sales_ref', 'buy' => 'buy_ref'] as $in => $col) {
            if (array_key_exists($in, $spec) && $spec[$in] !== null && $spec[$in] !== '') {
                $fields[$col] = round((float) $spec[$in], 2);
            }
        }
        if ($rawStatus !== '') {
            $fields['status'] = in_array(strtolower($rawStatus), self::CLOSED_STATUS, true) ? 'closed' : 'open';
        }

        // customer by name / code
        $clientSpec = $spec['customer'] ?? $spec['client'] ?? null;
        $custResult = $this->resolveCustomer($clientSpec);
        if (isset($custResult['error'])) {
            return ['result' => 'error', 'code' => $code, 'errors' => [$custResult['error']]];
        }
        if (isset($custResult['id'])) {
            $fields['customer_id'] = $custResult['id'];
        }
        if (! empty($custResult['created'])) {
            $summary['customers']++;
        }

        $fields['source'] = 'api';

        $existing = $this->jobs->where('code', $code)->first();
        if ($existing) {
            if ($name !== '') {
                $fields['name'] = mb_substr($name, 0, 150);
            }
            $this->jobs->update((int) $existing['id'], $fields);

            return ['result' => 'updated', 'code' => $code, 'job_id' => (int) $existing['id']];
        }

        $id = (int) $this->jobs->insert($fields + [
            'code'   => $code,
            'name'   => mb_substr($name !== '' ? $name : $code, 0, 150),
            'status' => $fields['status'] ?? 'open',
        ], true);

        return ['result' => 'created', 'code' => $code, 'job_id' => $id];
    }

    /**
     * @param mixed $spec  string name, {code}, {name} or {code,name}
     *
     * @return array{id?:int, created?:bool, error?:string}
     */
    private function resolveCustomer($spec): array
    {
        $code = null;
        $name = null;
        if (is_string($spec)) {
            $name = trim($spec);
        } elseif (is_array($spec)) {
            $code = isset($spec['code']) ? trim((string) $spec['code']) : null;
            $name = isset($spec['name']) ? trim((string) $spec['name']) : null;
        }
        if (($code === null || $code === '') && ($name === null || $name === '')) {
            return []; // no customer given - fine
        }

        if ($code !== null && $code !== '') {
            $row = $this->customers->where('code', $code)->first();
            if ($row) {
                return ['id' => (int) $row['id']];
            }
            if ($name === null || $name === '') {
                return ['error' => "Unknown customer code '{$code}'."];
            }
        }

        $row = $this->customers->where('name', $name)->first();
        if ($row) {
            return ['id' => (int) $row['id']];
        }

        $id = (int) $this->customers->insert([
            'code'      => $this->nextCustomerCode(),
            'name'      => mb_substr($name, 0, 150),
            'is_active' => 1,
        ], true);

        return ['id' => $id, 'created' => true];
    }

    private function nextCustomerCode(): string
    {
        $last = $this->customers->like('code', 'C', 'after')->orderBy('code', 'DESC')->first();
        $n    = $last ? ((int) preg_replace('/\D/', '', (string) $last['code']) + 1) : 1;

        return 'C' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function date($v): ?string
    {
        if (! is_string($v) && ! is_numeric($v)) {
            return null;
        }
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        $ts = strtotime($v);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function str($v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
