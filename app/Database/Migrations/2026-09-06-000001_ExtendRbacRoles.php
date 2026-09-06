<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\AuthGroups;

/**
 * Bring an existing install's runtime RBAC settings up to the expanded catalogue
 * in Config\AuthGroups: the dashboard.view permission, six per-category report
 * permissions (reports.financial / gl / cashbank / sales / purchase / job) and
 * the Accountant / Manager / Auditor / Data Entry starter roles.
 *
 * 2026-01-05 SeedRbacSettings already copied the old groups/permissions/matrix
 * into the settings table and only seeds once, so a fresh migrate seeds the new
 * catalogue straight from the config (and this migration then no-ops on its
 * guard). This migration is what updates a database that was seeded earlier.
 *
 * It is additive: new permission keys, new group definitions and new matrix rows
 * are inserted, and `admin` gains the new permissions — existing rows and any
 * runtime edits to them are left untouched.
 */
class ExtendRbacRoles extends Migration
{
    /** Permissions this migration introduces (reports.view already existed). */
    private array $newPermissions = [
        'dashboard.view',
        'reports.financial', 'reports.gl', 'reports.cashbank',
        'reports.sales', 'reports.purchase', 'reports.job',
    ];

    /** Roles this migration introduces (admin / staff already existed). */
    private array $newRoles = ['accountant', 'manager', 'auditor', 'dataentry'];

    public function up(): void
    {
        helper('setting');

        $perms = setting('AuthGroups.permissions');
        if (is_array($perms) && array_key_exists('dashboard.view', $perms)) {
            return; // already extended (e.g. freshly seeded from the new config)
        }

        $cfg   = new AuthGroups();
        $perms = is_array($perms) ? $perms : [];
        // Labels aren't user-editable (no UI), so the config text is authoritative
        // — refresh every catalogue label, add the new keys. reports.view in
        // particular now means only "open the Reports hub".
        foreach ($cfg->permissions as $key => $label) {
            $perms[$key] = $label;
        }
        setting('AuthGroups.permissions', $perms);

        $groups = setting('AuthGroups.groups');
        $groups = is_array($groups) ? $groups : [];
        foreach ($cfg->groups as $key => $def) {
            if (! array_key_exists($key, $groups)) {
                $groups[$key] = $def;
            }
        }
        setting('AuthGroups.groups', $groups);

        $matrix = setting('AuthGroups.matrix');
        $matrix = is_array($matrix) ? $matrix : [];

        // Preserve effective access across the reports.view / dashboard.view
        // split: any role that existed before this migration and could reach the
        // dashboard (via reports.view) keeps it. New roles are defined exactly.
        foreach ($matrix as $role => $list) {
            if (
                ! in_array($role, $this->newRoles, true)
                && in_array('reports.view', $list, true)
                && ! in_array('dashboard.view', $list, true)
            ) {
                $matrix[$role][] = 'dashboard.view';
            }
        }

        foreach ($this->newRoles as $role) {
            if (! array_key_exists($role, $matrix) && isset($cfg->matrix[$role])) {
                $matrix[$role] = $cfg->matrix[$role];
            }
        }
        $adminAdds       = array_merge(['reports.view'], $this->newPermissions);
        $matrix['admin'] = array_values(array_unique(array_merge($matrix['admin'] ?? [], $adminAdds)));
        setting('AuthGroups.matrix', $matrix);
    }

    public function down(): void
    {
        helper('setting');

        $perms = setting('AuthGroups.permissions') ?? [];
        foreach ($this->newPermissions as $key) {
            unset($perms[$key]);
        }
        setting('AuthGroups.permissions', $perms);

        $groups = setting('AuthGroups.groups') ?? [];
        foreach ($this->newRoles as $role) {
            unset($groups[$role]);
        }
        setting('AuthGroups.groups', $groups);

        $matrix = setting('AuthGroups.matrix') ?? [];
        foreach ($this->newRoles as $role) {
            unset($matrix[$role]);
        }
        foreach ($matrix as $role => $list) {
            $matrix[$role] = array_values(array_diff($list, $this->newPermissions));
        }
        setting('AuthGroups.matrix', $matrix);
    }
}
