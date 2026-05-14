<?php
// admin/login.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['admin_id'])) redirect(APP_URL . '/admin/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        // H3: Rate limiting — check lockout before verifying password (prevents timing oracle).
        if ($admin && $admin->locked_until && strtotime($admin->locked_until) > time()) {
            $error = "Account locked due to too many failed attempts. Try again later.";
        } elseif ($admin && password_verify($password, $admin->password)) {
            // Reset failed attempt counter on successful login.
            $pdo->prepare("UPDATE admin_users SET login_attempts = 0, locked_until = NULL WHERE id = ?")
                ->execute([$admin->id]);

            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin->id;
            $_SESSION['admin_name'] = $admin->name;
            $_SESSION['admin_role'] = $admin->role;

            if ($admin->role === 'team_member') {
                $perms = $pdo->prepare("SELECT permission FROM admin_permissions WHERE admin_user_id = ?");
                $perms->execute([$admin->id]);
                $_SESSION['admin_permissions'] = $perms->fetchAll(PDO::FETCH_COLUMN);
            }

            redirect(APP_URL . '/admin/dashboard.php');
        } else {
            $error = "Invalid credentials or inactive account.";
            // H3: Increment attempt counter; lock after 10 consecutive failures for 15 minutes.
            if ($admin) {
                $newAttempts = (int)$admin->login_attempts + 1;
                $lockedUntil = $newAttempts >= 10 ? date('Y-m-d H:i:s', time() + 900) : null;
                $pdo->prepare("UPDATE admin_users SET login_attempts = ?, locked_until = ? WHERE id = ?")
                    ->execute([$newAttempts, $lockedUntil, $admin->id]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <style>
    body {
      min-height: 100vh;
      margin: 0;
      background: #0f172a;
      color: #0f172a;
      font-family: var(--font);
    }
    .admin-login-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: minmax(320px, 0.9fr) minmax(420px, 1.1fr);
    }
    .admin-login-panel {
      background: #111827;
      color: #fff;
      padding: 3rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-right: 1px solid rgba(255,255,255,0.08);
    }
    .admin-login-brand {
      display: flex;
      align-items: center;
      gap: 0.9rem;
      font-weight: 900;
      font-size: 1.45rem;
    }
    .admin-login-mark {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: #0f8f83;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .admin-login-copy h1 {
      font-size: 2.25rem;
      line-height: 1.12;
      margin: 0 0 1rem;
      color: #fff;
    }
    .admin-login-copy p {
      color: #cbd5e1;
      max-width: 420px;
      font-size: 1rem;
    }
    .admin-login-points {
      display: grid;
      gap: 0.85rem;
      margin-top: 2rem;
      color: #dbeafe;
      font-weight: 700;
    }
    .admin-login-points span {
      display: flex;
      align-items: center;
      gap: 0.7rem;
    }
    .admin-login-main {
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }
    .admin-login-card {
      width: min(460px, 100%);
      background: #fff;
      border: 1px solid #dfe5ec;
      border-radius: 18px;
      box-shadow: 0 24px 70px rgba(15, 23, 42, 0.14);
      padding: 2rem;
    }
    .admin-login-card h2 {
      margin: 0 0 0.35rem;
      color: #020617;
      font-size: 1.55rem;
    }
    .admin-login-card .muted {
      color: #64748b;
      margin-bottom: 1.6rem;
    }
    .admin-login-card .form-control {
      min-height: 52px;
      border-radius: 12px;
    }
    .admin-login-submit {
      width: 100%;
      min-height: 52px;
      border: 0;
      border-radius: 12px;
      background: #0f8f83;
      color: #fff;
      font: inherit;
      font-weight: 900;
      cursor: pointer;
      margin-top: 0.6rem;
    }
    .admin-login-submit:hover {
      background: #0f766e;
    }
    .admin-login-help {
      margin-top: 1.2rem;
      color: #64748b;
      font-size: 0.9rem;
      text-align: center;
    }
    @media (max-width: 860px) {
      .admin-login-shell { grid-template-columns: 1fr; }
      .admin-login-panel { gap: 3rem; padding: 2rem; }
      .admin-login-main { padding: 1.25rem; }
    }
  </style>
</head>
<body>
  <div class="admin-login-shell">
    <aside class="admin-login-panel">
      <div class="admin-login-brand">
        <span class="admin-login-mark">&#x2665;</span>
        <span>Zero Dependency Tracker</span>
      </div>
      <div class="admin-login-copy">
        <h1>Admin Portal</h1>
        <p>Manage clients, audit windows, questions, team assignments, and progress notifications from one focused workspace.</p>
        <div class="admin-login-points">
          <span>✓ Client and team management</span>
          <span>✓ Audit progress monitoring</span>
          <span>✓ Secure role-based access</span>
        </div>
      </div>
      <p style="color:#64748b;margin:0;">Authorized administrators only</p>
    </aside>

    <main class="admin-login-main">
      <section class="admin-login-card">
        <h2>Sign in</h2>
        <p class="muted">Use your admin credentials to continue.</p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@yourdomain.com" required autofocus>
          </div>

          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Password" required>
          </div>

          <button type="submit" class="admin-login-submit">Login to Admin Dashboard</button>
        </form>
        <p class="admin-login-help">Contact your system administrator if you have forgotten your credentials.</p>
      </section>
    </main>
  </div>
</body>
</html>
