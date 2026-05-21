<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AppSetupFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // ── Base URL ─────────────────────────────────────────────────────────
        if (!defined('APP_URL')) {
            $baseURL = rtrim(env('app.baseURL', 'http://localhost'), '/');
            define('APP_URL', $baseURL);
        }

        // ── App name ─────────────────────────────────────────────────────────
        if (!defined('APP_NAME')) {
            define('APP_NAME', env('app.appName', 'Zero Dependency Tracker'));
        }

        // ── Database credentials (for PDO getDB()) ───────────────────────────
        // env() reads CI4 .env file; getenv() reads Docker/system env vars
        if (!defined('DB_HOST')) define('DB_HOST', env('database.default.hostname', getenv('DB_HOST') ?: 'localhost'));
        if (!defined('DB_NAME')) define('DB_NAME', env('database.default.database', getenv('DB_NAME') ?: ''));
        if (!defined('DB_USER')) define('DB_USER', env('database.default.username', getenv('DB_USER') ?: ''));
        if (!defined('DB_PASS')) define('DB_PASS', env('database.default.password', getenv('DB_PASS') ?: ''));

        // ── WhatsApp / Combot ─────────────────────────────────────────────────
        if (!defined('COMBOT_API_URL')) {
            define('COMBOT_API_URL', env('COMBOT_API_URL', 'https://crm.sabhibot.com/api/meta/v19.0/850901054776919/messages'));
        }
        if (!defined('COMBOT_API_KEY'))    define('COMBOT_API_KEY',    env('COMBOT_API_KEY', ''));
        if (!defined('ADMIN_WHATSAPP'))    define('ADMIN_WHATSAPP',    env('ADMIN_WHATSAPP', '919999999999'));
        if (!defined('OTP_EXPIRY_MINUTES')) define('OTP_EXPIRY_MINUTES', 5);

    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
