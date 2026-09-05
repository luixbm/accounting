<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reporting dimensions on customers: client group (e.g. "RTN", "Scandinavia",
 * "Other TO") and country. Used to group the Sales Overview report and filter
 * Sales Monthly. First-class columns rather than custom fields because reports
 * GROUP BY them.
 */
class AddCustomerReportDimensions extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('customers', [
            'client_group' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'npwp'],
            'country'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'client_group'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', ['client_group', 'country']);
    }
}
