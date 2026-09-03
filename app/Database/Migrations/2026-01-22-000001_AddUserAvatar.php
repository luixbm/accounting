<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A profile photo for each user. Nullable; stores a web-root-relative path
 * (e.g. "assets/avatars/user-3-1737500000.jpg"). The file itself lives under
 * public/assets/avatars/ - see App\Libraries\AvatarStore.
 *
 * Shield owns the `users` table; this column is written directly (it is not in
 * Shield's UserModel::$allowedFields) and read back via the user_avatar_url()
 * helper, so no Shield entity/model override is needed.
 */
class AddUserAvatar extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'avatar_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'status_message'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'avatar_path');
    }
}
