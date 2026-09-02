<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Line-level detail carried from an external booking system (Jambix): the
 * booking reference, the service date, the traveller / party name, and the
 * unit / night descriptors. All nullable and additive - the ledger only ever
 * uses amount / amount_base, so posting and reports are unaffected.
 *
 * `cost_source` (purchase only) tracks whether a line's cost is still a budget
 * estimate or has been updated to the actual supplier figure.
 */
class AddLineDetailFields extends Migration
{
    public function up(): void
    {
        $common = [
            'booking_ref'  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'description'],
            'service_date' => ['type' => 'DATE', 'null' => true, 'after' => 'booking_ref'],
            'party_name'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'service_date'],
            'units'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'party_name'],
            'nights'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'units'],
        ];

        $this->forge->addColumn('purchase_invoice_lines', $common + [
            'cost_source' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'manual', 'after' => 'nights'],
        ]);
        $this->forge->addColumn('sales_invoice_lines', $common);

        $this->db->query('ALTER TABLE purchase_invoice_lines ADD KEY pil_booking_ref (booking_ref)');
        $this->db->query('ALTER TABLE sales_invoice_lines ADD KEY sil_booking_ref (booking_ref)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE purchase_invoice_lines DROP KEY pil_booking_ref');
        $this->db->query('ALTER TABLE sales_invoice_lines DROP KEY sil_booking_ref');
        $this->forge->dropColumn('purchase_invoice_lines', ['booking_ref', 'service_date', 'party_name', 'units', 'nights', 'cost_source']);
        $this->forge->dropColumn('sales_invoice_lines', ['booking_ref', 'service_date', 'party_name', 'units', 'nights']);
    }
}
