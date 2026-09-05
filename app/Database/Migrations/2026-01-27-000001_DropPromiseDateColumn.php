<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Undoes the purchase_invoice_lines.promise_date column added in
 * 2026-01-26-000001. There's already a "Promise Date" custom field on
 * purchase_invoice (entity=purchase_invoice, field_key=promise_date,
 * alongside PO Number) - that's the real store; the Invoice Review page
 * writes there via App\Libraries\CustomFields instead of a dedicated column.
 * cost_review_items.promise_date is untouched - it's just the staging value
 * shown/edited on the review grid before a line is matched to an invoice.
 */
class DropPromiseDateColumn extends Migration
{
    public function up(): void
    {
        $this->forge->dropColumn('purchase_invoice_lines', 'promise_date');
    }

    public function down(): void
    {
        $this->forge->addColumn('purchase_invoice_lines', [
            'promise_date' => ['type' => 'DATE', 'null' => true, 'after' => 'service_date'],
        ]);
    }
}
