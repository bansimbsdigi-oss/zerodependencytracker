<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_areas');
$pdo = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['area_name'] ?? '');
    $order = (int)($_POST['display_order'] ?? 0);
    if (strlen($name) < 2) $errors[] = 'Area name is required.';
    if (!$errors) {
        $pdo->prepare("INSERT INTO problem_areas (area_name, display_order, is_active) VALUES (?, ?, 1)")->execute([$name, $order]);
        flash('admin', 'Problem area created.', 'success');
        redirect(APP_URL . '/admin/areas/index.php');
    }
}

adminPageStart('Add Problem Area', 'areas');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
    <div class="form-group"><label class="form-label">Area Name</label><input class="form-control" name="area_name" value="<?= e($_POST['area_name'] ?? '') ?>" required></div>
    <div class="form-group"><label class="form-label">Display Order</label><input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? '0') ?>"></div>
    <button class="btn btn-primary">Save Area</button>
    <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/areas/index.php">Cancel</a>
  </form>
</div></div>
<?php adminPageEnd(); ?>
