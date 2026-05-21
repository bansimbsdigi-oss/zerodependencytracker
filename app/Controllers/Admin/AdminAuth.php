<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class AdminAuth extends Controller
{
    public function index()
    {
        if (!empty($_SESSION['admin_id'])) {
            return redirect()->to(APP_URL . '/admin/dashboard');
        }
        return view('admin/login', ['error' => '']);
    }

    public function login()
    {
        if (!empty($_SESSION['admin_id'])) {
            return redirect()->to(APP_URL . '/admin/dashboard');
        }

        verifyCsrf();

        $email    = trim($this->request->getPost('email') ?? '');
        $password = $this->request->getPost('password') ?? '';

        if (empty($email) || empty($password)) {
            return view('admin/login', ['error' => 'Email and password are required.']);
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && $admin->locked_until && strtotime($admin->locked_until) > time()) {
            return view('admin/login', ['error' => 'Account locked due to too many failed attempts. Try again later.']);
        }

        if ($admin && password_verify($password, $admin->password)) {
            $pdo->prepare('UPDATE admin_users SET login_attempts = 0, locked_until = NULL WHERE id = ?')
                ->execute([$admin->id]);

            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin->id;
            $_SESSION['admin_name'] = $admin->name;
            $_SESSION['admin_role'] = $admin->role;

            if ($admin->role === 'team_member') {
                $perms = $pdo->prepare('SELECT permission FROM admin_permissions WHERE admin_user_id = ?');
                $perms->execute([$admin->id]);
                $_SESSION['admin_permissions'] = $perms->fetchAll(\PDO::FETCH_COLUMN);
            }

            return redirect()->to(APP_URL . '/admin/dashboard');
        }

        if ($admin) {
            $newAttempts = (int)$admin->login_attempts + 1;
            $lockedUntil = $newAttempts >= 10 ? date('Y-m-d H:i:s', time() + 900) : null;
            $pdo->prepare('UPDATE admin_users SET login_attempts = ?, locked_until = ? WHERE id = ?')
                ->execute([$newAttempts, $lockedUntil, $admin->id]);
        }

        return view('admin/login', ['error' => 'Invalid credentials or inactive account.']);
    }

    public function logout()
    {
        // CI4 4.4+ getMethod() returns uppercase ('POST', 'GET')
        if (strtolower($this->request->getMethod()) !== 'post') {
            return redirect()->to(APP_URL . '/admin/login');
        }
        // 1. Wipe session data so even if the file is re-written it has no auth keys
        $_SESSION = [];
        // 2. Delete the CI4 session file and expire the browser cookie
        session()->destroy();
        // 3. Explicitly expire the cookie in the browser (belt-and-suspenders)
        setcookie('ci_session', '', time() - 3600, '/');
        // 4. Use raw header + exit so CI4's end-of-request pipeline
        //    (which can call session_write_close and re-persist the session)
        //    never runs.
        header('Location: ' . APP_URL . '/admin/login');
        exit;
    }
}
