<?php
require_once __DIR__ . '/../_bootstrap.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action   = $_POST['action'] ?? '';
    $category = $_POST['category'] ?? '';
    if ($action === 'mark_all' && in_array($category, ['client', 'team', 'all'])) {
        $where = $category === 'all' ? '' : "WHERE category = " . $pdo->quote($category);
        $pdo->exec("UPDATE admin_notifications SET is_read = 1 $where");
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
    }
    flash('admin', 'Notification updated.', 'success');
    $tab = $_POST['tab'] ?? 'client';
    redirect(APP_URL . '/admin/notifications/index.php?tab=' . urlencode($tab));
}

$activeTab = in_array($_GET['tab'] ?? '', ['client', 'team']) ? $_GET['tab'] : 'client';

$clientItems = $pdo->query("
    SELECT an.*, u.name client_name, cf.suggested_area_id, pa.area_name suggested_area_name
    FROM admin_notifications an
    LEFT JOIN users u ON u.id = an.related_user_id
    LEFT JOIN client_feedback cf ON cf.id = an.related_feedback_id
    LEFT JOIN problem_areas pa ON pa.id = cf.suggested_area_id
    WHERE an.category = 'client'
    ORDER BY an.is_read ASC, an.created_at DESC
")->fetchAll();

$teamItems = $pdo->query("
    SELECT an.*, au.name actor_name, u.name client_name
    FROM admin_notifications an
    LEFT JOIN admin_users au ON au.id = an.related_admin_id
    LEFT JOIN users u ON u.id = an.related_user_id
    WHERE an.category = 'team'
    ORDER BY an.is_read ASC, an.created_at DESC
")->fetchAll();

$clientUnread = count(array_filter($clientItems, fn($n) => !$n->is_read));
$teamUnread   = count(array_filter($teamItems,   fn($n) => !$n->is_read));

function notifClientLabel($type) {
    return ['perfect_score'=>'Perfect Score','area_feedback'=>'Area Feedback','audit_completed'=>'Audit Completed','new_registration'=>'New Registration'][$type] ?? ucwords(str_replace('_',' ',$type));
}
function notifTeamLabel($type) {
    return ['team_created'=>'Member Created','team_updated'=>'Member Updated','team_deactivated'=>'Member Deactivated','team_feedback_saved'=>'Feedback Saved','team_feedback_reviewed'=>'Feedback Reviewed','team_area_changed'=>'Area Changed','team_client_assigned'=>'Client Assigned'][$type] ?? ucwords(str_replace('_',' ',$type));
}

adminPageStart('Notifications', 'notifications');
?>
<style>
.notif-tabs { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:1.5rem; }
.notif-tab  { padding:.65rem 1.4rem; font-size:.88rem; font-weight:600; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; transition:color .15s, border-color .15s; }
.notif-tab:hover { color:#0d9488; }
.notif-tab.active { color:#0d9488; border-bottom-color:#0d9488; }
.notif-tab.team.active { color:#7c3aed; border-bottom-color:#7c3aed; }
.notif-badge { display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px; padding:0 5px; border-radius:9999px; font-size:.7rem; font-weight:700; background:#0d9488; color:#fff; }
.notif-badge.team { background:#7c3aed; }

.notif-section { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
.notif-section-head { padding:.875rem 1.25rem; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; }
.notif-section-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; }

.notif-row { display:flex; align-items:flex-start; gap:1rem; padding:.9rem 1.25rem; border-bottom:1px solid #f9fafb; transition:background .12s; }
.notif-row:last-child { border-bottom:none; }
.notif-row.is-unread { background:#fafffe; }
.notif-row.is-read   { opacity:.72; }
.notif-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:.4rem; }
.notif-dot.unread-client { background:#0d9488; }
.notif-dot.unread-team   { background:#7c3aed; }
.notif-dot.read          { background:#d1d5db; }
.notif-body { flex:1; min-width:0; }
.notif-label { display:inline-block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:.1rem .5rem; border-radius:4px; margin-bottom:.3rem; }
.notif-label.client { background:#ecfdf5; color:#065f46; }
.notif-label.team   { background:#f5f3ff; color:#5b21b6; }
.notif-label.warn   { background:#fef9c3; color:#854d0e; }
.notif-msg  { font-size:.875rem; color:#374151; margin-bottom:.25rem; line-height:1.45; }
.notif-meta { font-size:.75rem; color:#9ca3af; }
.notif-actions { display:flex; gap:.4rem; flex-shrink:0; flex-wrap:wrap; align-items:flex-start; }
.notif-empty { padding:2.5rem; text-align:center; color:#9ca3af; font-size:.875rem; }
</style>

<div class="notif-tabs">
  <a href="?tab=client" class="notif-tab <?= $activeTab === 'client' ? 'active' : '' ?>">
    Client Notifications
    <?php if ($clientUnread): ?><span class="notif-badge"><?= $clientUnread ?></span><?php endif; ?>
  </a>
  <a href="?tab=team" class="notif-tab team <?= $activeTab === 'team' ? 'active' : '' ?>">
    Team Notifications
    <?php if ($teamUnread): ?><span class="notif-badge team"><?= $teamUnread ?></span><?php endif; ?>
  </a>
</div>

<?php if ($activeTab === 'client'): ?>
<div class="notif-section">
  <div class="notif-section-head">
    <span class="notif-section-title">Client Notifications &mdash; <?= $clientUnread ?> unread</span>
    <?php if ($clientItems): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
      <input type="hidden" name="action" value="mark_all">
      <input type="hidden" name="category" value="client">
      <input type="hidden" name="tab" value="client">
      <button class="admin-action-btn btn-sm">Mark All Read</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (!$clientItems): ?>
    <div class="notif-empty">No client notifications yet.</div>
  <?php endif; ?>
  <?php foreach ($clientItems as $n):
    $isFeedback = $n->type === 'area_feedback';
    $isWarn     = $isFeedback;
    $targetUrl  = $n->related_user_id ? APP_URL . '/admin/clients/view.php?id=' . (int)$n->related_user_id . ($n->related_feedback_id ? '#feedback-' . (int)$n->related_feedback_id : '') : '#';
  ?>
  <div class="notif-row <?= $n->is_read ? 'is-read' : 'is-unread' ?>">
    <div class="notif-dot <?= $n->is_read ? 'read' : 'unread-client' ?>"></div>
    <div class="notif-body">
      <span class="notif-label <?= $isWarn ? 'warn' : 'client' ?>"><?= e(notifClientLabel($n->type)) ?></span>
      <div class="notif-msg"><?= e($n->message) ?><?= $n->client_name ? ' &mdash; <strong>' . e($n->client_name) . '</strong>' : '' ?></div>
      <div class="notif-meta"><?= e(date('d M Y \a\t g:i A', strtotime($n->created_at))) ?><?= $n->is_read ? ' · Read' : '' ?></div>
    </div>
    <div class="notif-actions">
      <?php if ($n->related_user_id): ?>
        <a class="admin-row-btn outline" href="<?= e($targetUrl) ?>">View Client</a>
      <?php endif; ?>
      <?php if (!$n->is_read): ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="id" value="<?= (int)$n->id ?>">
          <input type="hidden" name="tab" value="client">
          <button class="admin-row-btn">Mark Read</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<div class="notif-section">
  <div class="notif-section-head">
    <span class="notif-section-title">Team Notifications &mdash; <?= $teamUnread ?> unread</span>
    <?php if ($teamItems): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
      <input type="hidden" name="action" value="mark_all">
      <input type="hidden" name="category" value="team">
      <input type="hidden" name="tab" value="team">
      <button class="admin-action-btn btn-sm">Mark All Read</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (!$teamItems): ?>
    <div class="notif-empty">No team notifications yet.</div>
  <?php endif; ?>
  <?php foreach ($teamItems as $n): ?>
  <div class="notif-row <?= $n->is_read ? 'is-read' : 'is-unread' ?>">
    <div class="notif-dot <?= $n->is_read ? 'read' : 'unread-team' ?>"></div>
    <div class="notif-body">
      <span class="notif-label team"><?= e(notifTeamLabel($n->type)) ?></span>
      <div class="notif-msg"><?= e($n->message) ?></div>
      <div class="notif-meta">
        <?php if ($n->actor_name): ?>By <strong><?= e($n->actor_name) ?></strong> &middot; <?php endif; ?>
        <?= e(date('d M Y \a\t g:i A', strtotime($n->created_at))) ?><?= $n->is_read ? ' · Read' : '' ?>
      </div>
    </div>
    <div class="notif-actions">
      <?php if ($n->related_user_id): ?>
        <a class="admin-row-btn outline" href="<?= APP_URL . '/admin/clients/view.php?id=' . (int)$n->related_user_id ?>">View Client</a>
      <?php endif; ?>
      <?php if (!$n->is_read): ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="id" value="<?= (int)$n->id ?>">
          <input type="hidden" name="tab" value="team">
          <button class="admin-row-btn">Mark Read</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php adminPageEnd(); ?>
