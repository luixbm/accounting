<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFiscalPeriods extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'year'      => ['type' => 'SMALLINT', 'constraint' => 4, 'unsigned' => true],
            'month'     => ['type' => 'TINYINT', 'constraint' => 2, 'unsigned' => true],
            'status'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'], // open | closed
            'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'closed_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['year', 'month']);
        $this->forge->createTable('fiscal_periods');
    }

    public function down(): void
    {
        $this->forge->dropTable('fiscal_periods');
    }
}
