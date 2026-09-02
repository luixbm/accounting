<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomFields extends Migration
{
    public function up(): void
    {
        // --- field definitions (per company, per entity)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'entity'      => ['type' => 'VARCHAR', 'constraint' => 40], // purchase_invoice, sales_invoice, customer, ...
            'field_key'   => ['type' => 'VARCHAR', 'constraint' => 40], // slug, unique per company+entity
            'label'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 12], // text|textarea|number|date|select|checkbox
            'options'     => ['type' => 'TEXT', 'null' => true],        // newline-separated for select
            'help'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'is_required' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'show_in_list'=> ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'sort_order'  => ['type' => 'INT', 'constraint' => 4, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'entity', 'field_key']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('custom_fields');

        // --- values (typed columns so a future payment-list can filter/sort by them)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'entity'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'record_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'field_key'  => ['type' => 'VARCHAR', 'constraint' => 40],
            'value_text' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'value_num'  => ['type' => 'DECIMAL', 'constraint' => '20,4', 'null' => true],
            'value_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['entity', 'record_id', 'field_key']);
        $this->forge->addKey(['company_id', 'entity', 'field_key', 'value_date']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('custom_values');
    }

    public function down(): void
    {
        $this->forge->dropTable('custom_values', true);
        $this->forge->dropTable('custom_fields', true);
    }
}
