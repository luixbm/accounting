<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCurrencies extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 3],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 60],
            'symbol'         => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'decimal_places' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 2],
            'is_base'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('currencies');
    }

    public function down(): void
    {
        $this->forge->dropTable('currencies');
    }
}
