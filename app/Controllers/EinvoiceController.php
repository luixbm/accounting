<?php

namespace App\Controllers;

use App\Libraries\CustomFields;
use App\Libraries\Einvoice\MyInvoisAuth;
use App\Libraries\Einvoice\MyInvoisClient;
use App\Libraries\Einvoice\Secret;
use App\Libraries\Einvoice\UblInvoiceBuilder;
use App\Models\CompanyModel;
use App\Models\EinvoiceCredentialModel;
use App\Models\EinvoiceSettingModel;
use App\Models\SalesInvoiceLineModel;
use App\Models\SalesInvoiceModel;

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
        $shared    = $this->model()->sharedRow();
        $credModel = model(EinvoiceCredentialModel::class);
        $creds     = [
            'sandbox'    => $credModel->forEnv((int) $shared['company_id'], 'sandbox'),
            'production' => $credModel->forEnv((int) $shared['company_id'], 'production'),
        ];

        return view('einvoice/settings', [
            'title' => lang('Nav.einvoice'),
            'row'   => $shared,
            'creds' => $creds,
            'hasSecret' => [
                'sandbox'    => ! empty($creds['sandbox']['client_secret_enc']),
                'production' => ! empty($creds['production']['client_secret_enc']),
            ],
        ]);
    }

    public function saveSettings()
    {
        $model  = $this->model();
        $shared = $model->sharedRow();

        $str = fn (string $k): ?string => ($v = trim((string) $this->request->getPost($k))) !== '' ? $v : null;

        $env = (string) $this->request->getPost('environment');
        $env = in_array($env, self::ENVIRONMENTS, true) ? $env : 'sandbox';

        $model->update($shared['id'], [
            'environment'       => $env,
            'enabled'           => $this->request->getPost('enabled') !== null ? 1 : 0,
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
        ]);

        // Sandbox and production are separate MyInvois registrations (different
        // Client ID/Secret, different TIN - preprod ties its client to a
        // synthetic test TIN) so each gets its own credentials row.
        $credModel      = model(EinvoiceCredentialModel::class);
        $activeHasNoSecret = false;
        foreach (self::ENVIRONMENTS as $envKey) {
            $cred = $credModel->forEnv((int) $shared['company_id'], $envKey);
            $idt  = strtoupper((string) $this->request->getPost($envKey . '_id_type'));

            $data = [
                'client_id' => $str($envKey . '_client_id'),
                'tax_id'    => $str($envKey . '_tax_id'),
                'id_type'   => in_array($idt, self::ID_TYPES, true) ? $idt : 'BRN',
                'id_value'  => $str($envKey . '_id_value'),
                'sst_no'    => $str($envKey . '_sst_no'),
            ];

            // Secret is write-only: a blank field on save means "keep the
            // existing one" (never re-shown), matching api_tokens' convention.
            $newSecret = (string) $this->request->getPost($envKey . '_client_secret');
            if ($newSecret !== '') {
                $data['client_secret_enc'] = Secret::encrypt($newSecret);
                // a new secret invalidates any cached token from the old one
                $data['cached_token']     = null;
                $data['token_expires_at'] = null;
            }

            $credModel->update($cred['id'], $data);

            if ($envKey === $env) {
                $secretStored      = ($data['client_secret_enc'] ?? $cred['client_secret_enc'] ?? '') !== '';
                $activeHasNoSecret = ! $secretStored && ($data['client_id'] || $this->request->getPost('enabled') !== null);
            }
        }

        // "Save" with an empty secret box on the active environment keeps
        // whatever was stored - which is nothing on a first save. Warn instead
        // of silently leaving the company half-configured for the environment
        // that's actually live.
        if ($activeHasNoSecret) {
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
            $fresh = model(EinvoiceCredentialModel::class)->find($row['id']);
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

    // ================================================================ submission

    /** Build the UBL document for a posted sales invoice and submit it to LHDN. */
    public function submit(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/' . $id)->with('error', lang('Einvoice.ei_not_allowed'));
        }

        [$inv, $err] = $this->submittableInvoice($id);
        if ($err !== null) {
            return redirect()->to('sales/' . $id)->with('error', $err);
        }

        $settings = $this->model()->current();
        $company  = model(CompanyModel::class)->find(active_company_id());
        $lines    = model(SalesInvoiceLineModel::class)->where('invoice_id', $id)->orderBy('line_no')->findAll();
        $customer = db_connect()->table('customers')->where('id', $inv['customer_id'])->get()->getRowArray() ?: [];
        $buyerCf  = (new CustomFields())->valuesFor('customer', (int) $inv['customer_id']);

        $doc    = UblInvoiceBuilder::build($inv, $lines, $settings, $company ?? [], $customer, $buyerCf);
        $client = new MyInvoisClient($settings);
        $res    = $client->submitInvoice((string) $inv['internal_no'], $doc);

        $sales = model(SalesInvoiceModel::class);
        if (! empty($res['ok'])) {
            $sales->update($id, [
                'einvoice_status'         => 'submitted',
                'einvoice_uuid'           => $res['uuid'] ?: null,
                'einvoice_submission_uid' => $res['submissionUid'] ?: null,
                'einvoice_submitted_at'   => date('Y-m-d H:i:s'),
                'einvoice_long_id'        => null,
                'einvoice_validated_at'   => null,
                'einvoice_error'          => null,
            ]);

            return redirect()->to('sales/' . $id)->with('message', lang('Einvoice.ei_submitted'));
        }

        $sales->update($id, [
            'einvoice_status' => 'invalid',
            'einvoice_error'  => mb_substr((string) ($res['error'] ?? 'Submission failed.'), 0, 4000),
        ]);

        return redirect()->to('sales/' . $id)->with('error', lang('Einvoice.ei_submit_failed', [mb_substr((string) ($res['error'] ?? ''), 0, 300)]));
    }

    /** Poll the submission and record Valid / Invalid. */
    public function checkStatus(int $id)
    {
        if (! user_can('journal.post')) {
            return redirect()->to('sales/' . $id)->with('error', lang('Einvoice.ei_not_allowed'));
        }

        $sales = model(SalesInvoiceModel::class);
        $inv   = $sales->find($id);
        if (! $inv || empty($inv['einvoice_submission_uid'])) {
            return redirect()->to('sales/' . $id)->with('error', lang('Einvoice.ei_nothing_to_check'));
        }

        $client = new MyInvoisClient($this->model()->current());
        $res    = $client->getSubmission((string) $inv['einvoice_submission_uid'], (string) ($inv['einvoice_uuid'] ?? '') ?: null);

        if (empty($res['ok'])) {
            return redirect()->to('sales/' . $id)->with('error', lang('Einvoice.ei_submit_failed', [mb_substr((string) ($res['error'] ?? ''), 0, 300)]));
        }

        if (($res['overallStatus'] ?? '') === 'in progress' || (($res['doc']['status'] ?? '') === 'Submitted')) {
            return redirect()->to('sales/' . $id)->with('message', lang('Einvoice.ei_still_processing'));
        }

        $doc    = $res['doc'] ?? [];
        $status = strtolower((string) ($doc['status'] ?? $res['overallStatus'] ?? ''));

        if ($status === 'valid') {
            $sales->update($id, [
                'einvoice_status'       => 'valid',
                'einvoice_uuid'         => $doc['uuid'] ?? $inv['einvoice_uuid'],
                'einvoice_long_id'      => $doc['longId'] ?? null,
                'einvoice_validated_at' => date('Y-m-d H:i:s'),
                'einvoice_error'        => null,
            ]);

            return redirect()->to('sales/' . $id)->with('message', lang('Einvoice.ei_valid'));
        }

        if ($status === 'cancelled') {
            $sales->update($id, ['einvoice_status' => 'cancelled']);

            return redirect()->to('sales/' . $id)->with('message', lang('Einvoice.ei_cancelled'));
        }

        $sales->update($id, [
            'einvoice_status' => 'invalid',
            'einvoice_error'  => mb_substr(json_encode($doc['validationResults'] ?? $doc ?: $res['raw'] ?? []), 0, 4000),
        ]);

        return redirect()->to('sales/' . $id)->with('error', lang('Einvoice.ei_invalid'));
    }

    /**
     * @return array{0: array<string,mixed>|null, 1: string|null}  [invoice, error]
     */
    private function submittableInvoice(int $id): array
    {
        $inv = model(SalesInvoiceModel::class)
            ->select('sales_invoices.*, currencies.code AS currency_code')
            ->join('currencies', 'currencies.id = sales_invoices.currency_id', 'left')
            ->find($id);

        if (! $inv) {
            return [null, lang('Einvoice.ei_inv_not_found')];
        }
        if (! (int) ($this->model()->current()['enabled'] ?? 0)) {
            return [null, lang('Einvoice.ei_disabled')];
        }
        if (! in_array($inv['status'], ['posted', 'partial', 'paid'], true)) {
            return [null, lang('Einvoice.ei_not_posted')];
        }
        if (in_array($inv['einvoice_status'] ?? '', ['submitted', 'valid'], true)) {
            return [null, lang('Einvoice.ei_already', [$inv['einvoice_status']])];
        }
        if (empty($inv['customer_id'])) {
            return [null, lang('Einvoice.ei_no_customer')];
        }

        return [$inv, null];
    }
}
