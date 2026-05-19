<?php
// config/config.example.php
// Copy this file to config.php and fill in real values. Never commit config.php.

// Auto-detect the base URL from the current request so links work on any host/port.
if (getenv('APP_URL')) {
    define('APP_URL', rtrim(getenv('APP_URL'), '/'));
} else {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', $scheme . '://' . $host);
}
define('APP_NAME', 'Zero Dependency Tracker');

// Database — set these via environment variables or fill in below for local dev
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'your_db_name');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');

// WhatsApp / Combot Meta API — endpoint and bearer token
define('COMBOT_API_URL', getenv('COMBOT_API_URL') ?: 'https://crm.sabhibot.com/api/meta/v19.0/850901054776919/messages');
define('COMBOT_API_KEY', getenv('COMBOT_API_KEY') ?: 'YOUR_COMBOT_API_KEY_HERE');
define('ADMIN_WHATSAPP', getenv('ADMIN_WHATSAPP') ?: '919999999999');
define('OTP_EXPIRY_MINUTES', 5);
