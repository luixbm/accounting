<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * One-shot install seeder:  php spark db:seed InitialSeeder
 */
class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CurrencySeeder::class);
        $this->call(ChartOfAccountsSeeder::class);
        $this->call(AdminUserSeeder::class);
    }
}
