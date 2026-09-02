<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Api\JobIngest;
use App\Models\JobModel;

/**
 * Push Jambix job / dossier rows into the Jobs module. Upsert is keyed on the
 * dossier number (job code). Reference figures only - the ledger is untouched.
 */
class JobController extends BaseApiController
{
    public function create()
    {
        if ($deny = $this->guardAbility('job:write')) {
            return $deny;
        }

        $result = (new JobIngest())->upsert($this->body());

        if ($result['status'] === 'error') {
            return $this->fail('No job could be accepted.', $result['code'], 'invalid_request', $result['errors'] ?? []);
        }

        return $this->respond([
            'summary' => $result['summary'],
            'results' => $result['results'],
        ], $result['code']);
    }

    public function show(string $code)
    {
        if ($deny = $this->guardAbility('read')) {
            return $deny;
        }

        $code = urldecode($code);
        $job  = model(JobModel::class)->where('code', $code)->first();
        if (! $job) {
            return $this->fail("No job matches '{$code}'.", 404, 'not_found');
        }

        $net    = (float) $job['sales_ref'] - (float) $job['buy_ref'];
        $margin = (float) $job['sales_ref'] != 0.0 ? round($net / (float) $job['sales_ref'] * 100, 2) : null;

        return $this->respond(['job' => [
            'code'          => $job['code'],
            'name'          => $job['name'],
            'status'        => $job['status'],
            'customer_id'   => $job['customer_id'] !== null ? (int) $job['customer_id'] : null,
            'arrival_date'  => $job['start_date'],
            'end_date'      => $job['end_date'],
            'created_on'    => $job['created_on'],
            'pax'           => $job['pax'] !== null ? (int) $job['pax'] : null,
            'category'      => $job['category'],
            'jambix_status' => $job['jambix_status'],
            'sales_ref'     => $job['sales_ref'] !== null ? (float) $job['sales_ref'] : null,
            'buy_ref'       => $job['buy_ref'] !== null ? (float) $job['buy_ref'] : null,
            'net_ref'       => ($job['sales_ref'] !== null || $job['buy_ref'] !== null) ? round($net, 2) : null,
            'margin_ref'    => $margin,
        ]]);
    }
}
