<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LHDN MyInvois submission state per sales invoice. `einvoice_status` is null
 * until the invoice is first submitted (not submitted); after that it tracks
 * submitted|valid|invalid|cancelled. `einvoice_error` holds LHDN's validation
 * error detail (JSON) when a submission comes back invalid.
 */
class AddEinvoiceFieldsToSalesInvoices extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sales_invoices', [
            'einvoice_status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'import_batch_id'],
            'einvoice_uuid'           => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'einvoice_status'],
            'einvoice_long_id'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'einvoice_uuid'],
            'einvoice_submission_uid' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'einvoice_long_id'],
            'einvoice_submitted_at'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'einvoice_submission_uid'],
            'einvoice_validated_at'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'einvoice_submitted_at'],
            'einvoice_error'          => ['type' => 'TEXT', 'null' => true, 'after' => 'einvoice_validated_at'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sales_invoices', [
            'einvoice_status', 'einvoice_uuid', 'einvoice_long_id', 'einvoice_submission_uid',
            'einvoice_submitted_at', 'einvoice_validated_at', 'einvoice_error',
        ]);
    }
}
