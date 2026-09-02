<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'decimal_places' => 2, 'is_base' => 1, 'is_active' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => 0, 'is_active' => 1],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => 0, 'is_active' => 1],
        ];

        $builder = $this->db->table('currencies');
        foreach ($rows as $r) {
            $exists = $builder->getWhere(['code' => $r['code']])->getRow();
            if ($exists) {
                continue;
            }
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
            $builder->insert($r);
        }
    }
}
