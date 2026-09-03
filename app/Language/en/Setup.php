<?php

/**
 * Master-data & setup screens: chart of accounts, customers/suppliers,
 * currencies, periods, custom fields, companies, control accounts, API
 * tokens, roles, users, settings. Generic words live in App.php.
 */
return [
    // chart of accounts
    'n_shown'          => '{0} shown',
    'of_total'         => 'of {0}',
    'new_account'      => '+ New Account',
    'start_coa_h'      => "Start this company's chart of accounts",
    'start_coa_note'   => 'Copy an existing chart from another company, then adjust it. Or add accounts one by one.',
    'copy_coa'         => 'Copy chart of accounts',
    'all_types'        => 'All types',
    'all_groups'       => 'All groups',
    'group'            => 'Group',
    'col_parent'       => 'Parent',
    'col_normal'       => 'Normal',
    'col_subledger'    => 'Subledger',
    'col_flags'        => 'Flags',
    'flag_header'      => 'header',
    'flag_cash'        => 'cash',
    'no_accounts_match'=> 'No accounts match these filters.',
    'account_name'     => 'Account Name',
    'normal_balance'   => 'Normal Balance',
    'debit_d'          => 'Debit (D)',
    'credit_k'         => 'Credit (K)',
    'parent_header'    => 'Parent (header account)',
    'subledger'        => 'Subledger',
    'sl_none'          => 'None',
    'sl_customer'      => 'Customer (AR)',
    'sl_supplier'      => 'Supplier (AP)',
    'cashflow_section' => 'Cash-flow section',
    'denomination_ccy' => 'Denomination Currency',
    'base_opt'         => '— base —',
    'flags'            => 'Flags',
    'flag_header_full' => 'Header account (no postings)',
    'flag_cash_full'   => 'Cash / bank account',
    'danger_zone'      => 'Danger zone',
    'deactivate'       => 'Deactivate',
    'reactivate'       => 'Reactivate',
    'delete_account_confirm' => 'Delete account {0}? This only works if it has no journal entries and no sub-accounts.',
    'danger_note'      => 'Deactivate hides it from pickers but keeps history. Delete is only for accounts never used.',

    // customers / suppliers
    'address'          => 'Address',
    'no_movement_period' => 'No movement in this period.',

    // currencies
    'currencies_rates_h' => 'Currencies & Exchange Rates',
    'ccy_note'         => 'Currencies are shared. Exchange rates are per company — below are the rates for {0}, quoted against its base currency {1}.',
    'symbol'           => 'Symbol',
    'decimals'         => 'Decimals',
    'role_here'        => 'Role here',
    'base_badge'       => 'base',
    'foreign'          => 'foreign',
    'add_currency'     => 'Add currency',
    'recent_rates'     => '{0} — recent rates',
    'per_1_of'         => '({0} per 1 {1})',
    'no_rates_note'    => 'No rates on file — 1.0 will be used.',
    'save_rate'        => 'Save rate',
    'rate'             => 'Rate',

    // periods
    'periods_h'        => 'Accounting Periods — {0}',
    'periods_note'     => 'Closing a month locks its journals against posting and voiding.',
    'month'            => 'Month',
    'close_month_confirm' => 'Close {0} {1}?',
];
