<?php

namespace App\Controllers;

/**
 * Runtime editor for Shield roles (a.k.a. groups) and the role→permission
 * matrix. All three live in the settings table under the AuthGroups.* keys,
 * which Shield's can()/inGroup() checks read directly.
 */
class RoleController extends BaseController
{
    private function guard(): bool
    {
        return user_can('roles.manage');
    }

    /** @return array<string,array{title:string,description:string}> */
    private function groups(): array
    {
        return setting('AuthGroups.groups') ?? [];
    }

    /** @return array<string,string> */
    private function permissions(): array
    {
        return setting('AuthGroups.permissions') ?? [];
    }

    /** @return array<string,list<string>> */
    private function matrix(): array
    {
        return setting('AuthGroups.matrix') ?? [];
    }

    private function userCountByGroup(): array
    {
        $rows = db_connect()->table('auth_groups_users')
            ->select('`group`, COUNT(*) AS n')->groupBy('`group`')->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['group']] = (int) $r['n'];
        }

        return $out;
    }

    public function index()
    {
        if (! $this->guard()) {
            return redirect()->to('/')->with('error', 'Not allowed.');
        }

        return view('roles/index', [
            'title'       => 'Roles & Permissions',
            'groups'      => $this->groups(),
            'permissions' => $this->permissions(),
            'matrix'      => $this->matrix(),
            'counts'      => $this->userCountByGroup(),
        ]);
    }

    public function new()
    {
        if (! $this->guard()) {
            return redirect()->to('roles')->with('error', 'Not allowed.');
        }

        return view('roles/form', [
            'title'       => 'New Role',
            'key'         => null,
            'role'        => ['title' => '', 'description' => ''],
            'permissions' => $this->permissions(),
            'granted'     => [],
        ]);
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to('roles')->with('error', 'Not allowed.');
        }
        $key = strtolower(trim((string) $this->request->getPost('key')));
        $key = preg_replace('/[^a-z0-9_-]+/', '-', $key);
        if ($key === '' || isset($this->groups()[$key])) {
            return redirect()->back()->withInput()->with('error', 'Role name is empty or already exists.');
        }

        $groups        = $this->groups();
        $groups[$key]  = [
            'title'       => trim((string) $this->request->getPost('title')) ?: ucfirst($key),
            'description' => trim((string) $this->request->getPost('description')),
        ];
        $matrix        = $this->matrix();
        $matrix[$key]  = $this->cleanPerms((array) $this->request->getPost('perms'));

        setting('AuthGroups.groups', $groups);
        setting('AuthGroups.matrix', $matrix);

        return redirect()->to('roles')->with('message', 'Role "' . $key . '" created.');
    }

    public function edit(string $key)
    {
        if (! $this->guard()) {
            return redirect()->to('roles')->with('error', 'Not allowed.');
        }
        $groups = $this->groups();
        if (! isset($groups[$key])) {
            return redirect()->to('roles')->with('error', 'Role not found.');
        }

        return view('roles/form', [
            'title'       => 'Edit ' . ($groups[$key]['title'] ?? $key),
            'key'         => $key,
            'role'        => $groups[$key],
            'permissions' => $this->permissions(),
            'granted'     => $this->matrix()[$key] ?? [],
        ]);
    }

    public function update(string $key)
    {
        if (! $this->guard()) {
            return redirect()->to('roles')->with('error', 'Not allowed.');
        }
        $groups = $this->groups();
        if (! isset($groups[$key])) {
            return redirect()->to('roles')->with('error', 'Role not found.');
        }

        $groups[$key] = [
            'title'       => trim((string) $this->request->getPost('title')) ?: $key,
            'description' => trim((string) $this->request->getPost('description')),
        ];
        $matrix = $this->matrix();
        $perms  = $this->cleanPerms((array) $this->request->getPost('perms'));

        // never let the last admin-capable role lose users.manage + roles.manage
        if ($key === 'admin' && (! in_array('roles.manage', $perms, true) || ! in_array('users.manage', $perms, true))) {
            $perms = array_values(array_unique(array_merge($perms, ['users.manage', 'roles.manage'])));
        }
        $matrix[$key] = $perms;

        setting('AuthGroups.groups', $groups);
        setting('AuthGroups.matrix', $matrix);

        return redirect()->to('roles')->with('message', 'Role "' . $key . '" saved.');
    }

    public function delete(string $key)
    {
        if (! $this->guard()) {
            return redirect()->to('roles')->with('error', 'Not allowed.');
        }
        if ($key === 'admin') {
            return redirect()->to('roles')->with('error', 'The admin role cannot be deleted.');
        }
        if (($this->userCountByGroup()[$key] ?? 0) > 0) {
            return redirect()->to('roles')->with('error', 'Reassign the users in this role first.');
        }

        $groups = $this->groups();
        $matrix = $this->matrix();
        unset($groups[$key], $matrix[$key]);
        setting('AuthGroups.groups', $groups);
        setting('AuthGroups.matrix', $matrix);

        return redirect()->to('roles')->with('message', 'Role "' . $key . '" deleted.');
    }

    /** @return list<string> */
    private function cleanPerms(array $raw): array
    {
        $valid = array_keys($this->permissions());

        return array_values(array_intersect($valid, array_map('strval', $raw)));
    }
}
