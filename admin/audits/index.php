<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/audit_reminders.php';
requirePermission('view_scores');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'send_reminders') {
        $result = sendAuditReminders($pdo);
        flash('admin', $result['message'], $result['success'] ? 'success' : 'danger');
        redirect(APP_URL . '/admin/audits/index.php');
    }
    if ($action === 'close') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE audit_windows SET is_open = 0, closed_at = NOW() WHERE id = ?")->execute([$id]);
        flash('admin', 'Audit window closed.', 'success');
        redirect(APP_URL . '/admin/audits/index.php');
    }
    if ($action === 'reopen') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE audit_windows SET is_open = 1, closed_at = NULL WHERE id = ?")->execute([$id]);
        flash('admin', 'Audit window reopened.', 'success');
        redirect(APP_URL . '/admin/audits/index.php');
    }
}

// Auto-close windows whose end_date has passed
$pdo->exec("UPDATE audit_windows SET is_open = 0, closed_at = NOW() WHERE is_open = 1 AND end_date IS NOT NULL AND end_date < CURDATE()");

$windows = $pdo->query("
    SELECT aw.*, au.name opened_by_name, COUNT(aus.id) completions
    FROM audit_windows aw
    LEFT JOIN admin_users au ON au.id = aw.opened_by
    LEFT JOIN audit_sessions aus ON aus.audit_window_id = aw.id AND aus.status = 'completed'
    GROUP BY aw.id
    ORDER BY aw.audit_year DESC, aw.audit_month DESC, aw.id DESC
")->fetchAll();

$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

adminPageStart('Audit Windows', 'audits');
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;gap:.75rem;flex-wrap:wrap;">
  <form method="POST" style="margin:0;">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
    <input type="hidden" name="action" value="send_reminders">
    <button type="submit" class="btn btn-secondary">Send Audit Reminders</button>
  </form>
  <a class="btn btn-primary" href="<?= APP_URL ?>/admin/audits/create.php">Open Audit Window</a>
</div>

<?php if (empty($windows)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#6b7280;padding:3rem;">
    No audit windows yet. <a href="<?= APP_URL ?>/admin/audits/create.php">Open the first one</a>.
  </div></div>
<?php else: ?>
<table class="table">
  <thead>
    <tr>
      <th>Type</th>
      <th>Period</th>
      <th>Start Date</th>
      <th>End Date</th>
      <th>Status</th>
      <th>Completed</th>
      <th>Opened By</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($windows as $w):
    $today      = date('Y-m-d');
    $isExpired  = $w->end_date   && $w->end_date   < $today;
    $notStarted = $w->start_date && $w->start_date > $today;
    $daysLeft   = $w->end_date   ? (int)ceil((strtotime($w->end_date) - strtotime($today)) / 86400) : null;
  ?>
  <tr>
    <td><?= e(ucwords(str_replace('_', ' ', $w->audit_type))) ?></td>
    <td><?= e($months[(int)$w->audit_month] ?? $w->audit_month) ?> <?= e($w->audit_year) ?></td>

    <td>
      <?php if ($w->start_date): ?>
        <?= e(date('d M Y', strtotime($w->start_date))) ?>
        <?php if ($notStarted): ?>
          <span style="display:block;font-size:.75rem;color:#d97706;font-weight:600;">Starts in <?= abs($daysLeft !== null ? (int)ceil((strtotime($w->start_date) - strtotime($today)) / 86400) : 0) ?> day(s)</span>
        <?php endif; ?>
      <?php else: ?>
        <span style="color:#9ca3af;">—</span>
      <?php endif; ?>
    </td>

    <td>
      <?php if ($w->end_date): ?>
        <?= e(date('d M Y', strtotime($w->end_date))) ?>
        <?php if ($w->is_open && !$isExpired && $daysLeft !== null): ?>
          <span style="display:block;font-size:.75rem;color:<?= $daysLeft <= 2 ? '#dc2626' : '#6b7280' ?>;font-weight:600;">
            <?= $daysLeft === 0 ? 'Closes today' : ($daysLeft < 0 ? 'Expired' : $daysLeft . ' day(s) left') ?>
          </span>
        <?php elseif ($isExpired): ?>
          <span style="display:block;font-size:.75rem;color:#dc2626;font-weight:600;">Expired</span>
        <?php endif; ?>
      <?php else: ?>
        <span style="color:#9ca3af;">—</span>
      <?php endif; ?>
    </td>

    <td>
      <?php if ($w->is_open && $notStarted): ?>
        <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">Scheduled</span>
      <?php elseif ($w->is_open): ?>
        <span class="badge badge-success">Open</span>
      <?php else: ?>
        <span class="badge badge-gray">Closed</span>
      <?php endif; ?>
    </td>

    <td><?= (int)$w->completions ?></td>
    <td><?= e($w->opened_by_name ?? '—') ?></td>

    <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <?php if ($w->is_open): ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="action" value="close">
          <input type="hidden" name="id" value="<?= $w->id ?>">
          <button class="btn btn-secondary btn-sm">Close</button>
        </form>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="action" value="reopen">
          <input type="hidden" name="id" value="<?= $w->id ?>">
          <button class="btn btn-secondary btn-sm">Reopen</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php adminPageEnd(); ?>
