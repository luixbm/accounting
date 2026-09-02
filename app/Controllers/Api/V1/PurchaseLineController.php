<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Api\PurchaseCostUpdate;

/**
 * Budget -> actual cost updates for purchase-invoice lines (the n8n supplier-
 * invoice pipeline). See docs/API.md.
 */
class PurchaseLineController extends BaseApiController
{
    public function costs()
    {
        if ($deny = $this->guardAbility('purchase:write')) {
            return $deny;
        }

        $result = (new PurchaseCostUpdate())->apply($this->body());

        if ($result['status'] === 'error') {
            return $this->fail($result['payload']['message'] ?? 'Request rejected.', $result['code'], 'invalid_request');
        }

        return $this->respond($result['payload'], $result['code']);
    }

    /** Purchase-invoice lines still awaiting an actual cost. Ability: read. */
    public function pending()
    {
        if ($deny = $this->guardAbility('read')) {
            return $deny;
        }

        $result = (new PurchaseCostUpdate())->pending($this->request->getGet() ?: []);

        return $this->respond($result['payload'], $result['code']);
    }
}
