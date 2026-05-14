<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();
$questionId = (int)($_GET['question_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM questions WHERE sno = ? AND question_type IN ('mcq','multi_select')");
$stmt->execute([$questionId]);
$question = $stmt->fetch();
if (!$question) redirect(APP_URL . '/admin/questions/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM options WHERE id = ? AND question_id = ?")->execute([$id, $questionId]);
    flash('admin', 'Option deleted.', 'success');
    redirect(APP_URL . '/admin/options/index.php?question_id=' . $questionId);
}

$opts = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY display_order, id");
$opts->execute([$questionId]);
$options = $opts->fetchAll();
adminPageStart('Options', 'questions');
?>
<div class="card"><div class="card-body"><strong><?= e($question->question_text) ?></strong></div></div>
<div style="display:flex;justify-content:space-between;align-items:center;margin:1rem 0;"><a class="btn btn-secondary" href="<?= APP_URL ?>/admin/questions/index.php">Back to Questions</a><a class="btn btn-primary" href="<?= APP_URL ?>/admin/options/create.php?question_id=<?= $questionId ?>">Add Option</a></div>
<table class="table"><thead><tr><th>Order</th><th>Option</th><th>Points</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($options as $opt): ?>
  <tr><td><?= (int)$opt->display_order ?></td><td><?= e($opt->option_text) ?></td><td><?= (int)$opt->points ?></td><td style="display:flex;gap:.5rem;"><a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/options/edit.php?id=<?= $opt->id ?>&question_id=<?= $questionId ?>">Edit</a><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><input type="hidden" name="id" value="<?= $opt->id ?>"><button class="btn btn-danger btn-sm btn-delete">Delete</button></form></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php adminPageEnd(); ?>
