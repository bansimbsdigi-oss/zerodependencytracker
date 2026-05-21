<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Session extends BaseConfig
{
    public string $driver = 'CodeIgniter\Session\Handlers\FileHandler';
    public string $cookieName = 'ci_session';
    public int $expiration = 7200;
    public string $savePath = WRITEPATH . 'session';
    public bool $matchIP = false;
    public int $timeToUpdate = 3600;
    public bool $regenerateDestroy = false;
}
