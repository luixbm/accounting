<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddJobToJournalLines extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('journal_lines', [
            'job_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'supplier_id'],
        ]);
        $this->forge->addKey('job_id');
        $this->db->query('ALTER TABLE `journal_lines` ADD CONSTRAINT `journal_lines_job_id_foreign`
            FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `journal_lines` DROP FOREIGN KEY `journal_lines_job_id_foreign`');
        $this->forge->dropColumn('journal_lines', 'job_id');
    }
}
