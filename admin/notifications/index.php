<?php
require_once __DIR__ . '/../_bootstrap.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'mark_all') {
        $pdo->exec("UPDATE admin_notifications SET is_read = 1");
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
    }
    flash('admin', 'Notification updated.', 'success');
    redirect(APP_URL . '/admin/notifications/index.php');
}

$items = $pdo->query("
    SELECT an.*, u.name client_name, cf.suggested_area_id, pa.area_name suggested_area_name
    FROM admin_notifications an
    LEFT JOIN users u ON u.id=an.related_user_id
    LEFT JOIN client_feedback cf ON cf.id=an.related_feedback_id
    LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id
    ORDER BY an.is_read ASC, an.created_at DESC
")->fetchAll();
$unreadCount = 0;
foreach ($items as $item) {
    if (!$item->is_read) $unreadCount++;
}

function notificationTypeLabel($type) {
    return $type === 'perfect_score' ? 'Perfect Score' : 'Area Feedback';
}

function notificationActionLabel($type) {
    return $type === 'perfect_score' ? 'View Report' : 'View Feedback';
}

adminPageStart('Notifications', 'notifications');
?>

<div class="admin-page-action">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
    <input type="hidden" name="action" value="mark_all">
    <button class="admin-action-btn">✓ Mark All Read</button>
  </form>
</div>

<section class="notifications-card">
  <div class="notifications-title">🔔 Notifications (<?= (int)$unreadCount ?> unread)</div>
  <?php if (!$items): ?>
    <div class="notification-empty">No notifications yet.</div>
  <?php endif; ?>
  <?php foreach ($items as $n): ?>
    <?php
      $isFeedback = $n->type === 'area_feedback';
      $targetUrl = $n->related_user_id
          ? APP_URL . '/admin/clients/view.php?id=' . (int)$n->related_user_id . ($n->related_feedback_id ? '#feedback-' . (int)$n->related_feedback_id : '')
          : '#';
    ?>
    <article class="notification-row <?= $n->is_read ? 'is-read' : 'is-unread' ?>" id="notification-<?= (int)$n->id ?>">
      <span class="notification-dot <?= $n->is_read ? 'read' : ($isFeedback ? 'warning' : 'success') ?>"></span>
      <div class="notification-main">
        <span class="notification-type <?= $isFeedback ? 'warning' : 'success' ?>"><?= $isFeedback ? '💬' : '🏆' ?> <?= e(notificationTypeLabel($n->type)) ?></span>
        <p><?= e($n->message) ?></p>
        <small><?= e(date('d M Y \a\t g:i A', strtotime($n->created_at))) ?><?= $n->is_read ? ' · Read' : '' ?></small>
      </div>
      <div class="notification-actions">
        <?php if ($n->related_user_id): ?>
          <a class="admin-row-btn outline" href="<?= e($targetUrl) ?>"><?= e(notificationActionLabel($n->type)) ?></a>
          <?php if ($isFeedback && hasPermission('edit_clients')): ?>
            <a class="admin-row-btn" href="<?= e(APP_URL . '/admin/clients/edit.php?id=' . (int)$n->related_user_id) ?>"><?= $n->suggested_area_name ? 'Change Area to ' . e($n->suggested_area_name) : 'Change Client Area' ?></a>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (!$n->is_read): ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
            <input type="hidden" name="id" value="<?= (int)$n->id ?>">
            <button class="admin-row-btn">Mark Read</button>
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<?php adminPageEnd(); ?>
