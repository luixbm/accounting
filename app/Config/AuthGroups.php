<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * Group a newly registered user is added to.
     */
    public string $defaultGroup = 'staff';

    /**
     * @var array<string, array<string, string>>
     */
    public array $groups = [
        'admin' => [
            'title'       => 'Administrator',
            'description' => 'Full access: post/void journals, close periods, manage master data, settings and users.',
        ],
        'staff' => [
            'title'       => 'Staff / Bookkeeper',
            'description' => 'Input and post journals. No access to the dashboard or reports; cannot void or close periods.',
        ],
    ];

    /**
     * @var array<string, string>
     */
    public array $permissions = [
        'journal.create'    => 'Create and edit draft journals',
        'journal.post'      => 'Post draft journals to the ledger',
        'journal.void'      => 'Void posted journals',
        'journal.delete'    => 'Delete draft journals',
        'masterdata.manage' => 'Manage chart of accounts, customers, suppliers, currencies, jobs',
        'period.close'      => 'Open and close accounting periods',
        'reports.view'      => 'View dashboard and financial reports',
        'settings.manage'   => 'Manage companies, settings and control accounts',
        'users.manage'      => 'Manage application users',
        'roles.manage'      => 'Manage roles and permissions',
    ];

    /**
     * @var array<string, list<string>>
     */
    public array $matrix = [
        'admin' => [
            'journal.create', 'journal.post', 'journal.void', 'journal.delete',
            'masterdata.manage', 'period.close', 'reports.view',
            'settings.manage', 'users.manage', 'roles.manage',
        ],
        'staff' => [
            'journal.create', 'journal.post',
        ],
    ];
}
