<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Per-report access. Applied to the /reports route group on top of the group's
 * `permission:reports.view` gate. Resolves the request path to a report key
 * (Config\Reports) and checks that report's permission — which defaults to
 * `reports.view`, so existing reports are unchanged and new ones are covered
 * automatically. Populate Config\Reports::$perms to tighten a specific report.
 */
class ReportGate implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! function_exists('auth') || ! auth()->loggedIn()) {
            return null; // the group's permission filter handles auth
        }

        $cfg  = config(\Config\Reports::class);
        $path = $request->getUri()->getPath();
        $key  = $cfg->keyForPath($path);
        if ($key === null) {
            return null; // hub page or an unlisted sub-route — baseline gate applies
        }

        $perm = $cfg->permFor($key);
        if ($perm === 'reports.view' || auth()->user()->can($perm)) {
            return null;
        }

        return redirect()->to(site_url('reports'))->with('error', lang('App.not_allowed'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
