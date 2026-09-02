<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Api\InvoiceIngest;
use App\Models\PurchaseInvoiceModel;
use App\Models\SalesInvoiceModel;

/**
 * Push and read back Sales / Purchase invoices. `kind` (sales|purchase) comes
 * from the route so one controller serves both.
 */
class InvoiceController extends BaseApiController
{
    public function create(string $kind)
    {
        $kind = $kind === 'purchase' ? 'purchase' : 'sales';
        if ($deny = $this->guardAbility("{$kind}:write")) {
            return $deny;
        }

        $result = (new InvoiceIngest($kind))->upsert($this->body());

        if ($result['status'] === 'error') {
            return $this->fail('The invoice could not be accepted.', $result['code'], 'invalid_request', $result['errors'] ?? []);
        }

        return $this->respond([
            'created'  => $result['status'] === 'created',
            'invoice'  => $result['invoice'],
        ], $result['code']);
    }

    public function show(string $kind, string $ref)
    {
        $kind = $kind === 'purchase' ? 'purchase' : 'sales';
        if ($deny = $this->guardAbility('read')) {
            return $deny;
        }

        $model = $kind === 'purchase' ? model(PurchaseInvoiceModel::class) : model(SalesInvoiceModel::class);
        $ref   = urldecode($ref);
        $inv   = $model->groupStart()->where('external_id', $ref)->orWhere('internal_no', $ref)->groupEnd()->first();
        if (! $inv) {
            return $this->fail("No {$kind} invoice matches '{$ref}'.", 404, 'not_found');
        }

        return $this->respond(['invoice' => (new InvoiceIngest($kind))->format((int) $inv['id'])]);
    }
}
