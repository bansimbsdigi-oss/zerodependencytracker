<?php
require_once __DIR__ . '/../_bootstrap.php';
if (($_SESSION['admin_role'] ?? '') !== 'admin') redirect(APP_URL . '/admin/dashboard.php');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id=? AND role='team_member'");
$stmt->execute([$id]);
$member = $stmt->fetch();
if (!$member) redirect(APP_URL . '/admin/team/index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $clientId = (int)($_POST['client_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'assign') $pdo->prepare("INSERT INTO client_assignments (client_id, team_member_id, assigned_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE team_member_id=VALUES(team_member_id), assigned_by=VALUES(assigned_by), assigned_at=NOW()")->execute([$clientId,$id,$_SESSION['admin_id']]);
    if (($_POST['action'] ?? '') === 'remove') $pdo->prepare("DELETE FROM client_assignments WHERE client_id=? AND team_member_id=?")->execute([$clientId,$id]);
    redirect(APP_URL . '/admin/team/assign_clients.php?id=' . $id);
}
$assigned = $pdo->prepare("SELECT u.*, pa.area_name FROM users u JOIN client_assignments ca ON ca.client_id=u.id LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE ca.team_member_id=? ORDER BY u.name");
$assigned->execute([$id]);
$assigned = $assigned->fetchAll();
$unassigned = $pdo->query("SELECT u.*, pa.area_name FROM users u LEFT JOIN client_assignments ca ON ca.client_id=u.id LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE ca.client_id IS NULL ORDER BY u.name")->fetchAll();
adminPageStart('Assign Clients: ' . $member->name, 'team');
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
<div class="card"><div class="card-header">Assigned Clients</div><div class="card-body"><?php foreach ($assigned as $c): ?><form method="POST" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><input type="hidden" name="client_id" value="<?= $c->id ?>"><input type="hidden" name="action" value="remove"><span><?= e($c->name) ?> <small class="text-muted"><?= e($c->area_name ?? '') ?></small></span><button class="btn btn-secondary btn-sm">Remove</button></form><?php endforeach; if (!$assigned): ?><p class="text-muted">No assigned clients.</p><?php endif; ?></div></div>
<div class="card"><div class="card-header">Unassigned Clients</div><div class="card-body"><?php foreach ($unassigned as $c): ?><form method="POST" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><input type="hidden" name="client_id" value="<?= $c->id ?>"><input type="hidden" name="action" value="assign"><span><?= e($c->name) ?> <small class="text-muted"><?= e($c->area_name ?? '') ?></small></span><button class="btn btn-primary btn-sm">Assign</button></form><?php endforeach; if (!$unassigned): ?><p class="text-muted">All clients are assigned.</p><?php endif; ?></div></div>
</div>
<p style="margin-top:1rem;"><a class="btn btn-secondary" href="<?= APP_URL ?>/admin/team/index.php">Back to Team</a></p>
<?php adminPageEnd(); ?>
