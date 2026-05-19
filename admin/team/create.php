<?php
require_once __DIR__ . '/../_bootstrap.php';
if (($_SESSION['admin_role'] ?? '') !== 'admin') redirect(APP_URL . '/admin/dashboard.php');
$pdo = getDB();
$allPerms = ['view_clients','edit_clients','register_clients','view_scores','manage_questions','manage_mappings','manage_areas'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $perms = $_POST['permissions'] ?? [];
    if (strlen($name) < 2) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    if (in_array('edit_clients', $perms, true) && !in_array('view_clients', $perms, true)) $perms[] = 'view_clients';
    if (!$errors) {
        $pdo->prepare("INSERT INTO admin_users (name,email,password,role,created_by,is_active) VALUES (?,?,?,'team_member',?,1)")->execute([$name,$email,password_hash($password,PASSWORD_BCRYPT),$_SESSION['admin_id']]);
        $id = $pdo->lastInsertId();
        foreach (array_unique($perms) as $p) if (in_array($p, $allPerms, true)) $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, permission) VALUES (?, ?)")->execute([$id, $p]);
        // Admin creating a team member is always admin role — notify anyway as a client-side record
        $pdo->prepare("INSERT INTO admin_notifications (type, category, message, related_admin_id) VALUES ('team_created','team',?,?)")
            ->execute(["New team member created: $name ($email)", (int)$id]);
        flash('admin', 'Team member created.', 'success');
        redirect(APP_URL . '/admin/team/index.php');
    }
}
adminPageStart('Add Team Member', 'team');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="form-group"><label class="form-label">Name</label><input class="form-control" name="name" required></div><div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div></div><div class="form-group"><label class="form-label">Initial Password</label><input class="form-control" type="password" name="password" required></div><h4>Permissions</h4><?php foreach ($allPerms as $p): ?><label style="display:inline-flex;gap:.4rem;margin:.3rem 1rem .3rem 0;"><input type="checkbox" name="permissions[]" value="<?= e($p) ?>"> <?= e($p) ?></label><?php endforeach; ?><br><button class="btn btn-primary" style="margin-top:1rem;">Create Member</button> <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/team/index.php">Cancel</a></form></div></div>
<?php adminPageEnd(); ?>
