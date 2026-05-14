<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $text = trim($_POST['question_text'] ?? '');
    $type = $_POST['question_type'] ?? 'mcq';
    $ratingMin = (int)($_POST['rating_min'] ?? 1);
    $ratingMax = (int)($_POST['rating_max'] ?? 5);
    if (strlen($text) < 5) $errors[] = 'Question text must be at least 5 characters.';
    if (!in_array($type, ['mcq','text','multi_select','rating'], true)) $errors[] = 'Invalid question type.';
    if ($type === 'rating' && $ratingMax <= $ratingMin) $errors[] = 'Rating max must be greater than min.';
    if (!$errors) {
        $pdo->prepare("INSERT INTO questions (question_text, question_type, rating_min, rating_max, flag) VALUES (?, ?, ?, ?, 1)")->execute([$text, $type, $ratingMin, $ratingMax]);
        $id = $pdo->lastInsertId();
        flash('admin', 'Question created.', 'success');
        redirect(in_array($type, ['mcq','multi_select'], true) ? APP_URL . '/admin/options/index.php?question_id=' . $id : APP_URL . '/admin/questions/index.php');
    }
}

adminPageStart('Add Question', 'questions');
$selectedType = $_POST['question_type'] ?? 'mcq';
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST" id="question-form">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

  <div class="form-group">
    <label class="form-label">Question Text</label>
    <textarea class="form-control" name="question_text" rows="3" required><?= e($_POST['question_text'] ?? '') ?></textarea>
  </div>

  <div class="form-group">
    <label class="form-label">Question Type</label>
    <select class="form-control" name="question_type" id="question_type">
      <option value="mcq"           <?= $selectedType === 'mcq'           ? 'selected' : '' ?>>Single Select — Radio buttons (one answer only)</option>
      <option value="multi_select"  <?= $selectedType === 'multi_select'  ? 'selected' : '' ?>>Multi Select — Checkboxes (multiple answers allowed)</option>
      <option value="text"          <?= $selectedType === 'text'          ? 'selected' : '' ?>>Long Text — Open-ended written answer</option>
      <option value="rating"        <?= $selectedType === 'rating'        ? 'selected' : '' ?>>Rating Scale — Numeric score (e.g. 1 to 5)</option>
    </select>
  </div>

  <div id="type-hint" style="margin-bottom:1rem;padding:.75rem 1rem;border-radius:8px;background:#f0fdfa;border:1px solid #99f6e4;color:#0f766e;font-size:.9rem;"></div>

  <div id="rating-fields" style="display:none;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group"><label class="form-label">Rating Min</label><input class="form-control" type="number" name="rating_min" value="<?= e($_POST['rating_min'] ?? '1') ?>"></div>
      <div class="form-group"><label class="form-label">Rating Max</label><input class="form-control" type="number" name="rating_max" value="<?= e($_POST['rating_max'] ?? '5') ?>"></div>
    </div>
  </div>

  <button class="btn btn-primary">Save Question</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/questions/index.php">Cancel</a>
</form>
</div></div>

<script>
const typeHints = {
  mcq:          'Client sees a list of options with radio buttons — they must pick exactly one answer.',
  multi_select: 'Client sees a list of options with checkboxes — they can pick one or more answers.',
  text:         'Client sees a text area — they type a free-form written answer. This type does not contribute to the score.',
  rating:       'Client sees numbered buttons from Min to Max — they tap one number as their score.',
};
const sel       = document.getElementById('question_type');
const hint      = document.getElementById('type-hint');
const ratingDiv = document.getElementById('rating-fields');

function updateUI() {
  hint.textContent = typeHints[sel.value] || '';
  ratingDiv.style.display = sel.value === 'rating' ? '' : 'none';
}
sel.addEventListener('change', updateUI);
updateUI();
</script>
<?php adminPageEnd(); ?>
