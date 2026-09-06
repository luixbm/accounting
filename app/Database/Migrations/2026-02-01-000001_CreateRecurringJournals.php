<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Recurring journal templates: a stored header + line set for entries that
 * repeat every period (salary, depreciation, rent, standing accruals).
 * "Generate" turns a template into a draft general journal for review; nothing
 * posts automatically.
 */
class CreateRecurringJournals extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 80],
            'description'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'general'],
            'reference'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'currency_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'frequency'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'monthly'],
            'next_date'         => ['type' => 'DATE', 'null' => true],
            'is_active'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_generated_on' => ['type' => 'DATE', 'null' => true],
            'last_journal_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_by'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('currency_id');
        $this->forge->addUniqueKey(['company_id', 'name']);
        $this->forge->createTable('recurring_journals');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'template_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'line_no'     => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
            'account_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'memo'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'debit'       => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'credit'      => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'supplier_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'job_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('template_id');
        $this->forge->addForeignKey('template_id', 'recurring_journals', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', '', 'CASCADE');
        $this->forge->createTable('recurring_journal_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('recurring_journal_lines');
        $this->forge->dropTable('recurring_journals');
    }
}
