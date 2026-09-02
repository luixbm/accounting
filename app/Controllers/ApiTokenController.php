<?php

namespace App\Controllers;

use App\Models\ApiTokenModel;

/**
 * Manage the REST API credentials for the active company. Each token is bound
 * to this company; the raw value is shown once, right after it is generated.
 */
class ApiTokenController extends BaseController
{
    private ApiTokenModel $tokens;

    /** Abilities that can be granted, in display order. */
    public const ABILITIES = [
        'read'           => 'Read lookups (accounts, customers, suppliers, jobs)',
        'sales:write'    => 'Create &amp; post sales invoices',
        'purchase:write' => 'Create &amp; post purchase invoices',
        'job:write'      => 'Create &amp; update jobs (Jambix dossier data)',
    ];

    public function __construct()
    {
        $this->tokens = model(ApiTokenModel::class);
    }

    public function index()
    {
        return view('api_tokens/index', [
            'title'     => 'API Tokens',
            'tokens'    => $this->tokens->forCompany(),
            'abilities' => self::ABILITIES,
            'newToken'  => session()->getFlashdata('new_token'),
        ]);
    }

    public function create()
    {
        $name      = trim((string) $this->request->getPost('name'));
        $picked    = (array) $this->request->getPost('abilities');
        $abilities = array_values(array_intersect(array_keys(self::ABILITIES), $picked));

        if ($name === '') {
            return redirect()->back()->with('error', 'Give the token a name so you can recognise it later.');
        }
        if (! $abilities) {
            return redirect()->back()->with('error', 'Pick at least one ability.');
        }

        $issued = $this->tokens->issue($name, $abilities);

        return redirect()->to('api-tokens')->with('new_token', [
            'raw'  => $issued['raw'],
            'name' => $issued['row']['name'],
        ]);
    }

    public function revoke(int $id)
    {
        $tok = $this->tokens->find($id);
        if (! $tok) {
            return redirect()->to('api-tokens')->with('error', 'Token not found.');
        }
        if (empty($tok['revoked_at'])) {
            $this->tokens->update($id, ['revoked_at' => date('Y-m-d H:i:s')]);
        }

        return redirect()->to('api-tokens')->with('message', 'Token revoked.');
    }
}
