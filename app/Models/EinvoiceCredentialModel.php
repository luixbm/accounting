<?php

namespace App\Models;

/**
 * One row per company + environment (sandbox | production): the LHDN
 * ERP Client ID/Secret and the taxpayer identity (TIN/BRN/SST) that pair is
 * registered under. Sandbox and production are separate MyInvois
 * registrations with different identities - preprod ties its Client ID to a
 * synthetic test TIN, not the company's real one - so these never share a row.
 */
class EinvoiceCredentialModel extends TenantModel
{
    protected $table         = 'einvoice_credentials';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'environment', 'client_id', 'client_secret_enc', 'cached_token', 'token_expires_at',
        'tax_id', 'id_type', 'id_value', 'sst_no',
    ];

    /** The row for this company + environment, creating an empty one if it doesn't exist yet. */
    public function forEnv(int $companyId, string $environment): array
    {
        $row = $this->where('company_id', $companyId)->where('environment', $environment)->first();
        if ($row) {
            return $row;
        }
        $id = $this->insert(['company_id' => $companyId, 'environment' => $environment], true);

        return $this->find($id);
    }
}
