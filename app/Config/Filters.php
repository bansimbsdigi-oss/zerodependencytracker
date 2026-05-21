<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    public array $aliases = [
        'csrf'        => \CodeIgniter\Filters\CSRF::class,
        'toolbar'     => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot'    => \CodeIgniter\Filters\Honeypot::class,
        'invalidchars'=> \CodeIgniter\Filters\InvalidChars::class,
        'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
        'appsetup'    => \App\Filters\AppSetupFilter::class,
        'clientauth'  => \App\Filters\ClientAuthFilter::class,
        'adminauth'   => \App\Filters\AdminAuthFilter::class,
    ];

    public array $globals = [
        'before' => [
            'appsetup',
            'csrf' => ['except' => ['ajax/resend-otp', 'ajax/set-tutorial-done']],
        ],
        'after'  => [],
    ];

    public array $methods = [];

    public array $filters = [
        'clientauth' => [
            'before' => [
                'dashboard',
                'audit',
                'audit-report/*',
                'profile',
                'ajax/*',
            ],
        ],
        'adminauth' => [
            'before' => [
                'admin/dashboard',
                'admin/clients*',
                'admin/questions*',
                'admin/sections*',
                'admin/areas*',
                'admin/options*',
                'admin/audits*',
                'admin/team*',
                'admin/mappings*',
                'admin/notifications*',
            ],
        ],
    ];
}
