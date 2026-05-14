<?php
// logout.php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// H1: Logout must be POST-only with CSRF to prevent forced-logout attacks.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/login.php');
}

verifyCsrf();
session_unset();
session_destroy();
redirect(APP_URL . '/login.php');
