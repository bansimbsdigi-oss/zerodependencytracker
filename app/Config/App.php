<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    /**
     * Base URL — override via .env: app.baseURL = 'https://yourdomain.com'
     */
    public string $baseURL = 'http://localhost/';

    public string $appName = 'Zero Dependency Tracker';

    /**
     * Remove index.php from all URLs (handled by .htaccess rewrite)
     */
    public string $indexPage = '';

    public string $uriProtocol    = 'REQUEST_URI';
    public string $defaultLocale  = 'en';
    public bool   $negotiateLocale = false;
    public array  $supportedLocales = ['en'];
    public string $appTimezone    = 'Asia/Kolkata';
    public string $charset        = 'UTF-8';

    /**
     * Set to true on production if site is 100% HTTPS.
     * Override via .env: app.forceGlobalSecureRequests = true
     */
    public bool $forceGlobalSecureRequests = false;

    public array $proxyIPs = [];

    public array $allowedHostnames = [];

    // ── Session ──────────────────────────────────────────────────────────────
    public string $sessionDriver            = 'CodeIgniter\\Session\\Handlers\\FileHandler';
    public string $sessionCookieName        = 'ci_session';
    public int    $sessionExpiration        = 7200;
    public string $sessionSavePath          = WRITEPATH . 'session';
    public bool   $sessionMatchIP           = false;
    public int    $sessionTimeToUpdate      = 300;
    public bool   $sessionRegenerateDestroy = false;

    // ── Cookies ───────────────────────────────────────────────────────────────
    public string  $cookiePrefix   = '';
    public string  $cookieDomain   = '';
    public string  $cookiePath     = '/';
    public bool    $cookieSecure   = false;   // Set true in .env on HTTPS
    public bool    $cookieHTTPOnly = true;
    public ?string $cookieSameSite = 'Lax';

    public bool $CSPEnabled = false;
}
