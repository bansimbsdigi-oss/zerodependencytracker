<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$questionId = (int)($_GET['question_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM options WHERE id = ? AND question_id = ?");
$stmt->execute([$id, $questionId]);
$opt = $stmt->fetch();
if (!$opt) redirect(APP_URL . '/admin/questions/index.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $text = trim($_POST['option_text'] ?? '');
    $points = (int)($_POST['points'] ?? 0);
    $order = (int)($_POST['display_order'] ?? 0);
    if ($text === '') $errors[] = 'Option text is required.';
    if (!$errors) {
        $pdo->prepare("UPDATE options SET option_text=?, points=?, display_order=? WHERE id=?")->execute([$text, $points, $order, $id]);
        flash('admin', 'Option saved.', 'success');
        redirect(APP_URL . '/admin/options/index.php?question_id=' . $questionId);
    }
}

adminPageStart('Edit Option', 'questions');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><div class="form-group"><label class="form-label">Option Text</label><input class="form-control" name="option_text" value="<?= e($_POST['option_text'] ?? $opt->option_text) ?>" required></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="form-group"><label class="form-label">Points</label><input class="form-control" type="number" name="points" value="<?= e($_POST['points'] ?? $opt->points) ?>"></div><div class="form-group"><label class="form-label">Display Order</label><input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? $opt->display_order) ?>"></div></div><button class="btn btn-primary">Save Changes</button> <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/options/index.php?question_id=<?= $questionId ?>">Cancel</a></form></div></div>
<?php adminPageEnd(); ?>
