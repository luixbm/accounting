<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Api\ApiContext;
use App\Models\AccountModel;
use App\Models\CustomerModel;
use App\Models\JobModel;
use App\Models\SupplierModel;

/**
 * Read-only helpers an integrator needs to map their data to this ledger:
 * an auth check plus account / party / job lookups. All are scoped to the
 * token's company by TenantModel.
 */
class MetaController extends BaseApiController
{
    public function ping()
    {
        $co = active_company();

        return $this->respond([
            'ok'        => true,
            'company'   => $co ? ['id' => (int) $co['id'], 'code' => $co['code'], 'name' => $co['name']] : null,
            'token'     => ApiContext::token()['name'] ?? null,
            'abilities' => ApiContext::abilities(),
        ]);
    }

    public function accounts()
    {
        if ($deny = $this->guardAbility('read')) {
            return $deny;
        }
        $m = model(AccountModel::class)->where('is_group', 0)->where('is_active', 1);
        if ($type = $this->request->getGet('type')) {
            $m->where('type', $type);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $m->groupStart()->like('code', $q)->orLike('name', $q)->groupEnd();
        }
        $rows = $m->orderBy('code', 'ASC')->findAll(500);

        return $this->respond(['accounts' => array_map(static fn ($a) => [
            'code' => $a['code'], 'name' => $a['name'], 'type' => $a['type'],
            'normal_balance' => $a['normal_balance'], 'is_cash' => (bool) $a['is_cash'],
            'subledger' => $a['subledger'],
        ], $rows)]);
    }

    public function parties(string $kind)
    {
        if ($deny = $this->guardAbility('read')) {
            return $deny;
        }
        $kind  = $kind === 'purchase' ? 'purchase' : 'sales';
        $model = $kind === 'purchase' ? model(SupplierModel::class) : model(CustomerModel::class);
        $m     = $model;
        if ($q = trim((string) $this->request->getGet('q'))) {
            $m = $m->groupStart()->like('code', $q)->orLike('name', $q)->groupEnd();
        }
        $rows = $m->orderBy('name', 'ASC')->findAll(500);

        return $this->respond([($kind === 'purchase' ? 'suppliers' : 'customers') => array_map(static fn ($p) => [
            'code' => $p['code'], 'name' => $p['name'], 'npwp' => $p['npwp'] ?? null,
            'is_active' => (bool) $p['is_active'],
        ], $rows)]);
    }

    public function jobs()
    {
        if ($deny = $this->guardAbility('read')) {
            return $deny;
        }
        $rows = model(JobModel::class)->orderBy('code', 'ASC')->findAll(500);

        return $this->respond(['jobs' => array_map(static fn ($j) => [
            'code' => $j['code'], 'name' => $j['name'], 'status' => $j['status'],
        ], $rows)]);
    }
}
