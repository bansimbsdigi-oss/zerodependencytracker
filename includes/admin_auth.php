<?php
// includes/admin_auth.php

require_once __DIR__ . '/auth.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensures admin user is logged in
 */
function requireAdminLogin() {
    if (empty($_SESSION['admin_id'])) {
        redirect(APP_URL . '/admin/login.php');
    }
}

function requireAdmin() {
    requireAdminLogin();
}

/**
 * Checks if the currently logged in admin has a specific permission
 */
function hasPermission($permissionKey) {
    if (empty($_SESSION['admin_id'])) return false;
    
    // Admins have all permissions
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin') {
        return true;
    }
    
    // Team members check specific permissions
    $perms = $_SESSION['admin_permissions'] ?? [];
    return in_array($permissionKey, $perms, true);
}

/**
 * Enforces permission. Redirects or dies if not allowed.
 */
function requirePermission($permissionKey) {
    requireAdminLogin();
    if (!hasPermission($permissionKey)) {
        http_response_code(403);
        $label = e($permissionKey);
        die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Access Denied</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc;}.box{text-align:center;padding:2rem;background:#fff;border-radius:12px;border:1px solid #dfe5ec;max-width:440px;}h1{font-size:3rem;margin:0 0 .5rem;color:#0f172a;}p{color:#64748b;}a{color:#0f8f83;}</style></head><body><div class="box"><h1>403</h1><p>You do not have permission to access this page.</p><p><small>Required: <code>' . $label . '</code></small></p><a href="' . APP_URL . '/admin/dashboard.php">Back to Dashboard</a></div></body></html>');
    }
}

function getCurrentAdmin() {
    if (empty($_SESSION['admin_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

function getUnreadNotificationCount() {
    if (empty($_SESSION['admin_id'])) return 0;
    $pdo = getDB();
    return (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
}
