<?php
// CSRF Debug script — DELETE AFTER TESTING
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Read session cookie manually
$sessionId = $_COOKIE['ci_session'] ?? 'NO_COOKIE';
$sessionFile = ROOTPATH . 'writable/session/ci_session' . $sessionId;

echo "Session ID from cookie: $sessionId\n";
echo "Session file exists: " . (file_exists($sessionFile) ? 'YES' : 'NO') . "\n";

if (file_exists($sessionFile)) {
    echo "Session file content:\n";
    echo file_get_contents($sessionFile) . "\n";
}

// Start native PHP session to see what PHP sees
ini_set('session.save_path', ROOTPATH . 'writable/session');
ini_set('session.name', 'ci_session');
session_start();
echo "\nPHP session data:\n";
var_dump($_SESSION);
echo "\ncsrf_token from session: " . ($_SESSION['csrf_token'] ?? 'NOT FOUND') . "\n";

// What POST token do we have?
echo "\nPOST csrf_token: " . ($_POST['csrf_token'] ?? 'NOT IN POST') . "\n";
echo "Match: " . (
    isset($_SESSION['csrf_token'], $_POST['csrf_token']) &&
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ? 'YES' : 'NO'
) . "\n";
