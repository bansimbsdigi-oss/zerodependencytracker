<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('view_scores');
$pdo = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE audit_windows SET is_open = 0, closed_at = NOW() WHERE id = ?")->execute([$id]);
    flash('admin', 'Audit window closed.', 'success');
    redirect(APP_URL . '/admin/audits/index.php');
}
$windows = $pdo->query("SELECT aw.*, au.name opened_by_name, COUNT(aus.id) completions FROM audit_windows aw LEFT JOIN admin_users au ON au.id=aw.opened_by LEFT JOIN audit_sessions aus ON aus.audit_window_id=aw.id AND aus.status='completed' GROUP BY aw.id ORDER BY aw.audit_year DESC, aw.audit_month DESC, aw.id DESC")->fetchAll();
adminPageStart('Audit Windows', 'audits');
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;"><a class="btn btn-primary" href="<?= APP_URL ?>/admin/audits/create.php">Open Audit Window</a></div>
<table class="table"><thead><tr><th>Type</th><th>Period</th><th>Status</th><th>Completed</th><th>Opened By</th><th>Action</th></tr></thead><tbody>
<?php foreach ($windows as $w): ?>
<tr><td><?= e(ucwords(str_replace('_',' ',$w->audit_type))) ?></td><td><?= e($w->audit_month) ?>/<?= e($w->audit_year) ?></td><td><span class="badge <?= $w->is_open ? 'badge-success' : 'badge-gray' ?>"><?= $w->is_open ? 'Open' : 'Closed' ?></span></td><td><?= (int)$w->completions ?></td><td><?= e($w->opened_by_name ?? '-') ?></td><td><?php if ($w->is_open): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><input type="hidden" name="id" value="<?= $w->id ?>"><button class="btn btn-secondary btn-sm">Close</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php adminPageEnd(); ?>
