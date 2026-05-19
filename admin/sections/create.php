<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();
$errors = [];
$areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['section_name'] ?? '');
    $areaId  = (int)($_POST['area_id'] ?? 0);
    $order   = (int)($_POST['display_order'] ?? 0);

    if (strlen($name) < 2)  $errors[] = 'Section name must be at least 2 characters.';
    if ($areaId < 1)        $errors[] = 'Please select a problem area.';

    if (!$errors) {
        $pdo->prepare("INSERT INTO question_sections (area_id, section_name, display_order) VALUES (?, ?, ?)")->execute([$areaId, $name, $order]);
        flash('admin', 'Section created.', 'success');
        redirect(APP_URL . '/admin/sections/index.php');
    }
}

adminPageStart('Add Section', 'sections');
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

  <div class="form-group">
    <label class="form-label">Problem Area <span style="color:#dc2626">*</span></label>
    <select class="form-control" name="area_id" required>
      <option value="">— Select Area —</option>
      <?php foreach ($areas as $area): ?>
        <option value="<?= $area->id ?>" <?= (($_POST['area_id'] ?? '') == $area->id) ? 'selected' : '' ?>><?= e($area->area_name) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label class="form-label">Section Name <span style="color:#dc2626">*</span></label>
    <input class="form-control" name="section_name" value="<?= e($_POST['section_name'] ?? '') ?>" placeholder="e.g. Mobility" required>
  </div>

  <div class="form-group">
    <label class="form-label">Display Order</label>
    <input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? '0') ?>">
    <span style="font-size:.8rem;color:#6b7280;">Lower number appears first within the same area.</span>
  </div>

  <button class="btn btn-primary">Save Section</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/sections/index.php">Cancel</a>
</form>
</div></div>
<?php adminPageEnd(); ?>
