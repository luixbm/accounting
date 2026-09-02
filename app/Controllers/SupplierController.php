<?php

namespace App\Controllers;

use App\Models\SupplierModel;
use CodeIgniter\Model;

class SupplierController extends PartyController
{
    protected string $kind  = 'supplier';
    protected string $route = 'suppliers';
    protected string $label = 'Supplier';

    protected function model(): Model
    {
        return model(SupplierModel::class);
    }
}
