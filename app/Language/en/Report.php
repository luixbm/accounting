<?php

/**
 * Report Center — catalogue titles + descriptions and per-report page titles.
 * Keys match Config\Reports::$items; `<key>` = title, `<key>_d` = description.
 */
return [
    'title' => 'Reports',

    // categories
    'cat_financial' => 'Financial',
    'cat_gl'        => 'General Ledger',
    'cat_cashbank'  => 'Cash & Bank',
    'cat_sales'     => 'Sales',
    'cat_purchase'  => 'Purchase',
    'cat_job'       => 'Job',

    // ---- Financial
    'pnl'           => 'Profit & Loss',
    'pnl_d'         => 'Income statement for a period',
    'pnl-monthly'   => 'P&L — multi period',
    'pnl-monthly_d' => 'Monthly profit & loss for a period',
    'pnl-yearly'    => 'P&L — multi year',
    'pnl-yearly_d'  => 'Yearly profit & loss',
    'bs'            => 'Balance Sheet',
    'bs_d'          => 'Standard balance sheet as of a date',
    'bs-monthly'    => 'Balance Sheet — multi period',
    'bs-monthly_d'  => 'Month-end balance sheet columns',
    'cashflow'      => 'Cash Flow',
    'cashflow_d'    => 'Cash inflow & outflow for a period',
    'cashflow-m'    => 'Cash Flow — multi period',
    'cashflow-m_d'  => 'Monthly cash inflow & outflow',

    // ---- General Ledger
    'trial-balance'   => 'Trial Balance',
    'trial-balance_d' => 'Opening, movement and closing per account',
    'journal-list'    => 'Journal List',
    'journal-list_d'  => 'List of general journals for a period',
    'realized-fx'     => 'Realized Gain / Loss',
    'realized-fx_d'   => 'FX rate-difference gains and losses',
    'gl-account'      => 'General Ledger',
    'gl-account_d'    => 'Statement for one account',
    'gl-details'      => 'General Ledger Details',
    'gl-details_d'    => 'Postings for chosen accounts and period',

    // ---- Cash & Bank
    'bank-history'    => 'Bank History',
    'bank-history_d'  => 'All movements on a bank / cash account',
    'payment-bank'    => 'Payment by Bank',
    'payment-bank_d'  => 'Payments & receipts grouped by bank',
    'bank-recon'      => 'Bank Reconciliation',
    'bank-recon_d'    => 'Import a statement & tie out the balance',
    'consolidation'   => 'Consolidated',
    'consolidation_d' => 'Combined statements across companies',

    // ---- Sales
    's-register'       => 'Sales Register',
    's-register_d'     => 'All sales per customer for a period',
    's-monthly'        => 'Sales Monthly',
    's-monthly_d'      => 'Monthly sales per customer',
    's-outstanding'    => 'Outstanding Invoices',
    's-outstanding_d'  => 'Unpaid customer invoices',
    's-aging-sum'      => 'AR Aging (summary)',
    's-aging-sum_d'    => 'Receivables by customer & bucket',
    's-aging'          => 'AR Aging (detail)',
    's-aging_d'        => 'Receivables aged per invoice',
    's-detail'         => 'Sales Invoice Detail',
    's-detail_d'       => 'Line-item detail across invoices',
    's-receipts'       => 'Receipt List',
    's-receipts_d'     => 'Money received from customers',
    's-invoice-paid'   => 'Invoice Paid',
    's-invoice-paid_d' => 'Receipts applied to sales invoices',
    's-customers'      => 'Customer List',
    's-customers_d'    => 'All customers with AR balance',

    // ---- Purchase
    'p-register'       => 'Purchase Register',
    'p-register_d'     => 'All purchases per supplier for a period',
    'p-monthly'        => 'Purchase Monthly',
    'p-monthly_d'      => 'Monthly purchases per supplier',
    'p-outstanding'    => 'Outstanding Invoices',
    'p-outstanding_d'  => 'Unpaid supplier invoices',
    'p-aging-sum'      => 'AP Aging (summary)',
    'p-aging-sum_d'    => 'Payables by supplier & bucket',
    'p-aging'          => 'AP Aging (detail)',
    'p-aging_d'        => 'Payables aged per invoice',
    'p-detail'         => 'Purchase Invoice Detail',
    'p-detail_d'       => 'Line-item detail across invoices',
    'p-payments'       => 'Payments Made',
    'p-payments_d'     => 'Money already paid to suppliers',
    'p-paylist'        => 'Payment List',
    'p-paylist_d'      => 'Unpaid purchase lines to pay, filtered by promise date',
    'p-invoice-paid'   => 'Invoice Paid',
    'p-invoice-paid_d' => 'Payments applied to purchase invoices',
    'p-suppliers'      => 'Supplier List',
    'p-suppliers_d'    => 'All suppliers with AP balance',

    // ---- Job
    'job-list'     => 'Job List',
    'job-list_d'   => 'Revenue, cost & margin per job',
    'job-detail'   => 'Job P&L — Sales vs Purchase',
    'job-detail_d' => 'Sales & purchase per customer / supplier for jobs in an arrival window',

    // page titles that are not 1:1 with a catalogue key
    'consolidated'         => 'Consolidated Report',
    'bs_comparative'       => 'Balance Sheet — comparative',
    'pnl_comparative'      => 'Income Statement — comparative',
    'cashflow_comparative' => 'Cash Flow — comparative',
    'outstanding_p'        => 'Outstanding Purchase Invoices',
    'outstanding_s'        => 'Outstanding Sales Invoices',
];
