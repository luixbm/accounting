<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\AuthGroups;

/**
 * Copy the role / permission definitions out of Config\AuthGroups into the
 * settings table so they become editable at runtime. Shield reads
 * setting('AuthGroups.groups' | '.permissions' | '.matrix') which the Settings
 * package resolves from the database first, then the config class.
 */
class SeedRbacSettings extends Migration
{
    public function up(): void
    {
        helper('setting');
        $cfg = new AuthGroups();

        $has = $this->db->table('settings')
            ->where('class', 'Config\\AuthGroups')
            ->where('key', 'matrix')
            ->countAllResults();

        if ($has === 0) {
            setting()->set('AuthGroups.groups', $cfg->groups);
            setting()->set('AuthGroups.permissions', $cfg->permissions);
            setting()->set('AuthGroups.matrix', $cfg->matrix);
        }
    }

    public function down(): void
    {
        $this->db->table('settings')->where('class', 'Config\\AuthGroups')
            ->whereIn('key', ['groups', 'permissions', 'matrix'])->delete();
    }
}
