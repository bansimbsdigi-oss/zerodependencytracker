<?php
require_once __DIR__ . '/../_bootstrap.php';
if (($_SESSION['admin_role'] ?? '') !== 'admin') redirect(APP_URL . '/admin/dashboard.php');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id=? AND role='team_member'");
$stmt->execute([$id]);
$member = $stmt->fetch();
if (!$member) redirect(APP_URL . '/admin/team/index.php');
$allPerms = ['view_clients','edit_clients','register_clients','view_scores','manage_questions','manage_mappings','manage_areas'];
$stmt = $pdo->prepare("SELECT permission FROM admin_permissions WHERE admin_user_id=?");
$stmt->execute([$id]);
$existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $perms = $_POST['permissions'] ?? [];
    if (strlen($name) < 2) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($password && strlen($password) < 8) $errors[] = 'New password must be at least 8 characters.';
    if (in_array('edit_clients', $perms, true) && !in_array('view_clients', $perms, true)) $perms[] = 'view_clients';
    if (!$errors) {
        $pdo->prepare("UPDATE admin_users SET name=?, email=?, is_active=? WHERE id=?")->execute([$name,$email,$active,$id]);
        if ($password) $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?")->execute([password_hash($password,PASSWORD_BCRYPT),$id]);
        $pdo->prepare("DELETE FROM admin_permissions WHERE admin_user_id=?")->execute([$id]);
        foreach (array_unique($perms) as $p) if (in_array($p, $allPerms, true)) $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, permission) VALUES (?, ?)")->execute([$id, $p]);
        flash('admin', 'Team member saved.', 'success');
        redirect(APP_URL . '/admin/team/index.php');
    }
}
$selected = $_POST['permissions'] ?? $existing;
adminPageStart('Edit Team Member', 'team');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="form-group"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($_POST['name'] ?? $member->name) ?>" required></div><div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? $member->email) ?>" required></div></div><div class="form-group"><label class="form-label">New Password</label><input class="form-control" type="password" name="password"></div><label style="display:flex;gap:.5rem;margin-bottom:1rem;"><input type="checkbox" name="is_active" <?= ($_POST ? isset($_POST['is_active']) : $member->is_active) ? 'checked' : '' ?>> Active</label><h4>Permissions</h4><?php foreach ($allPerms as $p): ?><label style="display:inline-flex;gap:.4rem;margin:.3rem 1rem .3rem 0;"><input type="checkbox" name="permissions[]" value="<?= e($p) ?>" <?= in_array($p, $selected, true) ? 'checked' : '' ?>> <?= e($p) ?></label><?php endforeach; ?><br><button class="btn btn-primary" style="margin-top:1rem;">Save Member</button> <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/team/assign_clients.php?id=<?= $id ?>">Assign Clients</a> <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/team/index.php">Cancel</a></form></div></div>
<?php adminPageEnd(); ?>
