<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('edit_clients');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!adminCanAccessClient($id)) redirect(APP_URL . '/admin/clients/index.php');
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) redirect(APP_URL . '/admin/clients/index.php');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');
    $areaId = (int)($_POST['area_id'] ?? 0);
    $graduated = isset($_POST['is_graduated']) ? 1 : 0;
    if (strlen($name) < 2) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!preg_match('/^\d{10,15}$/', $mobile)) $errors[] = 'Mobile must be 10 to 15 digits.';
    if ($areaId < 1) $errors[] = 'Select a problem area.';
    if (!$errors) {
        $dup = $pdo->prepare("SELECT id FROM users WHERE (email=? OR mobile=?) AND id<>?");
        $dup->execute([$email, $mobile, $id]);
        if ($dup->fetch()) $errors[] = 'Email or mobile already exists.';
    }
    if (!$errors) {
        $pdo->prepare("UPDATE users SET name=?, email=?, mobile=?, area_id=?, is_graduated=? WHERE id=?")->execute([$name,$email,$mobile,$areaId,$graduated,$id]);
        flash('admin', 'Client saved.', 'success');
        redirect(APP_URL . '/admin/clients/view.php?id=' . $id);
    }
}
$areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
adminPageStart('Edit Client', 'clients');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><div class="form-group"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($_POST['name'] ?? $client->name) ?>" required></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? $client->email) ?>" required></div><div class="form-group"><label class="form-label">WhatsApp Mobile</label><input class="form-control" name="mobile" value="<?= e($_POST['mobile'] ?? $client->mobile) ?>" required></div></div><div class="form-group"><label class="form-label">Area</label><select class="form-control" name="area_id"><?php foreach ($areas as $a): ?><option value="<?= $a->id ?>" <?= (($_POST['area_id'] ?? $client->area_id) == $a->id) ? 'selected' : '' ?>><?= e($a->area_name) ?></option><?php endforeach; ?></select></div><label style="display:flex;gap:.5rem;margin-bottom:1rem;"><input type="checkbox" name="is_graduated" <?= ($_POST ? isset($_POST['is_graduated']) : $client->is_graduated) ? 'checked' : '' ?>> Graduated</label><button class="btn btn-primary">Save Changes</button> <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/clients/view.php?id=<?= $id ?>">Cancel</a></form></div></div>
<?php adminPageEnd(); ?>
