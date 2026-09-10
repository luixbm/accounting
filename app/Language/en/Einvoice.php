<?php

return [
    'saved' => 'E-Invoice settings saved.',
    'saved_no_secret' => 'Saved — but no Client secret is stored for this company yet. Paste it into the Client secret field and save again.',

    'intro' => 'Connect this company to LHDN\'s MyInvois e-Invoice system. Start in Sandbox and confirm submissions validate there before switching to Production.',

    'connection_h'   => 'Connection',
    'enabled'        => 'Enabled for this company',
    'enabled_hint'   => 'Only when checked will a "Submit to LHDN" button appear on this company\'s sales invoices.',
    'environment'    => 'Environment',
    'env_sandbox'    => 'Sandbox (testing)',
    'env_production' => 'Production (live)',
    'client_id'      => 'Client ID',
    'client_secret'  => 'Client secret',
    'client_secret_set'    => 'A secret is already saved. Leave blank to keep it.',
    'client_secret_unset'  => 'Not set yet.',

    'test_h'    => 'Test connection',
    'test_hint' => 'Requests a token from LHDN using the saved Client ID and secret. Save your changes first if you just edited them.',
    'test_btn'  => 'Test connection',
    'test_missing'    => 'Enter and save a Client ID and secret first.',
    'test_secret_bad' => 'The saved secret could not be decrypted — re-enter it and save.',
    'test_ok'   => 'Connected to {0} — token received (expires around {1}).',
    'test_fail' => 'LHDN rejected the login: {0}',

    'profile_h'    => 'Company tax profile (for LHDN)',
    'tax_id'       => 'TIN (Tax Identification Number)',
    'id_type'      => 'Registration ID type',
    'id_value'     => 'Registration ID number',
    'sst_no'       => 'SST registration no.',
    'msic_code'    => 'MSIC code',
    'business_activity' => 'Business activity description',

    'address_h'   => 'Registered address',
    'addr_line1'  => 'Address line 1',
    'addr_line2'  => 'Address line 2',
    'addr_city'   => 'City',
    'addr_postcode' => 'Postcode',
    'addr_state'  => 'State code',
    'addr_country' => 'Country code',

    'contact_h'     => 'Contact',
    'contact_phone' => 'Phone',
    'contact_email' => 'Email',
];
