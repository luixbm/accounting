<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * doc_type on purchase_invoices / sales_invoices: 'invoice' (the normal bill)
 * or 'credit_note' (a purchase/sales credit note - a.k.a. debit note - the
 * mirror document that reduces the party's balance). All amount columns stay
 * positive; doc_type carries the sign meaning. The poster swaps debit/credit
 * for a credit note, and payments/receipts net open credit notes.
 */
class AddDocTypeToInvoices extends Migration
{
    private array $tables = ['purchase_invoices', 'sales_invoices'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            $this->forge->addColumn($t, [
                'doc_type' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false, 'default' => 'invoice', 'after' => 'internal_no'],
            ]);
            $this->db->table($t)->where('doc_type', null)->orWhere('doc_type', '')->update(['doc_type' => 'invoice']);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            $this->forge->dropColumn($t, 'doc_type');
        }
    }
}
