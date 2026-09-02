<?php

namespace App\Controllers\Api;

use App\Libraries\Api\ApiContext;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Shared JSON helpers for the token-authenticated API. Auth + company binding
 * are handled by the `apiauth` filter before any of these run.
 */
abstract class BaseApiController extends Controller
{
    protected $helpers = ['acc'];

    /** Parsed JSON (or form) request body. @return array<string,mixed> */
    protected function body(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }

        return $this->request->getPost() ?: [];
    }

    protected function respond(array $payload, int $code = 200): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON($payload);
    }

    protected function fail(string $message, int $code = 422, string $errCode = 'invalid_request', array $details = []): ResponseInterface
    {
        $err = ['code' => $errCode, 'message' => $message];
        if ($details) {
            $err['details'] = array_values($details);
        }

        return $this->response->setStatusCode($code)->setJSON(['error' => $err]);
    }

    /** @return ResponseInterface|null null = allowed */
    protected function guardAbility(string $ability): ?ResponseInterface
    {
        if (! ApiContext::can($ability)) {
            return $this->fail("This token lacks the '{$ability}' ability.", 403, 'forbidden');
        }

        return null;
    }
}
