<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccounts extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 150],
            // asset, contra_asset, liability, equity, revenue, cogs, expense, other_income, other_expense
            'type'           => ['type' => 'VARCHAR', 'constraint' => 20],
            // D = debit normal balance, K = credit normal balance
            'normal_balance' => ['type' => 'CHAR', 'constraint' => 1],
            'parent_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // header/summary account - cannot be posted to
            'is_group'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // cash / bank account - shown on cash flow & bank pickers
            'is_cash'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // none | customer | supplier  -> lines require a subledger party
            'subledger'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'none'],
            // optional denomination currency (foreign-currency bank accounts)
            'currency_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('type');
        $this->forge->addForeignKey('parent_id', 'accounts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('currency_id', 'currencies', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('accounts');
    }

    public function down(): void
    {
        $this->forge->dropTable('accounts');
    }
}
