<?php

namespace App\Controllers;

use App\Libraries\Einvoice\MyInvoisAuth;
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

        // "Save" with an empty secret box keeps whatever was stored - which is
        // nothing on a first save. Warn instead of silently leaving the company
        // half-configured (it will keep failing the connection test otherwise).
        $secretStored = ($data['client_secret_enc'] ?? $row['client_secret_enc'] ?? '') !== '';
        if (! $secretStored && ($data['client_id'] || $data['enabled'])) {
            return redirect()->to('settings/einvoice')->with('error', lang('Einvoice.saved_no_secret'));
        }

        return redirect()->to('settings/einvoice')->with('message', lang('Einvoice.saved'));
    }

    /**
     * Try a client_credentials token exchange against the saved credentials and
     * report the outcome. Forces a fresh call (ignores any cached token) and
     * never reveals the secret or the token itself.
     */
    public function testConnection()
    {
        $row = $this->model()->current();
        $back = redirect()->to('settings/einvoice');

        if (empty($row['client_id']) || empty($row['client_secret_enc'])) {
            return $back->with('error', lang('Einvoice.test_missing'));
        }

        // confirm the stored secret still decrypts with the current key
        try {
            Secret::decrypt($row['client_secret_enc']);
        } catch (\Throwable $e) {
            return $back->with('error', lang('Einvoice.test_secret_bad'));
        }

        $probe                     = $row;
        $probe['cached_token']     = null;
        $probe['token_expires_at'] = null;

        $res = MyInvoisAuth::token($probe);

        if (! empty($res['ok'])) {
            $fresh = $this->model()->find($row['id']);
            $exp   = $fresh['token_expires_at'] ?? null;

            return $back->with('message', lang('Einvoice.test_ok', [
                MyInvoisAuth::host($row['environment'] ?? 'sandbox'),
                $exp ? date('H:i', strtotime($exp)) : '~60m',
            ]));
        }

        return $back->with('error', lang('Einvoice.test_fail', [$this->sanitiseAuthError((string) ($res['error'] ?? ''))]));
    }

    /**
     * Reduce MyInvois's token-endpoint error to something safe and short: the
     * OAuth `error` / `error_description` when the body is JSON, otherwise a
     * trimmed status line. LHDN never echoes the secret back, but trim anyway.
     */
    private function sanitiseAuthError(string $raw): string
    {
        if (preg_match('/HTTP (\d{3})/', $raw, $m)) {
            $status = $m[1];
            if (preg_match('/\{.*\}/s', $raw, $j) && is_array($body = json_decode($j[0], true))) {
                $bits = array_filter([
                    $body['error'] ?? null,
                    $body['error_description'] ?? null,
                ]);
                if ($bits) {
                    return 'HTTP ' . $status . ' — ' . mb_substr(implode(': ', $bits), 0, 200);
                }
            }

            return 'HTTP ' . $status;
        }

        return mb_substr($raw, 0, 200) ?: 'unknown error';
    }
}
