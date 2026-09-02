<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Applies the viewer's chosen UI language before the controller runs, so
 * lang() calls in views resolve correctly.
 */
class SetLocale implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (method_exists($request, 'setLocale')) {
            $request->setLocale(app_locale());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
