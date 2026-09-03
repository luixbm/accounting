<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Company-scoped announcements - a short notice board.
 *
 *   pinned  = also shown as a dismissible banner on every page (all rows show
 *             on the dashboard card regardless)
 *   level   = info | warning | success  (banner colour)
 *   starts_on / ends_on = optional visibility window (NULL = open-ended)
 */
class CreateAnnouncements extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 160],
            'body'       => ['type' => 'TEXT', 'null' => true],
            'level'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'info'],
            'pinned'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'starts_on'  => ['type' => 'DATE', 'null' => true],
            'ends_on'    => ['type' => 'DATE', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'is_active']);
        $this->forge->createTable('announcements');
    }

    public function down(): void
    {
        $this->forge->dropTable('announcements');
    }
}
