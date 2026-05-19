<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

requireAdminLogin();

function adminIcon($name, $size = 20) {
    $s = $size;
    $icons = [
        'dashboard'     => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'clients'       => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'questions'     => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'sections'      => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        'areas'         => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'mappings'      => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>',
        'audits'        => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'team'          => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'bell'          => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'trophy'        => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="14.5 17.5 3 6 3 3 6 3 17.5 14.5"/><line x1="13" y1="19" x2="19" y2="13"/><polyline points="9.5 6.5 3 6 6 3 6.5 9.5"/><polyline points="14.5 17.5 21 18 18 21 17.5 14.5"/></svg>',
        'chart'         => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'user'          => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'logout'        => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'plus'          => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function adminSidebarLink($href, $label, $activeKey, $active, $icon = '', $badge = '') {
    $isActive = $active === $activeKey ? ' active' : '';
    $badgeHtml = $badge !== '' ? '<span class="admin-menu-badge">' . e($badge) . '</span>' : '';
    $iconHtml = $icon ? '<span class="admin-nav-icon">' . $icon . '</span>' : '';
    echo '<a href="' . APP_URL . $href . '" class="admin-nav-item' . $isActive . '">' . $iconHtml . '<span>' . e($label) . '</span>' . $badgeHtml . '</a>';
}

function adminPageStart($title, $active = '') {
    $flash   = getFlash('admin');
    $unread  = getUnreadNotificationCount();
    $role    = $_SESSION['admin_role'] ?? '';
    $adminName    = $_SESSION['admin_name'] ?? 'Administrator';
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
<?php if ($flash): ?>
<div id="adminToast" class="admin-toast toast-<?= e($flash['type']) ?>" role="alert" aria-live="polite">
  <span class="toast-icon"><?= $flash['type'] === 'success' ? '✓' : '✕' ?></span>
  <span><?= e($flash['message']) ?></span>
  <div class="toast-progress"></div>
</div>
<script>
  (function () {
    var t = document.getElementById('adminToast');
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { t.classList.add('toast-visible'); });
    });
    setTimeout(function () {
      t.style.transition = 'left 0.3s ease, opacity 0.3s ease';
      t.style.left = '-400px';
      t.style.opacity = '0';
    }, 3000);
  })();
</script>
<?php endif; ?>
<div class="admin-sidebar-overlay" id="sidebarOverlay"></div>
<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
      <span class="admin-brand-icon">&#x2665;</span>
      <span><strong>Zero Dependency Tracker</strong><small>Admin Portal</small></span>
    </div>
    <nav class="admin-nav">
      <div class="admin-nav-section">Overview</div>
      <?php adminSidebarLink('/admin/dashboard.php', 'Dashboard', 'dashboard', $active, adminIcon('dashboard')); ?>

      <?php if (hasPermission('view_clients')): ?>
        <div class="admin-nav-section">Clients</div>
        <?php adminSidebarLink('/admin/clients/index.php', 'All Clients', 'clients', $active, adminIcon('clients')); ?>
      <?php endif; ?>

      <div class="admin-nav-section">Content</div>
      <?php if (hasPermission('manage_questions')): ?>
        <?php adminSidebarLink('/admin/questions/index.php', 'Questions', 'questions', $active, adminIcon('questions')); ?>
        <?php adminSidebarLink('/admin/sections/index.php', 'Sections', 'sections', $active, adminIcon('sections')); ?>
      <?php endif; ?>
      <?php if (hasPermission('manage_areas')): ?>
        <?php adminSidebarLink('/admin/areas/index.php', 'Problem Areas', 'areas', $active, adminIcon('areas')); ?>
      <?php endif; ?>
      <?php if (hasPermission('manage_mappings')): ?>
        <?php adminSidebarLink('/admin/mappings/index.php', 'Q-Area Mapping', 'mappings', $active, adminIcon('mappings')); ?>
      <?php endif; ?>

      <?php if (hasPermission('view_scores')): ?>
        <div class="admin-nav-section">Audits</div>
        <?php adminSidebarLink('/admin/audits/index.php', 'Audit Windows', 'audits', $active, adminIcon('audits')); ?>
      <?php endif; ?>

      <?php if ($role === 'admin'): ?>
        <div class="admin-nav-section">Team</div>
        <?php adminSidebarLink('/admin/team/index.php', 'Team Members', 'team', $active, adminIcon('team')); ?>
      <?php endif; ?>

      <div class="admin-nav-section">System</div>
      <?php adminSidebarLink('/admin/notifications/index.php', 'Notifications', 'notifications', $active, adminIcon('bell'), $unread ? (string)$unread : ''); ?>
    </nav>
    <div class="admin-user">
      <span class="admin-user-avatar"><?= e($adminInitial) ?></span>
      <span><strong><?= e($adminName) ?></strong><small><?= e(ucwords(str_replace('_', ' ', $role ?: 'admin'))) ?></small></span>
    </div>
  </aside>
  <main class="admin-main">
    <header class="admin-header">
      <div class="admin-header-left">
        <button class="admin-menu-toggle" id="menuToggle" aria-label="Toggle menu">
          <span></span><span></span><span></span>
        </button>
        <div>
          <div class="admin-breadcrumb">Admin / <strong><?= e($title) ?></strong></div>
          <h1><?= e($title) ?></h1>
        </div>
      </div>
      <div class="admin-header-actions">
        <a href="<?= APP_URL ?>/admin/notifications/index.php" class="admin-bell" aria-label="Notifications"><?= adminIcon('bell', 22) ?><?= $unread ? '<span>' . (int)$unread . '</span>' : '' ?></a>
        <form method="POST" action="<?= APP_URL ?>/admin/logout.php" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <button type="submit" class="admin-logout" style="border:0;background:transparent;cursor:pointer;font:inherit;">Logout</button>
        </form>
      </div>
    </header>
    <div class="admin-content">
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

/**
 * Insert a team-category notification (only fires when acting user is a team_member).
 */
function teamNotify(string $type, string $message, ?int $relatedUserId = null, ?int $relatedAuditId = null): void {
    if (($_SESSION['admin_role'] ?? '') !== 'team_member') return;
    $actorId = (int)($_SESSION['admin_id'] ?? 0);
    $pdo = getDB();
    $pdo->prepare("INSERT INTO admin_notifications (type, category, message, related_user_id, related_audit_session_id, related_admin_id)
                   VALUES (?, 'team', ?, ?, ?, ?)")
        ->execute([$type, $message, $relatedUserId, $relatedAuditId, $actorId]);
}

function adminCanAccessClient($clientId) {
    if (($_SESSION['admin_role'] ?? '') === 'admin') return true;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT 1 FROM client_assignments WHERE client_id = ? AND team_member_id = ?");
    $stmt->execute([$clientId, $_SESSION['admin_id']]);
    return (bool)$stmt->fetchColumn();
}
