<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Shield auth routes (login, logout, register, ...)
service('auth')->routes($routes);

$routes->get('/', static function () {
    if (! auth()->loggedIn()) {
        return redirect()->to('/login');
    }

    return redirect()->to(auth()->user()->can('reports.view') ? '/dashboard' : '/journals');
});

// Switch UI language (persisted in a cookie for one year).
$routes->get('lang/(:segment)', static function (string $locale) {
    $locale = in_array($locale, ['id', 'en'], true) ? $locale : 'id';
    $back   = service('request')->getServer('HTTP_REFERER') ?: '/';

    return redirect()->to($back)->setCookie('locale', $locale, 60 * 60 * 24 * 365);
});

// --- REST API (token auth, company bound to the token) -------------------
$routes->group('api/v1', ['filter' => 'apiauth', 'namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->get('ping', 'MetaController::ping');
    $routes->get('accounts', 'MetaController::accounts');
    $routes->get('customers', 'MetaController::parties/sales');
    $routes->get('suppliers', 'MetaController::parties/purchase');
    $routes->get('jobs', 'MetaController::jobs');

    foreach (['sales', 'purchase'] as $apiKind) {
        $routes->post("{$apiKind}/invoices", "InvoiceController::create/{$apiKind}");
        $routes->get("{$apiKind}/invoices/(:segment)", "InvoiceController::show/{$apiKind}/$1");
    }

    // Budget -> actual line costs (n8n supplier-invoice pipeline)
    $routes->get('purchase/lines/pending', 'PurchaseLineController::pending');
    $routes->post('purchase/lines/costs', 'PurchaseLineController::costs');
    $routes->post('purchase/lines/review', 'PurchaseLineController::review');

    // Jambix job / dossier upsert (reference figures only - no ledger effect)
    $routes->post('jobs', 'JobController::create');
    $routes->get('jobs/(:segment)', 'JobController::show/$1');
});

