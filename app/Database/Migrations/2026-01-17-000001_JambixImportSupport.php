<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Supports the Jambix booking import and the later budget -> actual cost update:
 *
 *  - purchase_invoice_lines.supp_inv_ref / supp_inv_date record which supplier
 *    invoice a line's *actual* cost came from, so the same cost is never applied
 *    twice and there is an audit trail behind the budget -> actual flip.
 *  - an index on service_date, because the n8n cost-update flow falls back to
 *    matching a supplier invoice line by (supplier + service date) when the
 *    booking id is not quoted on the supplier's paperwork.
 *
 * All additive and nullable - the ledger only ever uses amount / amount_base.
 */
class JambixImportSupport extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('purchase_invoice_lines', [
            'supp_inv_ref'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'cost_source'],
            'supp_inv_date' => ['type' => 'DATE', 'null' => true, 'after' => 'supp_inv_ref'],
        ]);

        $this->db->query('ALTER TABLE purchase_invoice_lines ADD KEY pil_service_date (service_date)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE purchase_invoice_lines DROP KEY pil_service_date');
        $this->forge->dropColumn('purchase_invoice_lines', ['supp_inv_ref', 'supp_inv_date']);
    }
}
