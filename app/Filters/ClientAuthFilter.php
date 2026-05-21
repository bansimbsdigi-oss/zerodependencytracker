<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ClientAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Use session()->get() so CI4 starts the PHP session even on routes
        // where the CSRF filter is excluded (e.g. ajax/*).
        if (empty(session()->get('user_id'))) {
            return redirect()->to(APP_URL . '/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
