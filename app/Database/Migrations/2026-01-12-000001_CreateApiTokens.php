<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Machine-to-machine API credentials. Each token is bound to one company, so a
 * third-party integration (e.g. Jambix) authenticates and is scoped in a single
 * step - no session, no user. The raw token is shown once; only its SHA-256
 * hash is stored.
 */
class CreateApiTokens extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 80],
            'token_hash'   => ['type' => 'CHAR', 'constraint' => 64],
            'prefix'       => ['type' => 'VARCHAR', 'constraint' => 16],
            // comma-separated: read, sales:write, purchase:write
            'abilities'    => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'read,sales:write,purchase:write'],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'revoked_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('company_id');
        $this->forge->createTable('api_tokens');
    }

    public function down(): void
    {
        $this->forge->dropTable('api_tokens');
    }
}
