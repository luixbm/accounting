<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Multi-currency settlement support: track how much has been received / paid on
 * an invoice in its own transaction currency (not just base), and record each
 * allocation's transaction amount alongside the base amount.
 */
class AddTxnAmountsToSettlements extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sales_invoices', [
            'received' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0, 'after' => 'received_base'],
        ]);
        $this->forge->addColumn('purchase_invoices', [
            'paid' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0, 'after' => 'paid_base'],
        ]);
        $this->forge->addColumn('sales_receipt_allocations', [
            'amount' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0, 'after' => 'invoice_id'],
        ]);
        $this->forge->addColumn('purchase_payment_allocations', [
            'amount' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0, 'after' => 'invoice_id'],
        ]);

        // existing data is base-currency only (rate 1), so txn == base
        $this->db->query('UPDATE sales_invoices SET received = received_base WHERE received = 0');
        $this->db->query('UPDATE purchase_invoices SET paid = paid_base WHERE paid = 0');
        $this->db->query('UPDATE sales_receipt_allocations SET amount = amount_base WHERE amount = 0');
        $this->db->query('UPDATE purchase_payment_allocations SET amount = amount_base WHERE amount = 0');
    }

    public function down(): void
    {
        $this->forge->dropColumn('sales_invoices', 'received');
        $this->forge->dropColumn('purchase_invoices', 'paid');
        $this->forge->dropColumn('sales_receipt_allocations', 'amount');
        $this->forge->dropColumn('purchase_payment_allocations', 'amount');
    }
}
