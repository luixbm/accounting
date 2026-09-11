<?php

namespace App\Models;

/**
 * One row per company - which MyInvois environment is currently active plus
 * the company's real-world profile for LHDN (MSIC, registered address,
 * contact). Environment-specific identity (Client ID/Secret, TIN/BRN/SST) is
 * NOT here - see EinvoiceCredentialModel, one row per company + environment.
 */
class EinvoiceSettingModel extends TenantModel
{
    protected $table         = 'einvoice_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'environment', 'enabled', 'document_version', 'msic_code', 'business_activity',
        'addr_line1', 'addr_line2', 'addr_city', 'addr_postcode', 'addr_state', 'addr_country',
        'contact_phone', 'contact_email',
    ];

    /** The active company's own settings row, creating an empty default one if it doesn't exist yet. */
    public function sharedRow(): array
    {
        $row = $this->first();
        if ($row) {
            return $row;
        }
        $id = $this->insert(['company_id' => active_company_id()], true);

        return $this->find($id);
    }

    /** The active company's profile merged with its active environment's credentials. */
    public function current(): array
    {
        return $this->withCredentials($this->sharedRow());
    }

    /**
     * The company profile merged with the credentials for its active
     * environment, in the flat shape the rest of the e-invoice code expects.
     * `id` is the *credentials* row's id (MyInvoisAuth caches the OAuth token
     * onto it); the settings row's own id is under `settings_id`.
     */
    public function withCredentials(array $settings): array
    {
        $cred = model(EinvoiceCredentialModel::class)->forEnv((int) $settings['company_id'], (string) $settings['environment']);

        return [
            'id'                => $cred['id'],
            'settings_id'       => $settings['id'],
            'company_id'        => $settings['company_id'],
            'environment'       => $settings['environment'],
            'enabled'           => $settings['enabled'],
            'document_version'  => $settings['document_version'],
            'client_id'         => $cred['client_id'],
            'client_secret_enc' => $cred['client_secret_enc'],
            'cached_token'      => $cred['cached_token'],
            'token_expires_at'  => $cred['token_expires_at'],
            'tax_id'            => $cred['tax_id'],
            'id_type'           => $cred['id_type'],
            'id_value'          => $cred['id_value'],
            'sst_no'            => $cred['sst_no'],
            'msic_code'         => $settings['msic_code'],
            'business_activity' => $settings['business_activity'],
            'addr_line1'        => $settings['addr_line1'],
            'addr_line2'        => $settings['addr_line2'],
            'addr_city'         => $settings['addr_city'],
            'addr_postcode'     => $settings['addr_postcode'],
            'addr_state'        => $settings['addr_state'],
            'addr_country'      => $settings['addr_country'],
            'contact_phone'     => $settings['contact_phone'],
            'contact_email'     => $settings['contact_email'],
        ];
    }
}
