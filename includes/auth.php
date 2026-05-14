<?php
// includes/auth.php

// L4: Security headers — sent before any output.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self';");

// M5: Session cookie flags must be set before session_start().
// Moving these here (before any output) ensures they always apply.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    // L3: Strict prevents the cookie being sent on any cross-site request.
    ini_set('session.cookie_samesite', 'Strict');
    // Enable secure flag when served over HTTPS
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
    session_start();
}

/**
 * Escapes HTML characters
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirects to a given URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Generates and returns a CSRF token
 */
function getCsrf() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function generateCsrf() {
    return getCsrf();
}

/**
 * Verifies a CSRF token from POST and rotates it
 */
function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die('Invalid CSRF token. Please refresh the page and try again.');
    }
    // Rotate token after successful verification
    unset($_SESSION['csrf_token']);
}

function flash($key, $message, $type = 'success') {
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function getFlash($key) {
    $item = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $item;
}

function generateTemporaryPassword($length = 8) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/**
 * Ensures user is logged in. Redirects to password setup if account requires it.
 */
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        redirect(APP_URL . '/login.php');
    }
    // C3: Force new users to set their own password before accessing any other page.
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'set-password.php') {
        redirect(APP_URL . '/set-password.php');
    }
}

/**
 * Get the currently logged in user ID
 */
function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUser() {
    if (empty($_SESSION['user_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON pa.id = u.area_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
