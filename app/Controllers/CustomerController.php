<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use CodeIgniter\Model;

class CustomerController extends PartyController
{
    protected string $kind  = 'customer';
    protected string $route = 'customers';
    protected string $label = 'Customer';

    protected function model(): Model
    {
        return model(CustomerModel::class);
    }

    protected function payload(): array
    {
        return parent::payload() + [
            'client_group' => trim((string) $this->request->getPost('client_group')) ?: null,
            'country'      => trim((string) $this->request->getPost('country')) ?: null,
        ];
    }
}
