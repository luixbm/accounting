<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCashflowToAccounts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('accounts', [
            // operating | investing | financing  — which section of the cash flow
            // statement a movement against this account belongs to
            'cashflow' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'operating', 'after' => 'subledger'],
        ]);

        // sensible starting classification
        $db = $this->db;

        // financing: equity accounts
        $db->query("UPDATE accounts SET cashflow = 'financing' WHERE type = 'equity'");

        // financing: liabilities under a 'long-term' / 'jangka panjang' header
        $db->query("UPDATE accounts a
            LEFT JOIN accounts p ON p.id = a.parent_id
            SET a.cashflow = 'financing'
            WHERE a.type = 'liability'
              AND (p.code = '2200' OR LOWER(p.name) LIKE '%jangka panjang%' OR LOWER(p.name) LIKE '%long term%'
                   OR LOWER(a.name) LIKE '%hutang bank%' OR LOWER(a.name) LIKE '%pemegang saham%' OR LOWER(a.name) LIKE '%deviden%')");

        // investing: fixed assets + accumulated depreciation
        $db->query("UPDATE accounts a
            LEFT JOIN accounts p ON p.id = a.parent_id
            SET a.cashflow = 'investing'
            WHERE (a.type = 'contra_asset' AND (LOWER(a.name) LIKE '%penyusutan%' OR LOWER(a.name) LIKE '%depreciation%'))
               OR (a.type = 'asset' AND (p.code = '1200' OR LOWER(p.name) LIKE '%aktiva tetap%' OR LOWER(p.name) LIKE '%fixed asset%'
                    OR LOWER(a.name) LIKE '%investasi%' OR LOWER(a.name) LIKE '%penyertaan%'))");
    }

    public function down(): void
    {
        $this->forge->dropColumn('accounts', 'cashflow');
    }
}
