<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Extra line columns on sales invoices to carry the Jambix sales-report data
 * (Ttl Pax, Duration, Remarks) alongside the fields Phase A already added
 * (booking_ref, service_date, party_name, units, nights). All nullable and
 * additive - the ledger only uses amount / amount_base.
 */
class SalesLineJambixFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sales_invoice_lines', [
            'pax'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'nights'],
            'duration' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'pax'],
            'remark'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'duration'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sales_invoice_lines', ['pax', 'duration', 'remark']);
    }
}
