<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Promise date (planned payment date, separate from service_date / due_date)
 * on purchase_invoice_lines, plus the review-queue columns needed to show
 * service date, arrival date (via jobs.start_date) and promise date on the
 * Invoice Review page without re-decoding the raw payload JSON each time.
 */
class AddPromiseDateAndReviewDates extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('purchase_invoice_lines', [
            'promise_date' => ['type' => 'DATE', 'null' => true, 'after' => 'service_date'],
        ]);

        $this->forge->addColumn('cost_review_items', [
            'service_date' => ['type' => 'DATE', 'null' => true, 'after' => 'description'],
            'promise_date' => ['type' => 'DATE', 'null' => true, 'after' => 'service_date'],
            'job_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'invoice_internal_no'],
            'line_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'invoice_internal_no'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('cost_review_items', ['service_date', 'promise_date', 'job_id', 'line_id']);
        $this->forge->dropColumn('purchase_invoice_lines', ['promise_date']);
    }
}
