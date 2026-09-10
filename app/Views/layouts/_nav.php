<?php

/**
 * Shared navigation model for every shell (classic sidebar, modern top bar).
 * Returns the grouped link lists plus the "which entry is active" helper so
 * each shell only decides how to *render* the menu, never what's in it.
 *
 * Each link: [seg, url, icon, langkey, visible]
 *   `seg` may be a single segment ('accounts') or a path ('purchases/review')
 *   for a sub-section that must win over its parent's link.
 *
 * @return array{
 *   dashboard: bool, reports: bool,
 *   master: list<array>, txn: list<array>, setup: list<array>,
 *   companies: list<array>, activeSeg: ?string, navFor: callable
 * }
 */

$masterData = [
    ['accounts', 'accounts', 'accounts', 'chart_of_accounts', true],
    ['suppliers', 'suppliers', 'supplier', 'suppliers', true],
    ['customers', 'customers', 'customer', 'customers', true],
    ['currencies', 'currencies', 'currency', 'currencies', true],
];
$transactions = [
    ['purchases', 'purchases', 'purchase', 'purchases', module_enabled('PurchaseController')],
    ['purchases/review', 'purchases/review', 'review', 'invoice_review', module_enabled('InvoiceReviewController')],
    ['sales', 'sales', 'sales', 'sales', module_enabled('SalesController')],
    ['banking', 'banking', 'currency', 'banking', module_enabled('BankingController')],
    ['journals', 'journals', 'journal', 'journals', true],
    ['journals/recurring', 'journals/recurring', 'repeat', 'recurring_journals', module_enabled('RecurringJournalController')],
    ['jobs', 'jobs', 'job', 'jobs', module_enabled('JobController')],
];
$setup = [
    ['periods', 'periods', 'period', 'periods', true],
    ['announcements', 'announcements', 'megaphone', 'announcements', user_can('settings.manage')],
    ['companies', 'companies', 'accounts', 'companies', user_can('settings.manage')],
    ['settings', 'settings', 'settings', 'settings', user_can('settings.manage')],
    ['control-accounts', 'control-accounts', 'accounts', 'control_accounts', user_can('settings.manage')],
    ['budgets', 'budgets', 'budget', 'budgets', user_can('settings.manage')],
    ['custom-fields', 'custom-fields', 'journal', 'custom_fields', user_can('settings.manage')],
    ['users', 'users', 'users', 'users', user_can('users.manage')],
    ['roles', 'roles', 'users', 'roles', user_can('roles.manage')],
    ['api-tokens', 'api-tokens', 'currency', 'api_tokens', user_can('settings.manage')],
    ['settings/einvoice', 'settings/einvoice', 'einvoice', 'einvoice', user_can('settings.manage')],
    ['settings/login-page', 'settings/login-page', 'palette', 'login_page', user_can('settings.manage')],
];

// Pick the single best-matching nav entry for the current URL: the LONGEST
// registered path that is (or prefixes) the current path, so a sub-section
// like purchases/review wins over its parent purchases.
$uriPath = trim(service('uri')->getPath(), '/');
$allSegs = array_merge(
    array_column($masterData, 0),
    array_column($transactions, 0),
    array_column($setup, 0),
    ['dashboard', 'reports']
);
$activeSeg = null;
foreach ($allSegs as $candidate) {
    if (($uriPath === $candidate || str_starts_with($uriPath, $candidate . '/')) && strlen($candidate) > strlen((string) $activeSeg)) {
        $activeSeg = $candidate;
    }
}

$companies = model(\App\Models\CompanyModel::class)->where('is_active', 1)->orderBy('code')->findAll();
if (($allowedCo = allowed_company_ids()) !== null) {
    $companies = array_values(array_filter($companies, static fn ($c) => in_array((int) $c['id'], $allowedCo, true)));
}

return [
    'dashboard' => user_can('dashboard.view'),
    'reports'   => user_can('reports.view'),
    'master'    => $masterData,
    'txn'       => $transactions,
    'setup'     => $setup,
    'companies' => $companies,
    'activeSeg' => $activeSeg,
    'navFor'    => static fn (string ...$s): string => in_array($activeSeg, $s, true) ? 'active' : '',
];
