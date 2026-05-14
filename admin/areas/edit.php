<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_areas');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM problem_areas WHERE id = ?");
$stmt->execute([$id]);
$area = $stmt->fetch();
if (!$area) redirect(APP_URL . '/admin/areas/index.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['area_name'] ?? '');
    $order = (int)($_POST['display_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if (strlen($name) < 2) $errors[] = 'Area name is required.';
    if (!$errors) {
        $pdo->prepare("UPDATE problem_areas SET area_name = ?, display_order = ?, is_active = ? WHERE id = ?")->execute([$name, $order, $active, $id]);
        flash('admin', 'Problem area saved.', 'success');
        redirect(APP_URL . '/admin/areas/index.php');
    }
}

adminPageStart('Edit Problem Area', 'areas');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
    <div class="form-group"><label class="form-label">Area Name</label><input class="form-control" name="area_name" value="<?= e($_POST['area_name'] ?? $area->area_name) ?>" required></div>
    <div class="form-group"><label class="form-label">Display Order</label><input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? $area->display_order) ?>"></div>
    <label style="display:flex;gap:.5rem;margin-bottom:1rem;"><input type="checkbox" name="is_active" <?= ($_POST ? isset($_POST['is_active']) : $area->is_active) ? 'checked' : '' ?>> Active</label>
    <button class="btn btn-primary">Save Changes</button>
    <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/areas/index.php">Cancel</a>
  </form>
</div></div>
<?php adminPageEnd(); ?>
