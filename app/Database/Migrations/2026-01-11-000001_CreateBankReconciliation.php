<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bank statement import + reconciliation.
 *
 * bank_statements       - one imported statement for a cash/bank account,
 *                         with the bank's own opening / closing balance.
 * bank_statement_lines  - individual statement rows; `amount` is signed
 *                         (positive = money into the bank). Each row may be
 *                         linked to one posted journal_lines row.
 */
class CreateBankReconciliation extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'bank_account_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'statement_date'  => ['type' => 'DATE'],
            'opening_balance' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'closing_balance' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'filename'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'stored_path'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sheet'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'options'         => ['type' => 'TEXT', 'null' => true],
            // draft | reconciled
            'status'          => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'draft'],
            'note'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'reconciled_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_by'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'bank_account_id']);
        $this->forge->createTable('bank_statements');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'statement_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'txn_date'        => ['type' => 'DATE'],
            'description'     => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'reference'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'amount'          => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'matched_line_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // auto | manual | created
            'match_type'      => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'sort_no'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('statement_id');
        $this->forge->addKey('matched_line_id');
        $this->forge->createTable('bank_statement_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('bank_statement_lines');
        $this->forge->dropTable('bank_statements');
    }
}
