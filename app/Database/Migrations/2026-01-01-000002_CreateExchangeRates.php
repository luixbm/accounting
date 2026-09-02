<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExchangeRates extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'currency_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'rate_date'   => ['type' => 'DATE'],
            // units of base currency per 1 unit of the foreign currency
            'rate'        => ['type' => 'DECIMAL', 'constraint' => '18,8'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['currency_id', 'rate_date']);
        $this->forge->addForeignKey('currency_id', 'currencies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('exchange_rates');
    }

    public function down(): void
    {
        $this->forge->dropTable('exchange_rates');
    }
}
