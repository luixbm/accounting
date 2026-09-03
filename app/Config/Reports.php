<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * The report catalogue that drives the Reports hub page and the sidebar.
 *
 * Each entry: key => [category, title, desc, icon, route, exists(bool)]
 * `exists` = the report is built. Not-yet-built ones show as "coming soon".
 */
class Reports extends BaseConfig
{
    public array $categories = [
        'financial' => ['label' => 'Financial', 'icon' => 'reports'],
        'gl'        => ['label' => 'General Ledger', 'icon' => 'accounts'],
        'cashbank'  => ['label' => 'Cash &amp; Bank', 'icon' => 'currency'],
        'sales'     => ['label' => 'Sales', 'icon' => 'sales'],
        'purchase'  => ['label' => 'Purchase', 'icon' => 'purchase'],
        'job'       => ['label' => 'Job', 'icon' => 'job'],
    ];

    public array $items = [
        // ---- Financial
        'pnl'          => ['financial', 'Profit &amp; Loss', 'Income statement for a period', 'reports', 'reports/income-statement', true],
        'pnl-monthly'  => ['financial', 'P&amp;L — multi period', 'Monthly profit &amp; loss for a period', 'reports', 'reports/income-statement?compare=month', true],
        'pnl-yearly'   => ['financial', 'P&amp;L — multi year', 'Yearly profit &amp; loss', 'reports', 'reports/income-statement?compare=year', true],
        'bs'           => ['financial', 'Balance Sheet', 'Standard balance sheet as of a date', 'reports', 'reports/balance-sheet', true],
        'bs-monthly'   => ['financial', 'Balance Sheet — multi period', 'Month-end balance sheet columns', 'reports', 'reports/balance-sheet?compare=month', true],
        'cashflow'     => ['financial', 'Cash Flow', 'Cash inflow &amp; outflow for a period', 'currency', 'reports/cash-flow', true],
        'cashflow-m'   => ['financial', 'Cash Flow — multi period', 'Monthly cash inflow &amp; outflow', 'currency', 'reports/cash-flow?compare=month', true],

        // ---- General Ledger
        'trial-balance'  => ['gl', 'Trial Balance', 'Opening, movement and closing per account', 'accounts', 'reports/trial-balance', true],
        'journal-list'   => ['gl', 'Journal List', 'List of general journals for a period', 'journal', 'reports/journal-list', true],
        'realized-fx'    => ['gl', 'Realized Gain / Loss', 'FX rate-difference gains and losses', 'currency', 'reports/realized-fx', true],
        'gl-account'     => ['gl', 'General Ledger', 'Statement for one account', 'accounts', 'reports/general-ledger', true],
        'gl-details'     => ['gl', 'General Ledger Details', 'Postings for chosen accounts and period', 'accounts', 'reports/gl-details', true],

        // ---- Cash & Bank
        'bank-history'   => ['cashbank', 'Bank History', 'All movements on a bank / cash account', 'currency', 'reports/bank-book', true],
        'payment-bank'   => ['cashbank', 'Payment by Bank', 'Payments &amp; receipts grouped by bank', 'currency', 'reports/payment-bank', true],
        'bank-recon'     => ['cashbank', 'Bank Reconciliation', 'Import a statement &amp; tie out the balance', 'currency', 'banking/reconcile', true],
        'consolidation'  => ['cashbank', 'Consolidated', 'Combined statements across companies', 'reports', 'reports/consolidation', true],

        // ---- Sales
        's-register'     => ['sales', 'Sales Register', 'All sales per customer for a period', 'sales', 'reports/sales-register', true],
        's-monthly'      => ['sales', 'Sales Monthly', 'Monthly sales per customer', 'sales', 'reports/sales-monthly', true],
        's-outstanding'  => ['sales', 'Outstanding Invoices', 'Unpaid customer invoices', 'sales', 'reports/sales-outstanding', true],
        's-aging-sum'    => ['sales', 'AR Aging (summary)', 'Receivables by customer &amp; bucket', 'sales', 'reports/ar-aging', true],
        's-aging'        => ['sales', 'AR Aging (detail)', 'Receivables aged per invoice', 'sales', 'reports/sales-aging-detail', true],
        's-detail'       => ['sales', 'Sales Invoice Detail', 'Line-item detail across invoices', 'sales', 'reports/sales-detail', true],
        's-receipts'     => ['sales', 'Receipt List', 'Money received from customers', 'sales', 'reports/sales-receipts', true],
        's-invoice-paid' => ['sales', 'Invoice Paid', 'Receipts applied to sales invoices', 'sales', 'reports/sales-invoice-paid', true],
        's-customers'    => ['sales', 'Customer List', 'All customers with AR balance', 'customer', 'reports/customer-list', true],

        // ---- Purchase
        'p-register'     => ['purchase', 'Purchase Register', 'All purchases per supplier for a period', 'purchase', 'reports/purchase-register', true],
        'p-monthly'      => ['purchase', 'Purchase Monthly', 'Monthly purchases per supplier', 'purchase', 'reports/purchase-monthly', true],
        'p-outstanding'  => ['purchase', 'Outstanding Invoices', 'Unpaid supplier invoices', 'purchase', 'reports/purchase-outstanding', true],
        'p-aging-sum'    => ['purchase', 'AP Aging (summary)', 'Payables by supplier &amp; bucket', 'purchase', 'reports/ap-aging', true],
        'p-aging'        => ['purchase', 'AP Aging (detail)', 'Payables aged per invoice', 'purchase', 'reports/purchase-aging-detail', true],
        'p-detail'       => ['purchase', 'Purchase Invoice Detail', 'Line-item detail across invoices', 'purchase', 'reports/purchase-detail', true],
        'p-payments'     => ['purchase', 'Payments Made', 'Money already paid to suppliers', 'purchase', 'reports/purchase-payments', true],
        'p-paylist'      => ['purchase', 'Payment List', 'Unpaid purchase lines to pay, filtered by promise date', 'purchase', 'reports/payment-list', true],
        'p-invoice-paid' => ['purchase', 'Invoice Paid', 'Payments applied to purchase invoices', 'purchase', 'reports/purchase-invoice-paid', true],
        'p-suppliers'    => ['purchase', 'Supplier List', 'All suppliers with AP balance', 'supplier', 'reports/supplier-list', true],

        // ---- Job
        'job-list'       => ['job', 'Job List', 'Revenue, cost &amp; margin per job', 'job', 'reports/job-list', true],
        'job-detail'     => ['job', 'Job P&amp;L — Sales vs Purchase', 'Sales &amp; purchase per customer / supplier for jobs in an arrival window', 'job', 'reports/job-pnl', true],
    ];

    /**
     * Per-report permission overrides: report key => permission string.
     *
     * A report not listed here needs only `reports.view` (the group gate) — so
     * every existing report is unchanged and every NEW report is covered by
     * default. To restrict one, add a line here, e.g.
     *   'consolidation' => 'reports.consolidated',
     * then add that permission string in Setup → Roles.
     *
     * Enforced by App\Filters\ReportGate on the /reports route group and honoured
     * by the Reports hub (a card the user can't open is hidden).
     */
    public array $perms = [
        // key => 'permission.string'
    ];

    /** The permission required to open a report, defaulting to reports.view. */
    public function permFor(string $key): string
    {
        return $this->perms[$key] ?? 'reports.view';
    }

    /** Reverse-lookup: the report key whose route matches a URI path, or null. */
    public function keyForPath(string $path): ?string
    {
        $path = trim($path, '/');
        foreach ($this->items as $key => $row) {
            if (trim(explode('?', (string) $row[4])[0], '/') === $path) {
                return $key;
            }
        }

        return null;
    }
}
