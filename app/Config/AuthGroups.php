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
        'accountant' => [
            'title'       => 'Accountant',
            'description' => 'Post and void journals, manage master data, close periods, open every report and the dashboard. No access to settings, users or roles.',
        ],
        'manager' => [
            'title'       => 'Manager',
            'description' => 'Read-only: the dashboard and every report. Cannot create, post or change anything.',
        ],
        'auditor' => [
            'title'       => 'Auditor / Consultant',
            'description' => 'Read-only: every report. No dashboard, and cannot create, post or change anything.',
        ],
        'staff' => [
            'title'       => 'Staff / Bookkeeper',
            'description' => 'Input and post journals. No access to the dashboard or reports; cannot void or close periods.',
        ],
        'dataentry' => [
            'title'       => 'Data Entry',
            'description' => 'Create and edit draft journals only. Cannot post to the ledger; no dashboard or reports.',
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
        'dashboard.view'    => 'View the dashboard',
        'reports.view'      => 'Open the Reports hub',
        'reports.financial' => 'Open Financial reports (P&L, Balance Sheet, Cash Flow)',
        'reports.gl'        => 'Open General Ledger reports',
        'reports.cashbank'  => 'Open Cash & Bank reports',
        'reports.sales'     => 'Open Sales reports',
        'reports.purchase'  => 'Open Purchase reports',
        'reports.job'       => 'Open Job reports',
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
            'masterdata.manage', 'period.close',
            'dashboard.view', 'reports.view',
            'reports.financial', 'reports.gl', 'reports.cashbank',
            'reports.sales', 'reports.purchase', 'reports.job',
            'settings.manage', 'users.manage', 'roles.manage',
        ],
        'accountant' => [
            'journal.create', 'journal.post', 'journal.void', 'journal.delete',
            'masterdata.manage', 'period.close',
            'dashboard.view', 'reports.view',
            'reports.financial', 'reports.gl', 'reports.cashbank',
            'reports.sales', 'reports.purchase', 'reports.job',
        ],
        'manager' => [
            'dashboard.view', 'reports.view',
            'reports.financial', 'reports.gl', 'reports.cashbank',
            'reports.sales', 'reports.purchase', 'reports.job',
        ],
        'auditor' => [
            'reports.view',
            'reports.financial', 'reports.gl', 'reports.cashbank',
            'reports.sales', 'reports.purchase', 'reports.job',
        ],
        'staff' => [
            'journal.create', 'journal.post',
        ],
        'dataentry' => [
            'journal.create', 'journal.delete',
        ],
    ];
}
