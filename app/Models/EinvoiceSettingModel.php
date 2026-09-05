<?php

namespace App\Models;

/** One row per company - LHDN MyInvois credentials + company tax profile. */
class EinvoiceSettingModel extends TenantModel
{
    protected $table         = 'einvoice_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'environment', 'enabled', 'document_version', 'client_id', 'client_secret_enc',
        'cached_token', 'token_expires_at', 'tax_id', 'id_type', 'id_value', 'sst_no',
        'msic_code', 'business_activity', 'addr_line1', 'addr_line2', 'addr_city',
        'addr_postcode', 'addr_state', 'addr_country', 'contact_phone', 'contact_email',
    ];

    /** The active company's row, creating an empty default one if it doesn't exist yet. */
    public function current(): array
    {
        $row = $this->first();
        if ($row) {
            return $row;
        }
        $id = $this->insert(['company_id' => active_company_id()], true);

        return $this->find($id);
    }
}
