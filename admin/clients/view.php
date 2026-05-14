<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('view_clients');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!adminCanAccessClient($id)) redirect(APP_URL . '/admin/clients/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'review_feedback' && hasPermission('edit_clients')) {
        $pdo->prepare("UPDATE client_feedback SET is_reviewed=1 WHERE id=? AND user_id=?")->execute([(int)$_POST['feedback_id'], $id]);
        flash('admin', 'Feedback marked reviewed.', 'success');
        redirect(APP_URL . '/admin/clients/view.php?id=' . $id . '#feedback');
    }
    if ($action === 'change_area' && hasPermission('edit_clients')) {
        $fbId = (int)$_POST['feedback_id'];
        $fbRow = $pdo->prepare("SELECT suggested_area_id FROM client_feedback WHERE id=? AND user_id=? AND suggested_area_id IS NOT NULL");
        $fbRow->execute([$fbId, $id]);
        $fbData = $fbRow->fetch();
        if ($fbData) {
            $pdo->prepare("UPDATE users SET area_id=? WHERE id=?")->execute([(int)$fbData->suggested_area_id, $id]);
            $pdo->prepare("UPDATE client_feedback SET is_reviewed=1 WHERE id=? AND user_id=?")->execute([$fbId, $id]);
            flash('admin', 'Client area updated and feedback marked reviewed.', 'success');
        }
        redirect(APP_URL . '/admin/clients/view.php?id=' . $id . '#feedback');
    }
    redirect(APP_URL . '/admin/clients/view.php?id=' . $id . '#feedback');
}

$stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE u.id=?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) redirect(APP_URL . '/admin/clients/index.php');

$sessions = [];
$responses = [];
if (hasPermission('view_scores')) {
    $stmt = $pdo->prepare("SELECT aus.*, aw.audit_type, aw.audit_month, aw.audit_year, pa.area_name FROM audit_sessions aus JOIN audit_windows aw ON aw.id=aus.audit_window_id LEFT JOIN problem_areas pa ON pa.id=aus.area_id WHERE aus.user_id=? AND aus.status='completed' ORDER BY aus.completed_at DESC");
    $stmt->execute([$id]);
    $sessions = $stmt->fetchAll();
    foreach ($sessions as $s) {
        $r = $pdo->prepare("SELECT ar.*, q.question_text, q.question_type, o.option_text, (SELECT GROUP_CONCAT(o2.option_text SEPARATOR ', ') FROM audit_response_selections ars JOIN options o2 ON o2.id=ars.option_id WHERE ars.audit_response_id=ar.id) multi_answer FROM audit_responses ar JOIN questions q ON q.sno=ar.question_id LEFT JOIN options o ON o.id=ar.option_id WHERE ar.audit_session_id=? ORDER BY ar.id");
        $r->execute([$s->id]);
        $responses[$s->id] = $r->fetchAll();
    }
}
$fb = $pdo->prepare("SELECT cf.*, pa.area_name suggested_area FROM client_feedback cf LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id WHERE cf.user_id=? ORDER BY cf.created_at DESC");
$fb->execute([$id]);
$feedbacks = $fb->fetchAll();

adminPageStart('Client: ' . $client->name, 'clients');
?>
<div class="card"><div class="card-body"><h3><?= e($client->name) ?></h3><p><?= e($client->email) ?> · <?= e($client->mobile) ?></p><p>Area: <strong><?= e($client->area_name ?? '-') ?></strong> · Status: <span class="badge <?= $client->is_graduated ? 'badge-success' : 'badge-gray' ?>"><?= $client->is_graduated ? 'Graduated' : 'Active' ?></span></p><?php if (hasPermission('edit_clients')): ?><a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/clients/edit.php?id=<?= $id ?>">Edit Client</a><?php endif; ?></div></div>
<h3>Audit History</h3>
<?php if (!hasPermission('view_scores')): ?><p class="text-muted">You do not have permission to view scores.</p><?php elseif (!$sessions): ?><p class="text-muted">No completed audits.</p><?php else: foreach ($sessions as $s): $pct = $s->max_score ? round($s->total_score / $s->max_score * 100) : 0; ?>
<details class="card" style="margin-bottom:1rem;"><summary class="card-header" style="cursor:pointer;"><strong><?= e(ucwords(str_replace('_',' ',$s->audit_type))) ?> <?= e($s->audit_month) ?>/<?= e($s->audit_year) ?></strong> · <?= e($s->area_name ?? '-') ?> · <?= $pct ?>%</summary><div class="card-body"><table class="table"><thead><tr><th>Question</th><th>Answer</th><th>Score</th></tr></thead><tbody><?php foreach ($responses[$s->id] ?? [] as $r): ?><tr><td><?= e($r->question_text) ?></td><td><?php if ($r->question_type === 'text') echo e($r->text_response); elseif ($r->question_type === 'rating') echo e($r->numeric_response); elseif ($r->question_type === 'multi_select') echo e($r->multi_answer ?: 'None'); else echo e($r->option_text); ?></td><td><?= (int)$r->points_earned ?> / <?= (int)$r->max_question_points ?></td></tr><?php endforeach; ?></tbody></table></div></details>
<?php endforeach; endif; ?>
<h3 id="feedback">Feedback</h3>
<?php if (!$feedbacks): ?><p class="text-muted">No feedback submitted.</p><?php else: ?><table class="table"><thead><tr><th>Date</th><th>Suggested Area</th><th>Notes</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($feedbacks as $f): ?><tr id="feedback-<?= $f->id ?>"><td><?= e(date('d M Y', strtotime($f->created_at))) ?></td><td><?= e($f->suggested_area ?? '-') ?></td><td><?= e($f->feedback_text ?? '-') ?></td><td><span class="badge <?= $f->is_reviewed ? 'badge-success' : 'badge-warning' ?>"><?= $f->is_reviewed ? 'Reviewed' : 'Pending' ?></span></td><td style="display:flex;gap:.5rem;flex-wrap:wrap;"><?php if (!$f->is_reviewed && hasPermission('edit_clients')): ?><form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><input type="hidden" name="action" value="review_feedback"><input type="hidden" name="feedback_id" value="<?= $f->id ?>"><button class="btn btn-primary btn-sm">Mark Reviewed</button></form><?php if ($f->suggested_area_id): ?><form method="POST" style="display:inline;" onsubmit="return confirm('Change client area to &quot;<?= e(addslashes($f->suggested_area)) ?>&quot;?');"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><input type="hidden" name="action" value="change_area"><input type="hidden" name="feedback_id" value="<?= $f->id ?>"><button class="btn btn-secondary btn-sm">Change Area to <?= e($f->suggested_area) ?></button></form><?php endif; ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php adminPageEnd(); ?>
