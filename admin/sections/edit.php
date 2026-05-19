<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM question_sections WHERE id = ?");
$stmt->execute([$id]);
$section = $stmt->fetch();
if (!$section) redirect(APP_URL . '/admin/sections/index.php');

$errors = [];
$areas  = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name   = trim($_POST['section_name'] ?? '');
    $areaId = (int)($_POST['area_id'] ?? 0);
    $order  = (int)($_POST['display_order'] ?? 0);

    if (strlen($name) < 2) $errors[] = 'Section name must be at least 2 characters.';
    if ($areaId < 1)       $errors[] = 'Please select a problem area.';

    if (!$errors) {
        $pdo->prepare("UPDATE question_sections SET area_id=?, section_name=?, display_order=? WHERE id=?")->execute([$areaId, $name, $order, $id]);
        // Sync question_area_map for questions in this section
        $pdo->prepare("
            INSERT IGNORE INTO question_area_map (question_id, area_id)
            SELECT q.sno, ? FROM questions q WHERE q.section_id = ?
        ")->execute([$areaId, $id]);
        flash('admin', 'Section saved.', 'success');
        redirect(APP_URL . '/admin/sections/index.php');
    }
}

adminPageStart('Edit Section', 'sections');
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
        <?php $selArea = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int)($_POST['area_id'] ?? 0) : (int)$section->area_id; ?>
        <option value="<?= $area->id ?>" <?= $selArea === (int)$area->id ? 'selected' : '' ?>><?= e($area->area_name) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label class="form-label">Section Name <span style="color:#dc2626">*</span></label>
    <input class="form-control" name="section_name" value="<?= e($_POST['section_name'] ?? $section->section_name) ?>" required>
  </div>

  <div class="form-group">
    <label class="form-label">Display Order</label>
    <input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? $section->display_order) ?>">
  </div>

  <button class="btn btn-primary">Save Changes</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/sections/index.php">Cancel</a>
</form>
</div></div>
<?php adminPageEnd(); ?>
