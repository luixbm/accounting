<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Exchange rates become per-company: each company keeps rates against its own
 * base (functional) currency. USD -> IDR for the Jakarta entities, USD -> MYR
 * for the KL entities, and so on.
 */
class AddCompanyToExchangeRates extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('exchange_rates', [
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'id'],
        ]);
        // table is empty after the live-data cutover; stamp any stray rows to company 1
        $this->db->query('UPDATE exchange_rates SET company_id = 1 WHERE company_id IS NULL');
        $this->db->query('ALTER TABLE exchange_rates MODIFY company_id INT(11) UNSIGNED NOT NULL');
        $this->db->query('ALTER TABLE exchange_rates ADD KEY exchange_rates_company_ccy (company_id, currency_id, rate_date)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE exchange_rates DROP KEY exchange_rates_company_ccy');
        $this->forge->dropColumn('exchange_rates', 'company_id');
    }
}
