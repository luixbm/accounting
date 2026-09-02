<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJournalLines extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'journal_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'line_no'     => ['type' => 'INT', 'constraint' => 4, 'default' => 1],
            'account_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'memo'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'debit'       => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // transaction currency
            'credit'      => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // transaction currency
            'debit_base'  => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // base currency (IDR)
            'credit_base' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // base currency (IDR)
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'supplier_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('journal_id');
        $this->forge->addKey('account_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('supplier_id');
        $this->forge->addForeignKey('journal_id', 'journals', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('journal_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('journal_lines');
    }
}