$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {

    $routes->get('dashboard', 'Dashboard::index', ['filter' => 'permission:reports.view']);

    // Self-service profile (photo) - any logged-in user
    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile', 'ProfileController::update');

    // --- Journal import wizard ----------------------------------------------
    $routes->group('journals/import', static function (RouteCollection $routes): void {
        $routes->get('/', 'ImportController::index');
        $routes->post('/', 'ImportController::upload');
        $routes->get('(:num)', 'ImportController::show/$1');
        $routes->get('(:num)/map', 'ImportController::map/$1');
        $routes->post('(:num)/map', 'ImportController::saveMap/$1');
        $routes->get('(:num)/accounts', 'ImportController::accounts/$1');
        $routes->post('(:num)/accounts', 'ImportController::saveAccounts/$1');
        $routes->get('(:num)/preview', 'ImportController::preview/$1');
        $routes->post('(:num)/commit', 'ImportController::commit/$1');
        $routes->post('(:num)/post-all', 'ImportController::postAll/$1');
        $routes->post('(:num)/revert', 'ImportController::revert/$1');
    });

    // --- Purchase / Sales invoice import wizard --------------------------
    foreach (['purchase' => 'purchases', 'sales' => 'sales'] as $impKind => $impSeg) {
        $routes->group($impSeg . '/import', static function (RouteCollection $routes) use ($impKind): void {
            $routes->get('/', "InvoiceImportController::index/{$impKind}");
            $routes->post('/', "InvoiceImportController::upload/{$impKind}");
            $routes->get('(:num)', "InvoiceImportController::show/{$impKind}/$1");
            $routes->get('(:num)/map', "InvoiceImportController::map/{$impKind}/$1");
            $routes->post('(:num)/map', "InvoiceImportController::saveMap/{$impKind}/$1");
            $routes->get('(:num)/accounts', "InvoiceImportController::accounts/{$impKind}/$1");
            $routes->post('(:num)/accounts', "InvoiceImportController::saveAccounts/{$impKind}/$1");
            $routes->get('(:num)/preview', "InvoiceImportController::preview/{$impKind}/$1");
            $routes->post('(:num)/commit', "InvoiceImportController::commit/{$impKind}/$1");
            $routes->post('(:num)/post-all', "InvoiceImportController::postAll/{$impKind}/$1");
            $routes->post('(:num)/revert', "InvoiceImportController::revert/{$impKind}/$1");
        });
    }

    // --- Purchases -------------------------------------------------------
    $routes->group('purchases', static function (RouteCollection $routes): void {
        $routes->get('payments', 'PurchasePaymentController::index');
        $routes->get('payments/new', 'PurchasePaymentController::new');
        $routes->post('payments', 'PurchasePaymentController::create');
        $routes->get('payments/deposit', 'PurchasePaymentController::depositNew');
        $routes->post('payments/deposit', 'PurchasePaymentController::depositCreate');

        // bulk payment import wizard
        $routes->get('payments/import', 'PaymentImportController::index');
        $routes->get('payments/import/template', 'PaymentImportController::template');
        $routes->post('payments/import', 'PaymentImportController::upload');
        $routes->get('payments/import/(:num)/map', 'PaymentImportController::map/$1');
        $routes->post('payments/import/(:num)/map', 'PaymentImportController::saveMap/$1');
        $routes->get('payments/import/(:num)/preview', 'PaymentImportController::preview/$1');
        $routes->post('payments/import/(:num)/commit', 'PaymentImportController::commit/$1');
        $routes->post('payments/import/(:num)/revert', 'PaymentImportController::revert/$1');
        $routes->get('payments/(:num)', 'PurchasePaymentController::show/$1');
        $routes->get('payments/(:num)/apply', 'PurchasePaymentController::applyForm/$1');
        $routes->post('payments/(:num)/apply', 'PurchasePaymentController::apply/$1');
        $routes->post('payments/(:num)/unapply', 'PurchasePaymentController::unapply/$1');
        $routes->post('payments/(:num)/void', 'PurchasePaymentController::void/$1');

        $routes->get('/', 'PurchaseController::index');
        $routes->get('new', 'PurchaseController::new');
        $routes->post('/', 'PurchaseController::create');
        $routes->get('(:num)', 'PurchaseController::show/$1');
        $routes->get('(:num)/edit', 'PurchaseController::edit/$1');
        $routes->post('(:num)', 'PurchaseController::update/$1');
        $routes->post('(:num)/post', 'PurchaseController::post/$1');
        $routes->post('(:num)/void', 'PurchaseController::void/$1');
        $routes->post('(:num)/delete', 'PurchaseController::delete/$1');
    });

    // --- Jambix booking import wizard -------------------------------------
    $routes->group('purchases/jambix', static function (RouteCollection $routes): void {
        $routes->get('/', 'JambixImportController::index');
        $routes->post('/', 'JambixImportController::upload');
        $routes->get('(:num)/map', 'JambixImportController::map/$1');
        $routes->post('(:num)/map', 'JambixImportController::saveMap/$1');
        $routes->get('(:num)/preview', 'JambixImportController::preview/$1');
        $routes->post('(:num)/commit', 'JambixImportController::commit/$1');
        $routes->post('(:num)/revert', 'JambixImportController::revert/$1');
    });

    // --- Invoice review queue (n8n supplier-invoice pipeline, human confirms) ----
    $routes->group('purchases/review', static function (RouteCollection $routes): void {
        $routes->get('/', 'InvoiceReviewController::index');
        $routes->post('(:num)/confirm', 'InvoiceReviewController::confirmItem/$1');
        $routes->post('(:num)/recheck', 'InvoiceReviewController::recheck/$1');
        $routes->post('(:num)/promise-date', 'InvoiceReviewController::promiseDate/$1');
        $routes->post('batch/(:num)/confirm', 'InvoiceReviewController::confirmBatch/$1');
        $routes->post('batch/(:num)/recheck', 'InvoiceReviewController::recheckBatch/$1');
        $routes->post('batch/(:num)/delete', 'InvoiceReviewController::deleteBatch/$1');
    });

    // --- Banking ------------------------------------------------------------
    $routes->group('banking', static function (RouteCollection $routes): void {
        $routes->get('/', 'BankingController::index');
        $routes->get('transfer', 'BankingController::transferForm');
        $routes->post('transfer', 'BankingController::transfer');
        $routes->get('spend', 'BankingController::spendForm');
        $routes->post('spend', 'BankingController::money/spend');
        $routes->get('receive', 'BankingController::receiveForm');
        $routes->post('receive', 'BankingController::money/receive');

        // Bank statement import + reconciliation
        $routes->get('reconcile', 'BankReconController::index');
        $routes->get('reconcile/new', 'BankReconController::newForm');
        $routes->post('reconcile', 'BankReconController::create');
        $routes->get('reconcile/(:num)', 'BankReconController::review/$1');
        $routes->get('reconcile/(:num)/map', 'BankReconController::map/$1');
        $routes->post('reconcile/(:num)/map', 'BankReconController::saveMap/$1');
        $routes->post('reconcile/(:num)/rematch', 'BankReconController::rematch/$1');
        $routes->post('reconcile/(:num)/match', 'BankReconController::matchLine/$1');
        $routes->post('reconcile/(:num)/unmatch', 'BankReconController::unmatchLine/$1');
        $routes->post('reconcile/(:num)/add-entry', 'BankReconController::addEntry/$1');
        $routes->post('reconcile/(:num)/finish', 'BankReconController::finish/$1');
        $routes->post('reconcile/(:num)/reopen', 'BankReconController::reopen/$1');
        $routes->post('reconcile/(:num)/delete', 'BankReconController::destroy/$1');
        $routes->get('reconcile/(:num)/report', 'BankReconController::report/$1');
    });

    // --- Sales ----------------------------------------------------------
    $routes->group('sales', static function (RouteCollection $routes): void {
        $routes->get('receipts', 'SalesReceiptController::index');
        $routes->get('receipts/new', 'SalesReceiptController::new');
        $routes->post('receipts', 'SalesReceiptController::create');
        $routes->get('receipts/deposit', 'SalesReceiptController::depositNew');
        $routes->post('receipts/deposit', 'SalesReceiptController::depositCreate');

        // bulk receipt / deposit-application import wizard
        $routes->get('receipts/import', 'ReceiptImportController::index');
        $routes->get('receipts/import/template', 'ReceiptImportController::template');
        $routes->post('receipts/import', 'ReceiptImportController::upload');
        $routes->get('receipts/import/(:num)/map', 'ReceiptImportController::map/$1');
        $routes->post('receipts/import/(:num)/map', 'ReceiptImportController::saveMap/$1');
        $routes->get('receipts/import/(:num)/preview', 'ReceiptImportController::preview/$1');
        $routes->post('receipts/import/(:num)/commit', 'ReceiptImportController::commit/$1');
        $routes->post('receipts/import/(:num)/revert', 'ReceiptImportController::revert/$1');

        $routes->get('receipts/(:num)', 'SalesReceiptController::show/$1');
        $routes->get('receipts/(:num)/apply', 'SalesReceiptController::applyForm/$1');
        $routes->post('receipts/(:num)/apply', 'SalesReceiptController::apply/$1');
        $routes->post('receipts/(:num)/unapply', 'SalesReceiptController::unapply/$1');
        $routes->post('receipts/(:num)/void', 'SalesReceiptController::void/$1');

        $routes->get('/', 'SalesController::index');
        $routes->get('new', 'SalesController::new');
        $routes->post('/', 'SalesController::create');
        $routes->get('(:num)', 'SalesController::show/$1');
        $routes->get('(:num)/edit', 'SalesController::edit/$1');
        $routes->post('(:num)', 'SalesController::update/$1');
        $routes->post('(:num)/post', 'SalesController::post/$1');
        $routes->post('(:num)/void', 'SalesController::void/$1');
        $routes->post('(:num)/delete', 'SalesController::delete/$1');
        $routes->post('(:num)/einvoice/submit', 'EinvoiceController::submit/$1');
        $routes->post('(:num)/einvoice/status', 'EinvoiceController::checkStatus/$1');
    });

    // --- Journals -------------------------------------------------------
    $routes->group('journals', static function (RouteCollection $routes): void {
        $routes->get('/', 'JournalController::index');
        $routes->get('new', 'JournalController::new');
        $routes->post('/', 'JournalController::create');
        $routes->get('(:num)', 'JournalController::show/$1');
        $routes->get('(:num)/edit', 'JournalController::edit/$1');
        $routes->post('(:num)', 'JournalController::update/$1');
        $routes->post('(:num)/post', 'JournalController::post/$1');
        $routes->post('(:num)/void', 'JournalController::void/$1');
        $routes->post('(:num)/delete', 'JournalController::delete/$1');
    });

    // --- Master data --------------------------------------------------------
    $routes->group('accounts/import', static function (RouteCollection $routes): void {
        $routes->get('/', 'CoaImportController::index');
        $routes->post('/', 'CoaImportController::upload');
        $routes->get('(:num)/map', 'CoaImportController::map/$1');
        $routes->post('(:num)/map', 'CoaImportController::saveMap/$1');
        $routes->get('(:num)/preview', 'CoaImportController::preview/$1');
        $routes->post('(:num)/commit', 'CoaImportController::commit/$1');
    });

    $routes->group('accounts', static function (RouteCollection $routes): void {
        $routes->get('/', 'AccountController::index');
        $routes->get('new', 'AccountController::new');
        $routes->post('/', 'AccountController::create');
        $routes->get('(:num)/edit', 'AccountController::edit/$1');
        $routes->post('(:num)', 'AccountController::update/$1');
        $routes->post('(:num)/toggle', 'AccountController::toggle/$1');
        $routes->post('(:num)/delete', 'AccountController::delete/$1');
        $routes->post('copy-from', 'AccountController::copyFrom');
    });

    $routes->group('customers', static function (RouteCollection $routes): void {
        $routes->get('/', 'CustomerController::index');
        $routes->get('new', 'CustomerController::new');
        $routes->post('/', 'CustomerController::create');
        $routes->get('(:num)', 'CustomerController::show/$1');
        $routes->get('(:num)/edit', 'CustomerController::edit/$1');
        $routes->post('(:num)', 'CustomerController::update/$1');
    });

    $routes->group('suppliers', static function (RouteCollection $routes): void {
        $routes->get('/', 'SupplierController::index');
        $routes->get('new', 'SupplierController::new');
        $routes->post('/', 'SupplierController::create');
        $routes->get('(:num)', 'SupplierController::show/$1');
        $routes->get('(:num)/edit', 'SupplierController::edit/$1');
        $routes->post('(:num)', 'SupplierController::update/$1');
    });

    // --- Companies (multi-company) ----------------------------------------
    $routes->post('companies/switch', 'CompanyController::switch');
    $routes->group('companies', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'CompanyController::index');
        $routes->get('new', 'CompanyController::new');
        $routes->post('/', 'CompanyController::create');
        $routes->get('(:num)/edit', 'CompanyController::edit/$1');
        $routes->post('(:num)', 'CompanyController::update/$1');
    });

    // --- Announcements (notice board) ------------------------------------
    $routes->group('announcements', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'AnnouncementController::index');
        $routes->get('new', 'AnnouncementController::new');
        $routes->post('/', 'AnnouncementController::create');
        $routes->get('(:num)/edit', 'AnnouncementController::edit/$1');
        $routes->post('(:num)', 'AnnouncementController::update/$1');
        $routes->post('(:num)/toggle', 'AnnouncementController::toggle/$1');
        $routes->post('(:num)/delete', 'AnnouncementController::delete/$1');
    });

    // --- Budgets -------------------------------------------------------------
    $routes->group('budgets', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'BudgetVersionController::index');
        $routes->get('new', 'BudgetVersionController::new');
        $routes->post('/', 'BudgetVersionController::create');

        $routes->group('import', static function (RouteCollection $routes): void {
            $routes->get('/', 'BudgetImportController::index');
            $routes->post('/', 'BudgetImportController::upload');
            $routes->get('(:num)/map', 'BudgetImportController::map/$1');
            $routes->post('(:num)/map', 'BudgetImportController::saveMap/$1');
            $routes->get('(:num)/preview', 'BudgetImportController::preview/$1');
            $routes->post('(:num)/commit', 'BudgetImportController::commit/$1');
        });

        $routes->get('(:num)/edit', 'BudgetVersionController::edit/$1');
        $routes->post('(:num)', 'BudgetVersionController::update/$1');
        $routes->post('(:num)/set-default', 'BudgetVersionController::setDefault/$1');
        $routes->post('(:num)/delete', 'BudgetVersionController::delete/$1');
        $routes->get('(:num)/row/(:num)', 'BudgetVersionController::editRow/$1/$2');
        $routes->post('(:num)/row/(:num)', 'BudgetVersionController::saveRow/$1/$2');
        $routes->get('(:num)', 'BudgetVersionController::show/$1');
    });

    $routes->group('jobs', static function (RouteCollection $routes): void {
        $routes->get('/', 'JobController::index');

        // Jambix job / dossier import wizard
        $routes->group('import', static function (RouteCollection $routes): void {
            $routes->get('/', 'JobImportController::index');
            $routes->post('/', 'JobImportController::upload');
            $routes->get('(:num)/map', 'JobImportController::map/$1');
            $routes->post('(:num)/map', 'JobImportController::saveMap/$1');
            $routes->get('(:num)/preview', 'JobImportController::preview/$1');
            $routes->post('(:num)/commit', 'JobImportController::commit/$1');
            $routes->post('(:num)/revert', 'JobImportController::revert/$1');
        });

        $routes->get('new', 'JobController::new');
        $routes->post('/', 'JobController::create');
        $routes->get('(:num)', 'JobController::show/$1');
        $routes->get('(:num)/edit', 'JobController::edit/$1');
        $routes->post('(:num)', 'JobController::update/$1');
        $routes->post('(:num)/toggle', 'JobController::toggle/$1');
    });

    $routes->group('currencies', static function (RouteCollection $routes): void {
        $routes->get('/', 'CurrencyController::index');
        $routes->post('/', 'CurrencyController::create');
        $routes->post('(:num)', 'CurrencyController::update/$1');
        $routes->post('(:num)/rate', 'CurrencyController::addRate/$1');
    });

    $routes->group('periods', static function (RouteCollection $routes): void {
        $routes->get('/', 'PeriodController::index');
        $routes->get('(:num)', 'PeriodController::index/$1');
        $routes->post('close', 'PeriodController::close');
        $routes->post('reopen', 'PeriodController::reopen');
    });

    // --- Reports -----------------------------------------------------------
    $routes->group('reports', ['filter' => ['permission:reports.view', 'reportgate']], static function (RouteCollection $routes): void {
        $routes->get('/', 'ReportController::index');
        $routes->get('consolidation', 'ReportController::consolidation');
        $routes->get('cash-flow', 'ReportController::cashFlow');
        $routes->get('bank-book', 'ReportController::bankBook');
        $routes->get('trial-balance', 'ReportController::trialBalance');
        $routes->get('general-ledger', 'ReportController::generalLedger');
        $routes->get('balance-sheet', 'ReportController::balanceSheet');
        $routes->get('income-statement', 'ReportController::incomeStatement');
        $routes->get('pnl-budget', 'BudgetReportController::pnlBudget');
        $routes->get('ar-aging', 'ReportController::arAging');
        $routes->get('ap-aging', 'ReportController::apAging');

        // Sales / Purchase list reports (one controller, kind in the URL)
        foreach (['sales', 'purchase'] as $tKind) {
            $routes->get("{$tKind}-register", "TradeReportController::register/{$tKind}");
            $routes->get("{$tKind}-monthly", "TradeReportController::monthly/{$tKind}");
            $routes->get("{$tKind}-outstanding", "TradeReportController::outstanding/{$tKind}");
            $routes->get("{$tKind}-detail", "TradeReportController::detail/{$tKind}");
            $routes->get("{$tKind}-invoice-paid", "TradeReportController::invoicePaid/{$tKind}");
            $routes->get("{$tKind}-aging-detail", "TradeReportController::agingDetail/{$tKind}");
        }
        $routes->get('sales-receipts', 'TradeReportController::payments/sales');
        $routes->get('purchase-payments', 'TradeReportController::payments/purchase');
        $routes->get('payment-list', 'TradeReportController::paymentList');
        $routes->get('customer-list', 'TradeReportController::parties/sales');
        $routes->get('supplier-list', 'TradeReportController::parties/purchase');

        // Cross-cutting ledger / journal / job analytics (R4)
        $routes->get('journal-list', 'LedgerReportController::journalList');
        $routes->get('realized-fx', 'LedgerReportController::realizedFx');
        $routes->get('gl-details', 'LedgerReportController::glMulti');
        $routes->get('payment-bank', 'LedgerReportController::paymentByBank');
        $routes->get('job-list', 'LedgerReportController::jobList');
        $routes->get('job-pnl', 'LedgerReportController::jobPnlMulti');
    });

    // --- Settings & Users (admin) ---------------------------------------
    $routes->group('settings', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'SettingsController::index');
        $routes->post('/', 'SettingsController::save');
    });

    $routes->group('control-accounts', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'ControlAccountController::index');
        $routes->post('/', 'ControlAccountController::save');
    });

    $routes->group('api-tokens', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'ApiTokenController::index');
        $routes->post('/', 'ApiTokenController::create');
        $routes->post('(:num)/revoke', 'ApiTokenController::revoke/$1');
    });

    $routes->group('settings/einvoice', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'EinvoiceController::settings');
        $routes->post('/', 'EinvoiceController::saveSettings');
    });

    $routes->group('users', ['filter' => 'permission:users.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'UserController::index');
        $routes->get('new', 'UserController::new');
        $routes->post('/', 'UserController::create');
        $routes->get('(:num)/edit', 'UserController::edit/$1');
        $routes->post('(:num)', 'UserController::update/$1');
    });

    $routes->group('custom-fields', ['filter' => 'permission:settings.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'CustomFieldController::index');
        $routes->get('new', 'CustomFieldController::new');
        $routes->post('/', 'CustomFieldController::create');
        $routes->get('(:num)/edit', 'CustomFieldController::edit/$1');
        $routes->post('(:num)', 'CustomFieldController::update/$1');
        $routes->post('(:num)/toggle', 'CustomFieldController::toggle/$1');
    });

    $routes->group('roles', ['filter' => 'permission:roles.manage'], static function (RouteCollection $routes): void {
        $routes->get('/', 'RoleController::index');
        $routes->get('new', 'RoleController::new');
        $routes->post('/', 'RoleController::create');
        $routes->get('(:segment)/edit', 'RoleController::edit/$1');
        $routes->post('(:segment)/delete', 'RoleController::delete/$1');
        $routes->post('(:segment)', 'RoleController::update/$1');
    });
});
