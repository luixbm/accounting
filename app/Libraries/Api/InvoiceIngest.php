<?php

namespace App\Libraries\Api;

use App\Libraries\Accounting\PurchasePoster;
use App\Libraries\Accounting\SalesPoster;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\JobModel;
use App\Models\PurchaseInvoiceLineModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SalesInvoiceLineModel;
use App\Models\SalesInvoiceModel;
use App\Models\SupplierModel;

/**
 * Ingests a third-party invoice payload into the Sales or Purchase module.
 * `kind` (sales|purchase) parameterises the whole thing so one code path serves
 * both. Idempotent on (company_id, external_id): a repeat call returns the
 * existing invoice untouched.
 */
class InvoiceIngest
{
    private string $kind;

    public function __construct(string $kind)
    {
        $this->kind = $kind === 'purchase' ? 'purchase' : 'sales';
    }

    /** @return array<string,string> */
    private function cfg(): array
    {
        return $this->kind === 'purchase'
            ? [
                'invModel'  => PurchaseInvoiceModel::class,
                'lineModel' => PurchaseInvoiceLineModel::class,
                'partyModel' => SupplierModel::class,
                'partyKey'  => 'supplier', 'partyId' => 'supplier_id', 'partyRef' => 'supplier_ref',
                'code' => 'S',
            ]
            : [
                'invModel'  => SalesInvoiceModel::class,
                'lineModel' => SalesInvoiceLineModel::class,
                'partyModel' => CustomerModel::class,
                'partyKey'  => 'customer', 'partyId' => 'customer_id', 'partyRef' => 'customer_ref',
                'code' => 'C',
            ];
    }

    private function poster()
    {
        return $this->kind === 'purchase' ? new PurchasePoster() : new SalesPoster();
    }

    /** Fallback line account when a payload line omits account_code (Jambix push). */
    private function defaultAccountCode(): string
    {
        return $this->kind === 'purchase'
            ? (string) (acc_setting('jambixFallbackAcct') ?: '52000')
            : (string) (acc_setting('jambixSalesAcct') ?: '42000');
    }

