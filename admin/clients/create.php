<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/whatsapp.php';
requirePermission('register_clients');
$pdo = getDB();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');
    $areaId = (int)($_POST['area_id'] ?? 0);
    if (strlen($name) < 2) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!preg_match('/^\d{10,15}$/', $mobile)) $errors[] = 'Mobile must be 10 to 15 digits.';
    if ($areaId < 1) $errors[] = 'Select a problem area.';
    if (!$errors) {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email=? OR mobile=?");
        $dup->execute([$email, $mobile]);
        if ($dup->fetch()) $errors[] = 'Email or mobile already exists.';
    }
    if (!$errors) {
        $password = generateTemporaryPassword();
        $pdo->prepare("INSERT INTO users (name,email,mobile,password,area_id) VALUES (?,?,?,?,?)")->execute([$name,$email,$mobile,password_hash($password, PASSWORD_BCRYPT),$areaId]);
        sendWhatsAppMessage($mobile, APP_NAME . ": Your account password is $password");
        flash('admin', 'Client registered and password sent.', 'success');
        redirect(APP_URL . '/admin/clients/index.php');
    }
}
$areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
adminPageStart('Register Client', 'clients');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><div class="form-group"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($_POST['name'] ?? '') ?>" required></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div><div class="form-group"><label class="form-label">WhatsApp Mobile</label><input class="form-control" name="mobile" value="<?= e($_POST['mobile'] ?? '') ?>" required></div></div><div class="form-group"><label class="form-label">Area</label><select class="form-control" name="area_id"><?php foreach ($areas as $a): ?><option value="<?= $a->id ?>"><?= e($a->area_name) ?></option><?php endforeach; ?></select></div><button class="btn btn-primary">Register Client</button> <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/clients/index.php">Cancel</a></form></div></div>
<?php adminPageEnd(); ?>
