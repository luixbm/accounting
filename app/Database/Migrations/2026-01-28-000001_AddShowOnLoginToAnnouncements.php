<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets an announcement (e.g. a seasonal/holiday greeting) also appear on the
 * public login page, in addition to the existing pinned-banner/dashboard
 * placement for signed-in users. Login-page rendering bypasses the normal
 * per-company scoping (no session yet), so this is a separate opt-in flag
 * rather than reusing `pinned`.
 */
class AddShowOnLoginToAnnouncements extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('announcements', [
            'show_on_login' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'pinned'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('announcements', 'show_on_login');
    }
}
