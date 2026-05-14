<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

requireAdminLogin();

function adminSidebarLink($href, $label, $activeKey, $active, $icon = '', $badge = '') {
    $isActive = $active === $activeKey ? ' active' : '';
    $badgeHtml = $badge !== '' ? '<span class="admin-menu-badge">' . e($badge) . '</span>' : '';
    echo '<a href="' . APP_URL . $href . '" class="admin-nav-item' . $isActive . '"><span class="admin-nav-icon">' . e($icon) . '</span><span>' . e($label) . '</span>' . $badgeHtml . '</a>';
}

function adminPageStart($title, $active = '') {
    $unread = getUnreadNotificationCount();
    $role = $_SESSION['admin_role'] ?? '';
    $adminName = $_SESSION['admin_name'] ?? 'Administrator';
    $adminInitial = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - <?= APP_NAME ?> Admin</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <span class="admin-brand-icon">&#x2665;</span>
      <span><strong>Zero Dependency Tracker</strong><small>Admin Portal</small></span>
    </div>
    <nav class="admin-nav">
      <div class="admin-nav-section">Overview</div>
      <?php adminSidebarLink('/admin/dashboard.php', 'Dashboard', 'dashboard', $active, '⌂'); ?>

      <?php if (hasPermission('view_clients')): ?>
        <div class="admin-nav-section">Clients</div>
        <?php adminSidebarLink('/admin/clients/index.php', 'All Clients', 'clients', $active, '♟'); ?>
      <?php endif; ?>

      <div class="admin-nav-section">Content</div>
      <?php if (hasPermission('manage_questions')): ?>
        <?php adminSidebarLink('/admin/questions/index.php', 'Questions', 'questions', $active, '?'); ?>
      <?php endif; ?>
      <?php if (hasPermission('manage_areas')): ?>
        <?php adminSidebarLink('/admin/areas/index.php', 'Problem Areas', 'areas', $active, '•'); ?>
      <?php endif; ?>
      <?php if (hasPermission('manage_mappings')): ?>
        <?php adminSidebarLink('/admin/mappings/index.php', 'Q-Area Mapping', 'mappings', $active, '↔'); ?>
      <?php endif; ?>

      <?php if (hasPermission('view_scores')): ?>
        <div class="admin-nav-section">Audits</div>
        <?php adminSidebarLink('/admin/audits/index.php', 'Audit Windows', 'audits', $active, '▦'); ?>
      <?php endif; ?>

      <?php if ($role === 'admin'): ?>
        <div class="admin-nav-section">Team</div>
        <?php adminSidebarLink('/admin/team/index.php', 'Team Members', 'team', $active, '🤝'); ?>
      <?php endif; ?>

      <div class="admin-nav-section">System</div>
      <?php adminSidebarLink('/admin/notifications/index.php', 'Notifications', 'notifications', $active, '🔔', $unread ? (string)$unread : ''); ?>
    </nav>
    <div class="admin-user">
      <span class="admin-user-avatar"><?= e($adminInitial) ?></span>
      <span><strong><?= e($adminName) ?></strong><small><?= e(ucwords(str_replace('_', ' ', $role ?: 'admin'))) ?></small></span>
    </div>
  </aside>
  <main class="admin-main">
    <header class="admin-header">
      <div>
        <div class="admin-breadcrumb">Admin / <strong><?= e($title) ?></strong></div>
        <h1><?= e($title) ?></h1>
      </div>
      <div class="admin-header-actions">
        <a href="<?= APP_URL ?>/admin/notifications/index.php" class="admin-bell" aria-label="Notifications">🔔<?= $unread ? '<span>' . (int)$unread . '</span>' : '' ?></a>
        <form method="POST" action="<?= APP_URL ?>/admin/logout.php" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <button type="submit" class="admin-logout" style="border:0;background:transparent;cursor:pointer;font:inherit;">Logout</button>
        </form>
      </div>
    </header>
    <div class="admin-content">
      <?php $flash = getFlash('admin'); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endif; ?>
<?php
}

function adminPageEnd() {
?>
    </div>
  </main>
</div>
<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
</body>
</html>
<?php
}

function adminCanAccessClient($clientId) {
    if (($_SESSION['admin_role'] ?? '') === 'admin') return true;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT 1 FROM client_assignments WHERE client_id = ? AND team_member_id = ?");
    $stmt->execute([$clientId, $_SESSION['admin_id']]);
    return (bool)$stmt->fetchColumn();
}
