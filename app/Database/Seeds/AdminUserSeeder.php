<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Creates a first administrator so you can log in after install.
 *
 *   email:    admin@example.com
 *   username: admin
 *   password: admin12345
 *
 * CHANGE THIS PASSWORD immediately after the first login
 * (Users menu, or via `php spark shield:user`).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = model(UserModel::class);

        if ($users->findByCredentials(['email' => 'admin@example.com'])) {
            return;
        }

        $user = new User([
            'username' => 'admin',
            'email'    => 'admin@example.com',
            'password' => 'admin12345',
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->activate();
        $user->addGroup('admin');

        echo "  Created admin user  admin@example.com / admin12345\n";
    }
}
