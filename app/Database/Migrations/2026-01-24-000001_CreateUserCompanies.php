<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-user branch (company) access.
 *
 * A user with NO rows here can switch to ANY active company (the pre-existing
 * behaviour, so nobody is locked out on upgrade). A user with one or more rows
 * is restricted to exactly those companies.
 */
class CreateUserCompanies extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addKey(['user_id', 'company_id'], true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('user_companies');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_companies');
    }
}
