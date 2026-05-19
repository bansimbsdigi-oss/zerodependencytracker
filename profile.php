<?php
// profile.php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = currentUserId();
$pdo = getDB();

$flash = '';
$error = '';
$activeTab = $_POST['form_type'] ?? 'details';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($activeTab === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $passwordHash = $stmt->fetchColumn();

        if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
            $error = "Current password is incorrect.";
        } elseif (strlen($newPassword) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPassword)) {
            $error = "New password must be at least 8 characters and include uppercase, lowercase, and a number.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New password and confirmation do not match.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $userId])) {
                $flash = "Password changed successfully.";
            } else {
                $error = "Failed to change password.";
            }
        }
    } else {
        $activeTab = 'details';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        if (strlen($name) < 2 || strlen($name) > 100) {
            $error = "Name must be between 2 and 100 characters.";
        } elseif (empty($email) || empty($mobile)) {
            $error = "Email and WhatsApp mobile are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
            $error = "Mobile number must be 10-15 digits.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR mobile = ?) AND id != ?");
            $stmt->execute([$email, $mobile, $userId]);
            if ($stmt->fetch()) {
                $error = "Email or WhatsApp mobile is already in use by another account.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, mobile = ? WHERE id = ?");
                if ($stmt->execute([$name, $email, $mobile, $userId])) {
                    $flash = "Profile updated successfully.";
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON u.area_id = pa.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$userInitial = strtoupper(substr($user->name ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <style>
    body.profile-page { background: #f8fafc; color: #0f172a; }
    .profile-topbar { background: #fff; border-top: 3px solid #0f8f83; border-bottom: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05); }
    .profile-topbar-inner { max-width: 1440px; margin: 0 auto; height: 76px; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; }
    .profile-brand { display: inline-flex; align-items: center; gap: 0.75rem; color: #0f8f83; font-size: 1.45rem; font-weight: 800; }
    .profile-brand-mark { width: 45px; height: 45px; border-radius: 10px; background: #0f8f83; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .profile-nav { display: flex; align-items: center; gap: 0.75rem; }
    .profile-nav a, .profile-avatar { min-height: 38px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
    .profile-nav a { padding: 0 1rem; color: #374151; background: transparent; }
    .profile-nav a.active, .profile-nav a.logout { background: #f3f4f6; }
    .profile-nav a.active { color: #0f8f83; background: #ecfdf5; }
    .profile-avatar { width: 40px; background: #0f8f83; color: #fff; }
    .profile-main { max-width: 800px; margin: 3.5rem auto; padding: 0 1rem; }
    .profile-tabs { display: flex; gap: 1.5rem; border-bottom: 1px solid #dbe2ea; margin-bottom: 2rem; }
    .profile-tab { appearance: none; border: 0; background: transparent; padding: 0 1.5rem 0.85rem; color: #64748b; font: inherit; font-weight: 800; cursor: pointer; border-bottom: 2px solid transparent; }
    .profile-tab.active { color: #0f8f83; border-bottom-color: #0f8f83; }
    .profile-panel { display: none; }
    .profile-panel.active { display: block; }
    .profile-card { background: #fff; border: 1px solid #dfe5ec; border-radius: 16px; box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04); padding: 1.9rem; }
    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.35rem 1.25rem; }
    .profile-span { grid-column: 1 / -1; }
    .profile-label { display: block; color: #172033; font-size: 0.95rem; font-weight: 800; margin-bottom: 0.45rem; }
    .profile-required { color: #dc2626; }
    .profile-input { width: 100%; min-height: 54px; padding: 0.8rem 1rem; border: 1px solid #d9e0e8; border-radius: 12px; background: #fff; color: #071226; font: inherit; font-size: 1rem; }
    .profile-input:focus { outline: none; border-color: #0f8f83; box-shadow: 0 0 0 3px rgba(15, 143, 131, 0.12); }
    .profile-input[disabled] { background: #f8fafc; color: #64748b; cursor: not-allowed; }
    .profile-help { display: block; margin-top: 0.45rem; color: #64748b; font-size: 0.92rem; }
    .profile-submit { margin-top: 1.5rem; min-height: 50px; padding: 0 1.35rem; border: 0; border-radius: 11px; background: #0f8f83; color: #fff; font: inherit; font-weight: 800; cursor: pointer; }
    .profile-submit:hover { background: #0f766e; }
    .profile-alert { padding: 0.9rem 1rem; border-radius: 12px; margin-bottom: 1rem; font-weight: 700; }
    .profile-alert.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .profile-alert.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    @media (max-width: 760px) {
      .profile-topbar-inner { height: auto; padding: 0.9rem 1rem; align-items: flex-start; gap: 0.8rem; flex-direction: column; }
      .profile-nav { flex-wrap: wrap; }
      .profile-main { margin: 2rem auto; }
      .profile-tabs { gap: 0; }
      .profile-tab { flex: 1; padding-left: 0.5rem; padding-right: 0.5rem; }
      .profile-card { padding: 1.2rem; }
      .profile-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="profile-page">
  <header class="site-header">
    <div class="container">
      <a href="<?= APP_URL ?>/dashboard.php" class="brand">&#x2665; Zero Dependency Tracker</a>
      <div class="nav-links">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <div class="nav-avatar-wrap">
          <button class="nav-avatar" id="navAvatarBtn" aria-label="Account menu" aria-expanded="false">
            <?= e($userInitial) ?>
          </button>
          <div class="nav-dropdown" id="navDropdown">
            <a href="<?= APP_URL ?>/profile.php" style="color:var(--primary);font-weight:600;">&#128100; Profile</a>
            <hr>
            <form method="POST" action="<?= APP_URL ?>/logout.php">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
              <button type="submit">&#x2192; Logout</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="profile-main">
    <?php if ($flash): ?>
      <div class="profile-alert success"><?= e($flash) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="profile-alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="profile-tabs" role="tablist">
      <button type="button" class="profile-tab <?= $activeTab === 'password' ? '' : 'active' ?>" data-tab-button="details">Edit Details</button>
      <button type="button" class="profile-tab <?= $activeTab === 'password' ? 'active' : '' ?>" data-tab-button="password">Change Password</button>
    </div>

    <section class="profile-panel <?= $activeTab === 'password' ? '' : 'active' ?>" data-tab-panel="details">
      <div class="profile-card">
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="form_type" value="details">

          <div class="profile-grid">
            <div>
              <label class="profile-label">Full Name <span class="profile-required">*</span></label>
              <input type="text" name="name" class="profile-input" value="<?= e($_POST['name'] ?? $user->name) ?>" required>
            </div>
            <div>
              <label class="profile-label">Email Address <span class="profile-required">*</span></label>
              <input type="email" name="email" class="profile-input" value="<?= e($_POST['email'] ?? $user->email) ?>" required>
            </div>
            <div class="profile-span">
              <label class="profile-label">WhatsApp Mobile <span class="profile-required">*</span></label>
              <input type="text" name="mobile" class="profile-input" value="<?= e($_POST['mobile'] ?? $user->mobile) ?>" required>
            </div>
            <div class="profile-span">
              <label class="profile-label">Area of Problem</label>
              <input type="text" class="profile-input" value="<?= e($user->area_name ?? 'Unassigned') ?>" disabled>
              <span class="profile-help">Contact your coach to change your problem area.</span>
            </div>
          </div>

          <button type="submit" class="profile-submit">Save Changes</button>
        </form>
      </div>
    </section>

    <section class="profile-panel <?= $activeTab === 'password' ? 'active' : '' ?>" data-tab-panel="password">
      <div class="profile-card">
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="form_type" value="password">

          <div class="profile-grid">
            <div class="profile-span">
              <label class="profile-label">Current Password <span class="profile-required">*</span></label>
              <input type="password" name="current_password" class="profile-input" required>
            </div>
            <div>
              <label class="profile-label">New Password <span class="profile-required">*</span></label>
              <input type="password" name="new_password" class="profile-input" minlength="8" required>
            </div>
            <div>
              <label class="profile-label">Confirm Password <span class="profile-required">*</span></label>
              <input type="password" name="confirm_password" class="profile-input" minlength="8" required>
            </div>
          </div>

          <button type="submit" class="profile-submit">Change Password</button>
        </form>
      </div>
    </section>
  </main>

  <script>
    const buttons = document.querySelectorAll('[data-tab-button]');
    const panels = document.querySelectorAll('[data-tab-panel]');
    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        const target = button.dataset.tabButton;
        buttons.forEach((item) => item.classList.toggle('active', item === button));
        panels.forEach((panel) => panel.classList.toggle('active', panel.dataset.tabPanel === target));
      });
    });
  </script>
</body>
</html>
