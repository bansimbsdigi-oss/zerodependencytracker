<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Use session()->get() so CI4 starts the PHP session even on routes
        // where the CSRF filter is excluded.
        if (empty(session()->get('admin_id'))) {
            return redirect()->to(APP_URL . '/admin/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
