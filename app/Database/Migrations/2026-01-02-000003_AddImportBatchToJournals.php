<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddImportBatchToJournals extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('journals', [
            'import_batch_id' => [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'reversal_of',
            ],
        ]);
        $this->forge->addKey('import_batch_id');
        $this->db->query('ALTER TABLE `journals` ADD CONSTRAINT `journals_import_batch_id_foreign`
            FOREIGN KEY (`import_batch_id`) REFERENCES `import_batches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `journals` DROP FOREIGN KEY `journals_import_batch_id_foreign`');
        $this->forge->dropColumn('journals', 'import_batch_id');
    }
}
