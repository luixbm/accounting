<?php

namespace App\Filters;

use App\Libraries\Api\ApiContext;
use App\Models\ApiTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Bearer-token auth for the /api/* endpoints. Resolves the token, binds its
 * company into ApiContext (which active_company_id() then honours) and rejects
 * anything unauthenticated with a JSON 401.
 */
class ApiAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $raw = $this->extractToken($request);
        if ($raw === '') {
            return $this->deny('missing_token', 'Provide an API token via the Authorization: Bearer header.');
        }

        $row = model(ApiTokenModel::class)->byHash(hash('sha256', $raw));
        if ($row === null || ! empty($row['revoked_at'])) {
            return $this->deny('invalid_token', 'The API token is invalid or has been revoked.');
        }

        ApiContext::set($row);

        // Throttled "last used" bookkeeping - at most once a minute per token.
        if (empty($row['last_used_at']) || strtotime((string) $row['last_used_at']) < time() - 60) {
            model(ApiTokenModel::class)->touch((int) $row['id']);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        ApiContext::clear();

        return null;
    }

    private function extractToken(RequestInterface $request): string
    {
        $auth = $request->getHeaderLine('Authorization');
        if (stripos($auth, 'bearer ') === 0) {
            return trim(substr($auth, 7));
        }

        return trim($request->getHeaderLine('X-Api-Key'));
    }

    private function deny(string $code, string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }
}
