<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

    public array $default = [
        'DSN'      => '',
        'hostname' => 'localhost',
        'username' => '',
        'password' => '',
        'database' => '',
        'DBDriver' => 'MySQLi',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => false,
        'charset'  => 'utf8mb4',
        'DBCollat' => 'utf8mb4_general_ci',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port'     => 3306,
    ];

    public function __construct()
    {
        parent::__construct();

        // Allow env file to override, falling back to Docker environment variables
        $this->default['hostname'] = env('database.default.hostname', getenv('DB_HOST') ?: $this->default['hostname']);
        $this->default['username'] = env('database.default.username', getenv('DB_USER') ?: $this->default['username']);
        $this->default['password'] = env('database.default.password', getenv('DB_PASS') ?: $this->default['password']);
        $this->default['database'] = env('database.default.database', getenv('DB_NAME') ?: $this->default['database']);
        $this->default['port']     = (int) env('database.default.port', getenv('DB_PORT') ?: $this->default['port']);
    }
}