    /**
     * Resolve a job by code; auto-create it (open, linked to the invoice's
     * customer for sales) when the code is new. Blank code -> no job.
     *
     * @return array{id:int|null}
     */
    private function resolveJob($jobModel, $code, $name, ?int $customerId, ?string $arrivalDate = null): array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return ['id' => null];
        }
        $row = $jobModel->where('code', $code)->first();
        if ($row) {
            // backfill the arrival date if the job was created without one
            if ($arrivalDate !== null && empty($row['start_date'])) {
                $jobModel->update((int) $row['id'], ['start_date' => $arrivalDate]);
            }

            return ['id' => (int) $row['id']];
        }
        $id = (int) $jobModel->insert([
            'code'        => mb_substr($code, 0, 30),
            'name'        => trim((string) $name) ?: $code,
            'customer_id' => $this->kind === 'sales' ? $customerId : null,
            'start_date'  => $arrivalDate,
            'status'      => 'open',
        ], true);

        return ['id' => $id];
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array{status:string, code:int, invoice?:array<string,mixed>, errors?:list<string>}
     */
    public function upsert(array $body): array
    {
        $c        = $this->cfg();
        $invModel = model($c['invModel']);

        $extId = trim((string) ($body['external_id'] ?? ''));
        if ($extId === '') {
            return ['status' => 'error', 'code' => 422, 'errors' => ['external_id is required.']];
        }
        if (mb_strlen($extId) > 80) {
            return ['status' => 'error', 'code' => 422, 'errors' => ['external_id must be 80 characters or fewer.']];
        }

        // Idempotency: same external_id for this company -> return what exists.
        $existing = $invModel->where('external_id', $extId)->first();
        if ($existing) {
            return ['status' => 'exists', 'code' => 200, 'invoice' => $this->format((int) $existing['id'])];
        }

        // --- resolve currency
        $curModel = model(CurrencyModel::class);
        $curCode  = strtoupper(trim((string) ($body['currency'] ?? '')));
        $currency = $curCode !== '' ? $curModel->where('code', $curCode)->first() : $curModel->base();
        if (! $currency) {
            return ['status' => 'error', 'code' => 422, 'errors' => ["Unknown currency '{$curCode}'."]];
        }
        $rate = (int) $currency['is_base'] === 1 ? 1.0 : (float) ($body['exchange_rate'] ?? 0);
        if ((int) $currency['is_base'] === 0 && $rate <= 0) {
            return ['status' => 'error', 'code' => 422, 'errors' => ['exchange_rate is required for a non-base currency.']];
        }

        // --- resolve party (code, then name, then auto-create from name)
        $party = $this->resolveParty($body['customer'] ?? $body['supplier'] ?? $body['party'] ?? null);
        if (isset($party['error'])) {
            return ['status' => 'error', 'code' => 422, 'errors' => [$party['error']]];
        }

        // --- resolve lines
        $date = $this->normDate($body['invoice_date'] ?? null);
        if ($date === null) {
            return ['status' => 'error', 'code' => 422, 'errors' => ['invoice_date is required (YYYY-MM-DD).']];
        }
        if (! is_array($body['lines'] ?? null) || $body['lines'] === []) {
            return ['status' => 'error', 'code' => 422, 'errors' => ['At least one line is required.']];
        }

        $accModel = model(AccountModel::class);
        $jobModel = model(JobModel::class);
        $rawLines = [];
        $errs     = [];
        foreach ($body['lines'] as $i => $l) {
            $n    = $i + 1;
            $code = trim((string) ($l['account_code'] ?? '')) ?: $this->defaultAccountCode();
            $acc  = $accModel->where('code', $code)->first();
            if (! $acc) {
                $errs[] = "Line {$n}: unknown account_code '{$code}'.";

                continue;
            }
            if ((int) $acc['is_group'] === 1 || (int) $acc['is_active'] === 0) {
                $errs[] = "Line {$n}: account {$code} is a header or inactive account.";

                continue;
            }
            $amount = round((float) str_replace([',', ' '], '', (string) ($l['amount'] ?? 0)), 2);
            if ($amount <= 0) {
                $errs[] = "Line {$n}: amount must be greater than zero.";

                continue;
            }
            $jr    = $this->resolveJob($jobModel, $l['job_code'] ?? null, $l['job_name'] ?? null, (int) $party['id'], $date);
            $jobId = $jr['id'];

            $rawLines[] = [
                'account_id'   => (int) $acc['id'],
                'job_id'       => $jobId,
                'description'  => isset($l['description']) ? (string) $l['description'] : null,
                'amount'       => $amount,
                'booking_ref'  => isset($l['booking_ref']) ? (string) $l['booking_ref'] : null,
                'service_date' => $l['service_date'] ?? null,
                'party_name'   => isset($l['party_name']) ? (string) $l['party_name'] : null,
                'units'        => isset($l['units']) ? (string) $l['units'] : null,
                'nights'       => isset($l['nights']) ? (string) $l['nights'] : null,
                'pax'          => isset($l['pax']) ? (string) $l['pax'] : null,
                'duration'     => isset($l['duration']) ? (string) $l['duration'] : null,
                'remark'       => isset($l['remark']) ? (string) $l['remark'] : null,
            ];
        }
        if ($errs) {
            return ['status' => 'error', 'code' => 422, 'errors' => $errs];
        }

        // --- build + save
        $header = [
            $c['partyRef']  => $body['reference'] ?? null,
            $c['partyId']   => $party['id'],
            'invoice_date'  => $date,
            'due_date'      => $this->normDate($body['due_date'] ?? null),
            'currency_id'   => (int) $currency['id'],
            'exchange_rate' => $rate,
            'description'   => $body['description'] ?? null,
            'ppn_amount'    => $body['ppn_amount'] ?? 0,
            'pph_amount'    => $body['pph_amount'] ?? 0,
        ];

        $poster = $this->poster();
        $res    = $poster->saveInvoice($header, $rawLines);
        if (! $res['ok']) {
            return ['status' => 'error', 'code' => 422, 'errors' => $res['errors']];
        }
        $invId = (int) $res['id'];
        $invModel->update($invId, ['external_id' => $extId, 'source' => 'api']);

        $posted     = false;
        $postErrors = [];
        if (! empty($body['post'])) {
            $p = $poster->postInvoice($invId);
            if ($p['ok']) {
                $posted = true;
            } else {
                $postErrors = $p['errors'];
            }
        }

        $out = ['status' => 'created', 'code' => 201, 'invoice' => $this->format($invId)];
        if ($postErrors) {
            $out['invoice']['post_errors'] = $postErrors;
        }

        return $out;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param mixed $spec  {code?, name?} or a bare string treated as a code
     *
     * @return array{id:int}|array{error:string}
     */
    private function resolveParty($spec): array
    {
        $c     = $this->cfg();
        $model = model($c['partyModel']);

        $code = null;
        $name = null;
        if (is_string($spec)) {
            $code = trim($spec);
        } elseif (is_array($spec)) {
            $code = isset($spec['code']) ? trim((string) $spec['code']) : null;
            $name = isset($spec['name']) ? trim((string) $spec['name']) : null;
        }

        if (($code === null || $code === '') && ($name === null || $name === '')) {
            return ['error' => "A {$c['partyKey']} code or name is required."];
        }

        if ($code !== null && $code !== '') {
            $row = $model->where('code', $code)->first();
            if ($row) {
                return ['id' => (int) $row['id']];
            }
            if ($name === null || $name === '') {
                return ['error' => "Unknown {$c['partyKey']} code '{$code}'."];
            }
        }

        // match by name (DB collation is case-insensitive), else create
        $row = $model->where('name', $name)->first();
        if ($row) {
            return ['id' => (int) $row['id']];
        }

        $newId = (int) $model->insert([
            'code'      => $this->nextPartyCode($model, $c['code']),
            'name'      => $name,
            'is_active' => 1,
        ], true);

        return ['id' => $newId];
    }

    private function nextPartyCode($model, string $prefix): string
    {
        $last = $model->like('code', $prefix, 'after')->orderBy('code', 'DESC')->first();
        $n    = $last ? ((int) preg_replace('/\D/', '', (string) $last['code']) + 1) : 1;

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function normDate($v): ?string
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        $ts = strtotime(trim($v));

        return $ts ? date('Y-m-d', $ts) : null;
    }

    /** @return array<string,mixed> */
    public function format(int $id): array
    {
        $c   = $this->cfg();
        $inv = model($c['invModel'])->find($id);
        if (! $inv) {
            return [];
        }
        $lines = model($c['lineModel'])->where('invoice_id', $id)->orderBy('line_no')->findAll();

        return [
            'id'           => (int) $inv['id'],
            'kind'         => $this->kind,
            'internal_no'  => $inv['internal_no'],
            'external_id'  => $inv['external_id'],
            'status'       => $inv['status'],
            'source'       => $inv['source'],
            $c['partyId']  => (int) $inv[$c['partyId']],
            'reference'    => $inv[$c['partyRef']],
            'invoice_date' => $inv['invoice_date'],
            'due_date'     => $inv['due_date'],
            'currency_id'  => (int) $inv['currency_id'],
            'exchange_rate' => (float) $inv['exchange_rate'],
            'subtotal'     => (float) $inv['subtotal'],
            'ppn_amount'   => (float) $inv['ppn_amount'],
            'pph_amount'   => (float) $inv['pph_amount'],
            'total'        => (float) $inv['total'],
            'total_base'   => (float) $inv['total_base'],
            'journal_id'   => $inv['journal_id'] ? (int) $inv['journal_id'] : null,
            'lines'        => array_map(static fn ($l) => [
                'account_id'   => (int) $l['account_id'],
                'description'  => $l['description'],
                'job_id'       => $l['job_id'] ? (int) $l['job_id'] : null,
                'amount'       => (float) $l['amount'],
                'amount_base'  => (float) $l['amount_base'],
                'booking_ref'  => $l['booking_ref'] ?? null,
                'service_date' => $l['service_date'] ?? null,
                'party_name'   => $l['party_name'] ?? null,
                'units'        => $l['units'] ?? null,
                'nights'       => $l['nights'] ?? null,
                'pax'          => $l['pax'] ?? null,
                'duration'     => $l['duration'] ?? null,
                'remark'       => $l['remark'] ?? null,
            ], $lines),
        ];
    }
}
