<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Customer down payments & supplier deposits. A receipt / payment gains a
 * `kind` (settlement | deposit) and, for deposits, a running unapplied balance.
 * Allocation rows gain a `journal_id` so a single "apply deposit" action's
 * lines can be reversed together.
 */
class AddDepositSupport extends Migration
{
    public function up(): void
    {
        foreach (['sales_receipts', 'purchase_payments'] as $t) {
            $this->forge->addColumn($t, [
                'kind'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'settlement', 'after' => 'reference'],
                'unapplied'      => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0, 'after' => 'amount_base'],
                'unapplied_base' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0, 'after' => 'unapplied'],
            ]);
        }
        foreach (['sales_receipt_allocations', 'purchase_payment_allocations'] as $t) {
            $this->forge->addColumn($t, [
                'journal_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'amount_base'],
            ]);
            $this->db->query("ALTER TABLE `{$t}` ADD KEY `{$t}_journal` (`journal_id`)");
        }
    }

    public function down(): void
    {
        foreach (['sales_receipts', 'purchase_payments'] as $t) {
            $this->forge->dropColumn($t, ['kind', 'unapplied', 'unapplied_base']);
        }
        foreach (['sales_receipt_allocations', 'purchase_payment_allocations'] as $t) {
            $this->db->query("ALTER TABLE `{$t}` DROP KEY `{$t}_journal`");
            $this->forge->dropColumn($t, 'journal_id');
        }
    }
}
