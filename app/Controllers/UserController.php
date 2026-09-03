<?php

namespace App\Controllers;

use App\Libraries\AvatarStore;
use App\Models\CompanyModel;
use App\Models\UserCompanyModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class UserController extends BaseController
{
    /** @return list<string> role keys defined in the (editable) settings */
    private function roleKeys(): array
    {
        return array_keys(setting('AuthGroups.groups') ?? []);
    }

    /** @return array<string,string> key => title */
    private function roleTitles(): array
    {
        $out = [];
        foreach (setting('AuthGroups.groups') ?? [] as $k => $g) {
            $out[$k] = $g['title'] ?? $k;
        }

        return $out;
    }

    public function index()
    {
        $users = model(UserModel::class)->orderBy('id', 'ASC')->findAll();
        $rows  = [];
        foreach ($users as $u) {
            $rows[] = [
                'id'       => $u->id,
                'username' => $u->username,
                'email'    => $u->email,
                'active'   => $u->active,
                'groups'   => implode(', ', $u->getGroups()),
                'last'     => $u->last_active,
                'avatar'   => user_avatar_tag((int) $u->id, 'avatar-sm', (string) ($u->username ?? $u->email)),
            ];
        }

        return view('users/index', ['title' => 'Users', 'rows' => $rows]);
    }

    /** @return list<array<string,mixed>> active companies (for the branch picker) */
    private function companies(): array
    {
        return model(CompanyModel::class)->active();
    }

    public function new()
    {
        return view('users/form', [
            'title'         => 'New User',
            'user'          => null,
            'roles'         => $this->roleTitles(),
            'userRoles'     => [setting('AuthGroups.defaultGroup') ?? 'staff'],
            'companies'     => $this->companies(),
            'userCompanies' => [],
        ]);
    }

    public function create()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[30]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[auth_identities.secret]',
            'password' => 'required|min_length[8]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $roles = $this->pickedRoles();
        if (! $roles) {
            return redirect()->back()->withInput()->with('error', 'Pick at least one role.');
        }

        $users = model(UserModel::class);
        $user  = new User([
            'username' => $this->request->getPost('username'),
            'email'    => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
        ]);
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->activate();
        foreach ($roles as $r) {
            $user->addGroup($r);
        }
        $this->handlePhoto((int) $user->id);
        model(UserCompanyModel::class)->setFor((int) $user->id, (array) $this->request->getPost('companies'));

        return redirect()->to('users')->with('message', 'User created.');
    }

    public function edit(int $id)
    {
        $user = model(UserModel::class)->findById($id);
        if (! $user) {
            return redirect()->to('users')->with('error', 'User not found.');
        }

        return view('users/form', [
            'title'         => 'Edit ' . $user->username,
            'user'          => $user,
            'roles'         => $this->roleTitles(),
            'userRoles'     => $user->getGroups(),
            'companies'     => $this->companies(),
            'userCompanies' => model(UserCompanyModel::class)->idsFor((int) $user->id),
        ]);
    }

    public function update(int $id)
    {
        $users = model(UserModel::class);
        $user  = $users->findById($id);
        if (! $user) {
            return redirect()->to('users')->with('error', 'User not found.');
        }

        $roles = $this->pickedRoles();
        if (! $roles) {
            return redirect()->back()->with('error', 'A user needs at least one role.');
        }
        // don't let the last admin lose the admin role
        if (! in_array('admin', $roles, true) && $this->isLastAdmin((int) $user->id)) {
            return redirect()->back()->with('error', 'This is the only admin — keep the admin role.');
        }

        foreach ($user->getGroups() as $g) {
            $user->removeGroup($g);
        }
        foreach ($roles as $r) {
            $user->addGroup($r);
        }

        $password = trim((string) $this->request->getPost('password'));
        if ($password !== '') {
            if (strlen($password) < 8) {
                return redirect()->back()->with('error', 'Password must be at least 8 characters.');
            }
            $user->password = $password;
        }

        if ($this->request->getPost('active') !== null) {
            $user->activate();
        } else {
            $user->deactivate();
        }
        $users->save($user);
        $this->handlePhoto((int) $user->id);
        model(UserCompanyModel::class)->setFor((int) $user->id, (array) $this->request->getPost('companies'));

        return redirect()->to('users')->with('message', 'User updated.');
    }

    /** Apply a photo upload / removal from the user form. */
    private function handlePhoto(int $userId): void
    {
        if ($this->request->getPost('remove_photo')) {
            AvatarStore::remove($userId);

            return;
        }
        $file = $this->request->getFile('photo');
        if ($file && $file->isValid() && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $res = AvatarStore::save($userId, $file);
            if (! $res['ok']) {
                session()->setFlashdata('error', $res['error']);
            }
        }
    }

    /** @return list<string> */
    private function pickedRoles(): array
    {
        $valid = $this->roleKeys();

        return array_values(array_intersect($valid, (array) $this->request->getPost('roles')));
    }

    private function isLastAdmin(int $userId): bool
    {
        $admins = db_connect()->table('auth_groups_users')->where('group', 'admin')->get()->getResultArray();
        if (count($admins) > 1) {
            return false;
        }

        return count($admins) === 1 && (int) $admins[0]['user_id'] === $userId;
    }
}
