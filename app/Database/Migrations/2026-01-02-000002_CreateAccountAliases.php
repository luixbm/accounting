<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountAliases extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // normalised (lower-cased, trimmed) spreadsheet account label
            'source_label' => ['type' => 'VARCHAR', 'constraint' => 190],
            'account_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('source_label');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('account_aliases');
    }

    public function down(): void
    {
        $this->forge->dropTable('account_aliases');
    }
}
