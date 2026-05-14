<?php
// set-password.php — first-login password setup for new accounts (C3)

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Only accessible to authenticated users who must change their password.
if (empty($_SESSION['user_id'])) redirect(APP_URL . '/login.php');
if (empty($_SESSION['must_change_password'])) redirect(APP_URL . '/dashboard.php');

$userId = (int)$_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        if ($stmt->execute([password_hash($password, PASSWORD_BCRYPT), $userId])) {
            unset($_SESSION['must_change_password']);
            redirect(APP_URL . '/dashboard.php');
        }
        $error = "Failed to set password. Please try again.";
    }
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set Your Password — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
  <div class="container">
    <div class="auth-box">
      <div class="auth-header">
        <div class="auth-logo" style="background:var(--gray-900);">&#x1F512;</div>
        <h2>Set Your Password</h2>
        <p class="text-muted">Welcome, <?= e($user->name ?? '') ?>! Please choose a password to secure your account.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" minlength="8" required autofocus>
          <small class="text-muted">Min 8 characters, must include uppercase, lowercase, and a number.</small>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" minlength="8" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Set Password &amp; Continue</button>
      </form>
    </div>
  </div>
</body>
</html>
