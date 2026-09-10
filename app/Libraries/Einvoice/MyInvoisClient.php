<?php

namespace App\Libraries\Einvoice;

/**
 * Thin client for the MyInvois document APIs: submit one document and poll the
 * submission for its validation result. Auth is delegated to MyInvoisAuth
 * (cached client_credentials token).
 */
class MyInvoisClient
{
    /** Public validation-portal hosts (for the QR / share link). */
    private const PORTAL = [
        'sandbox'    => 'https://preprod.myinvois.hasil.gov.my',
        'production' => 'https://myinvois.hasil.gov.my',
    ];

    private string $host;
    private string $env;
    private ?string $token;
    private ?string $authError = null;

    public function __construct(array $settings)
    {
        $this->env  = ($settings['environment'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $this->host = MyInvoisAuth::host($this->env);

        $auth = MyInvoisAuth::token($settings);
        $this->token     = $auth['ok'] ? $auth['token'] : null;
        $this->authError = $auth['ok'] ? null : ($auth['error'] ?? 'Authentication failed.');
    }

    public function ready(): bool
    {
        return $this->token !== null;
    }

    public function authError(): ?string
    {
        return $this->authError;
    }

    /**
     * Submit one JSON document.
     *
     * @param string              $codeNumber the invoice's own number (echo id)
     * @param array<string,mixed> $document   UBL array from UblInvoiceBuilder::build()
     *
     * @return array{ok:bool, uuid?:string, submissionUid?:string, error?:string, raw?:array}
     */
    public function submitInvoice(string $codeNumber, array $document): array
    {
        if (! $this->ready()) {
            return ['ok' => false, 'error' => $this->authError ?? 'Not authenticated.'];
        }

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'error' => 'Could not encode the document JSON.'];
        }

        $body = [
            'documents' => [[
                'format'       => 'JSON',
                'document'     => base64_encode($json),
                'documentHash' => hash('sha256', $json),
                'codeNumber'   => $codeNumber,
            ]],
        ];

        $res = service('curlrequest', ['timeout' => 30])->post($this->host . '/api/v1.0/documentsubmissions', [
            'headers'     => ['Authorization' => 'Bearer ' . $this->token, 'Content-Type' => 'application/json'],
            'body'        => json_encode($body),
            'http_errors' => false,
        ]);

        $status = $res->getStatusCode();
        $data   = json_decode((string) $res->getBody(), true);
        $data   = is_array($data) ? $data : [];

        if ($status !== 202 && $status !== 200) {
            return ['ok' => false, 'error' => 'HTTP ' . $status . ': ' . mb_substr((string) $res->getBody(), 0, 500), 'raw' => $data];
        }

        $accepted = $data['acceptedDocuments'][0] ?? null;
        if (! $accepted) {
            $rej = $data['rejectedDocuments'][0]['error'] ?? $data;

            return ['ok' => false, 'error' => 'Rejected: ' . mb_substr(json_encode($rej), 0, 500), 'raw' => $data];
        }

        return [
            'ok'            => true,
            'uuid'          => (string) ($accepted['uuid'] ?? ''),
            'submissionUid' => (string) ($data['submissionUID'] ?? $data['submissionUid'] ?? ''),
            'raw'           => $data,
        ];
    }

    /**
     * Poll a submission. Returns the overall status plus the one document row
     * that matches $uuid (or the first row).
     *
     * @return array{ok:bool, overallStatus?:string, doc?:array, error?:string, raw?:array}
     */
    public function getSubmission(string $submissionUid, ?string $uuid = null): array
    {
        if (! $this->ready()) {
            return ['ok' => false, 'error' => $this->authError ?? 'Not authenticated.'];
        }

        $res = service('curlrequest', ['timeout' => 30])->get(
            $this->host . '/api/v1.0/documentsubmissions/' . rawurlencode($submissionUid) . '?pageNo=1&pageSize=100',
            [
                'headers'     => ['Authorization' => 'Bearer ' . $this->token],
                'http_errors' => false,
            ]
        );

        $status = $res->getStatusCode();
        $data   = json_decode((string) $res->getBody(), true);
        $data   = is_array($data) ? $data : [];

        if ($status !== 200) {
            return ['ok' => false, 'error' => 'HTTP ' . $status . ': ' . mb_substr((string) $res->getBody(), 0, 500), 'raw' => $data];
        }

        $summary = $data['documentSummary'] ?? [];
        $doc     = null;
        foreach ($summary as $row) {
            if ($uuid !== null && ($row['uuid'] ?? null) === $uuid) {
                $doc = $row;
                break;
            }
        }
        $doc ??= $summary[0] ?? null;

        return [
            'ok'            => true,
            'overallStatus' => strtolower((string) ($data['overallStatus'] ?? '')),
            'doc'           => $doc,
            'raw'           => $data,
        ];
    }

    /** Public share / QR link for a validated document. */
    public function validationUrl(string $uuid, string $longId): string
    {
        return self::PORTAL[$this->env] . '/' . rawurlencode($uuid) . '/share/' . rawurlencode($longId);
    }
}
