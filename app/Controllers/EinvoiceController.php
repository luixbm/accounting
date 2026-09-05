<?php

namespace App\Controllers;

use App\Libraries\Einvoice\Secret;
use App\Models\EinvoiceSettingModel;

/**
 * LHDN MyInvois e-Invoice: company credentials/profile (this file) plus, once
 * built, per-invoice submission (see routes `sales/(:num)/einvoice/*`).
 */
class EinvoiceController extends BaseController
{
    private const ID_TYPES     = ['BRN', 'NRIC', 'PASSPORT', 'ARMY'];
    private const ENVIRONMENTS = ['sandbox', 'production'];

    private function model(): EinvoiceSettingModel
    {
        return model(EinvoiceSettingModel::class);
    }

    public function settings()
    {
        $row = $this->model()->current();

        return view('einvoice/settings', [
            'title' => lang('Nav.einvoice'),
            'row'   => $row,
            'hasSecret' => $row['client_secret_enc'] !== null && $row['client_secret_enc'] !== '',
        ]);
    }

    public function saveSettings()
    {
        $model = $this->model();
        $row   = $model->current();

        $str = fn (string $k): ?string => ($v = trim((string) $this->request->getPost($k))) !== '' ? $v : null;

        $env = (string) $this->request->getPost('environment');
        $idt = (string) $this->request->getPost('id_type');

        $data = [
            'environment'       => in_array($env, self::ENVIRONMENTS, true) ? $env : 'sandbox',
            'enabled'           => $this->request->getPost('enabled') !== null ? 1 : 0,
            'client_id'         => $str('client_id'),
            'tax_id'            => $str('tax_id'),
            'id_type'           => in_array($idt, self::ID_TYPES, true) ? $idt : 'BRN',
            'id_value'          => $str('id_value'),
            'sst_no'            => $str('sst_no'),
            'msic_code'         => $str('msic_code'),
            'business_activity' => $str('business_activity'),
            'addr_line1'        => $str('addr_line1'),
            'addr_line2'        => $str('addr_line2'),
            'addr_city'         => $str('addr_city'),
            'addr_postcode'     => $str('addr_postcode'),
            'addr_state'        => $str('addr_state'),
            'addr_country'      => $str('addr_country') ?? 'MYS',
            'contact_phone'     => $str('contact_phone'),
            'contact_email'     => $str('contact_email'),
        ];

        // Secret is write-only: a blank field on save means "keep the existing
        // one" (never re-shown), matching the api_tokens "shown once" convention.
        $newSecret = (string) $this->request->getPost('client_secret');
        if ($newSecret !== '') {
            $data['client_secret_enc'] = Secret::encrypt($newSecret);
            // a new secret invalidates any cached token from the old one
            $data['cached_token']     = null;
            $data['token_expires_at'] = null;
        }

        $model->update($row['id'], $data);

        return redirect()->to('settings/einvoice')->with('message', lang('Einvoice.saved'));
    }
}
