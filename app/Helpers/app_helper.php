<?php

// ── Database ──────────────────────────────────────────────────────────────────

if (!function_exists('getDB')) {
    function getDB(): PDO {
        static $pdo = null;
        if ($pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (\PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                die('Service temporarily unavailable. Please try again later.');
            }
        }
        return $pdo;
    }
}

// ── HTML / Output ─────────────────────────────────────────────────────────────

if (!function_exists('e')) {
    function e($string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// ── Navigation ────────────────────────────────────────────────────────────────

if (!function_exists('redirect')) {
    function redirect(string $url): never {
        header('Location: ' . $url);
        exit;
    }
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
// CI4's CSRF filter (Filters.php) validates the token before any controller
// runs and removes it from $_POST. getCsrf() delegates to CI4's csrf_hash()
// so forms always use the CI4-managed token. verifyCsrf() is a no-op since
// CI4 already rejected the request if the token was invalid.

if (!function_exists('getCsrf')) {
    function getCsrf(): string {
        return csrf_hash();
    }
}

if (!function_exists('generateCsrf')) {
    function generateCsrf(): string {
        return csrf_hash();
    }
}

if (!function_exists('verifyCsrf')) {
    function verifyCsrf(): void {
        // CI4's global CSRF filter already verified and removed the token
        // before this controller ran. Nothing to do here.
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────

if (!function_exists('flash')) {
    function flash(string $key, string $message, string $type = 'success'): void {
        $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
    }
}

if (!function_exists('getFlash')) {
    function getFlash(string $key): ?array {
        $item = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $item;
    }
}

// ── Auth helpers ──────────────────────────────────────────────────────────────

if (!function_exists('requireLogin')) {
    function requireLogin(): void {
        // Use session()->get() so CI4 starts the session before reading user_id.
        // header() + exit bypasses CI4's shutdown pipeline (same pattern as logout).
        if (empty(session()->get('user_id'))) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }
}

if (!function_exists('currentUserId')) {
    function currentUserId(): ?int {
        $id = session()->get('user_id');
        return $id ? (int)$id : null;
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser(): ?object {
        if (empty(session()->get('user_id'))) return null;
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON pa.id = u.area_id WHERE u.id = ?');
        $stmt->execute([(int)session()->get('user_id')]);
        return $stmt->fetch() ?: null;
    }
}

// ── Utility ───────────────────────────────────────────────────────────────────

if (!function_exists('maskMobile')) {
    function maskMobile(string $mobile): string {
        $digits = preg_replace('/\D/', '', $mobile);
        $len    = strlen($digits);
        if ($len <= 4) return '+' . str_repeat('•', $len);
        $prefix  = substr($digits, 0, 2);
        $suffix  = substr($digits, -4);
        $bullets = str_repeat('•', max(0, $len - 6));
        return '+' . $prefix . ' ' . $bullets . $suffix;
    }
}

if (!function_exists('generateTemporaryPassword')) {
    function generateTemporaryPassword(int $length = 8): string {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $out   = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }
}
