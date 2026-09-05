<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Budget module: per-company named budget versions (e.g. "Realistic 2026",
 * "Optimistic 2026"), each holding a per-account monthly figure. Feeds the
 * "P&L vs Budget" report and the multi-period statements' budget columns.
 */
class CreateBudgets extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'year'       => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'note'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'year', 'name']);
        $this->forge->createTable('budget_versions');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'version_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'account_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'period_month' => ['type' => 'TINYINT', 'constraint' => 2, 'unsigned' => true],
            'amount'       => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['version_id', 'account_id', 'period_month']);
        $this->forge->addKey('version_id');
        $this->forge->addForeignKey('version_id', 'budget_versions', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', '', 'CASCADE');
        $this->forge->createTable('budget_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('budget_lines');
        $this->forge->dropTable('budget_versions');
    }
}
