<?php

namespace App\Libraries\Einvoice;

use App\Models\EinvoiceSettingModel;

/**
 * OAuth2 client_credentials login against MyInvois's identity service. Tokens
 * are valid ~60 minutes and MyInvois rate-limits logins, so the token is
 * cached on the company's einvoice_settings row and only refreshed once it's
 * actually expired (with a small safety buffer).
 */
class MyInvoisAuth
{
    public const HOSTS = [
        'sandbox'    => 'https://preprod-api.myinvois.hasil.gov.my',
        'production' => 'https://api.myinvois.hasil.gov.my',
    ];

    public static function host(string $environment): string
    {
        return self::HOSTS[$environment] ?? self::HOSTS['sandbox'];
    }

    /** @return array{ok:bool, token?:string, error?:string} */
    public static function token(array $settings): array
    {
        if (
            ! empty($settings['cached_token'])
            && ! empty($settings['token_expires_at'])
            && strtotime($settings['token_expires_at']) > time() + 30
        ) {
            return ['ok' => true, 'token' => $settings['cached_token']];
        }

        if (empty($settings['client_id']) || empty($settings['client_secret_enc'])) {
            return ['ok' => false, 'error' => 'Client ID / secret not set.'];
        }

        $host = self::host($settings['environment'] ?? 'sandbox');

        try {
            $secret = Secret::decrypt($settings['client_secret_enc']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'The saved client secret could not be decrypted — re-enter it in Setup → E-Invoice and save.'];
        }

        $res = service('curlrequest', ['timeout' => 20])->post($host . '/connect/token', [
            'form_params' => [
                'client_id'     => $settings['client_id'],
                'client_secret' => $secret,
                'grant_type'    => 'client_credentials',
                'scope'         => 'InvoicingAPI',
            ],
            'http_errors' => false,
        ]);

        $status = $res->getStatusCode();
        $body   = json_decode((string) $res->getBody(), true) ?? [];

        if ($status !== 200 || empty($body['access_token'])) {
            return ['ok' => false, 'error' => 'HTTP ' . $status . ': ' . (string) $res->getBody()];
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        model(EinvoiceSettingModel::class)->update((int) $settings['id'], [
            'cached_token'     => $body['access_token'],
            'token_expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
        ]);

        return ['ok' => true, 'token' => $body['access_token']];
    }
}
