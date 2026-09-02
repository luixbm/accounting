<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInvoiceImportFields extends Migration
{
    public function up(): void
    {
        // distinguish journal vs purchase vs sales import batches
        $this->forge->addColumn('import_batches', [
            'kind' => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'journal', 'after' => 'id'],
        ]);

        foreach (['purchase_invoices', 'sales_invoices'] as $table) {
            $this->forge->addColumn($table, [
                'external_id'     => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'internal_no'],
                'source'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual', 'after' => 'external_id'],
                'import_batch_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'journal_id'],
            ]);
            // NULL external_id allowed many times; a non-null one is unique per company
            $this->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$table}_company_ext` (`company_id`,`external_id`)");
            $this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$table}_batch_idx` (`import_batch_id`)");
            $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_batch_fk`
                FOREIGN KEY (`import_batch_id`) REFERENCES `import_batches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }
    }

    public function down(): void
    {
        foreach (['purchase_invoices', 'sales_invoices'] as $table) {
            try {
                $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_batch_fk`");
            } catch (\Throwable $e) {
            }
            $this->forge->dropColumn($table, ['external_id', 'source', 'import_batch_id']);
        }
        $this->forge->dropColumn('import_batches', 'kind');
    }
}
