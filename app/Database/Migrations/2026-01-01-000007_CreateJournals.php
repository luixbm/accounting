<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJournals extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'journal_no'    => ['type' => 'VARCHAR', 'constraint' => 30],
            'entry_date'    => ['type' => 'DATE'],
            'reference'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'description'   => ['type' => 'VARCHAR', 'constraint' => 255],
            // general | cash_receipt | cash_payment | sales | purchase | memorial | opening | adjustment
            'source'        => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'general'],
            'currency_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'exchange_rate' => ['type' => 'DECIMAL', 'constraint' => '18,8', 'default' => 1],
            // draft | posted | void
            'status'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'draft'],
            'total_debit'   => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // base currency
            'total_credit'  => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // base currency
            'reversal_of'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'posted_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'posted_at'     => ['type' => 'DATETIME', 'null' => true],
            'voided_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'voided_at'     => ['type' => 'DATETIME', 'null' => true],
            'void_reason'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('journal_no');
        $this->forge->addKey('entry_date');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('currency_id', 'currencies', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('journals');
    }

    public function down(): void
    {
        $this->forge->dropTable('journals');
    }
}
