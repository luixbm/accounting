<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateImportBatches extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'filename'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_path'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'sheet'         => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            // uploaded | mapped | committed | reverted
            'status'        => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'uploaded'],
            'options'       => ['type' => 'TEXT', 'null' => true], // JSON: header row, column map, commit mode
            'row_count'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'journal_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'skipped_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'committed_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('import_batches');
    }

    public function down(): void
    {
        $this->forge->dropTable('import_batches');
    }
}
