<?php
// config/config.example.php
// Copy this file to config.php and fill in real values. Never commit config.php.

define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8080');
define('APP_NAME', 'Zero Dependency Tracker');

// Database — set these via environment variables or fill in below for local dev
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'zerodependencytracker');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'CHANGE_ME');

// WhatsApp / Combot API key — obtain from your Combot dashboard
define('COMBOT_API_KEY', getenv('COMBOT_API_KEY') ?: 'YOUR_COMBOT_API_KEY_HERE');
define('ADMIN_WHATSAPP', getenv('ADMIN_WHATSAPP') ?: '919999999999');
define('OTP_EXPIRY_MINUTES', 10);
