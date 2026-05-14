<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM questions WHERE sno = ?");
$stmt->execute([$id]);
$q = $stmt->fetch();
if (!$q) redirect(APP_URL . '/admin/questions/index.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $text = trim($_POST['question_text'] ?? '');
    $ratingMin = (int)($_POST['rating_min'] ?? $q->rating_min);
    $ratingMax = (int)($_POST['rating_max'] ?? $q->rating_max);
    $flag = isset($_POST['flag']) ? 1 : 0;
    if (strlen($text) < 5) $errors[] = 'Question text must be at least 5 characters.';
    if ($q->question_type === 'rating' && $ratingMax <= $ratingMin) $errors[] = 'Rating max must be greater than min.';
    if (!$errors) {
        $pdo->prepare("UPDATE questions SET question_text=?, rating_min=?, rating_max=?, flag=? WHERE sno=?")->execute([$text, $ratingMin, $ratingMax, $flag, $id]);
        flash('admin', 'Question saved.', 'success');
        redirect(APP_URL . '/admin/questions/index.php');
    }
}

adminPageStart('Edit Question', 'questions');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
  <?php
  $typeNames = [
      'mcq'          => 'Single Select (Radio buttons)',
      'multi_select' => 'Multi Select (Checkboxes)',
      'text'         => 'Long Text (Open-ended)',
      'rating'       => 'Rating Scale (Numeric)',
  ];
  ?>
  <p class="text-muted" style="margin-bottom:1.25rem;">
    Question Type: <strong><?= e($typeNames[$q->question_type] ?? $q->question_type) ?></strong>
    <small style="color:#64748b;"> — type cannot be changed after creation</small>
  </p>
  <div class="form-group"><label class="form-label">Question Text</label><textarea class="form-control" name="question_text" rows="3" required><?= e($_POST['question_text'] ?? $q->question_text) ?></textarea></div>
  <?php if ($q->question_type === 'rating'): ?><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="form-group"><label class="form-label">Rating Min</label><input class="form-control" type="number" name="rating_min" value="<?= e($_POST['rating_min'] ?? $q->rating_min) ?>"></div><div class="form-group"><label class="form-label">Rating Max</label><input class="form-control" type="number" name="rating_max" value="<?= e($_POST['rating_max'] ?? $q->rating_max) ?>"></div></div><?php endif; ?>
  <label style="display:flex;gap:.5rem;margin-bottom:1rem;"><input type="checkbox" name="flag" <?= ($_POST ? isset($_POST['flag']) : $q->flag) ? 'checked' : '' ?>> Visible in audits</label>
  <button class="btn btn-primary">Save Changes</button>
  <?php if (in_array($q->question_type, ['mcq','multi_select'], true)): ?><a class="btn btn-secondary" href="<?= APP_URL ?>/admin/options/index.php?question_id=<?= $q->sno ?>">Manage Options</a><?php endif; ?>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/questions/index.php">Cancel</a>
</form>
</div></div>
<?php adminPageEnd(); ?>
