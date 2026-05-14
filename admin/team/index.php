<?php
require_once __DIR__ . '/../_bootstrap.php';
if (($_SESSION['admin_role'] ?? '') !== 'admin') redirect(APP_URL . '/admin/dashboard.php');

$pdo = getDB();
$allPerms = ['view_clients','edit_clients','register_clients','view_scores','manage_questions','manage_mappings','manage_areas'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    verifyCsrf();

    if ($_POST['action'] === 'create_member') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $perms = $_POST['permissions'] ?? [];

        if (strlen($name) < 2) {
            flash('admin', 'Name is required.', 'danger');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('admin', 'Valid email is required.', 'danger');
        } elseif (strlen($password) < 8) {
            flash('admin', 'Password must be at least 8 characters.', 'danger');
        } else {
            $dup = $pdo->prepare("SELECT id FROM admin_users WHERE email=?");
            $dup->execute([$email]);
            if ($dup->fetch()) {
                flash('admin', 'A team member with this email already exists.', 'danger');
            } else {
                if (in_array('edit_clients', $perms, true) && !in_array('view_clients', $perms, true)) $perms[] = 'view_clients';
                $pdo->prepare("INSERT INTO admin_users (name,email,password,role,created_by,is_active) VALUES (?,?,?,'team_member',?,1)")
                    ->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $_SESSION['admin_id']]);
                $memberId = (int)$pdo->lastInsertId();
                foreach (array_unique($perms) as $p) {
                    if (in_array($p, $allPerms, true)) {
                        $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, permission) VALUES (?, ?)")->execute([$memberId, $p]);
                    }
                }
                flash('admin', 'Team member created.', 'success');
                redirect(APP_URL . '/admin/team/index.php?member_id=' . $memberId);
            }
        }

        redirect(APP_URL . '/admin/team/index.php');
    }

    if (in_array($_POST['action'], ['assign', 'remove'], true)) {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);

        $validMember = $pdo->prepare("SELECT id FROM admin_users WHERE id=? AND role='team_member'");
        $validMember->execute([$memberId]);

        if ($clientId && $validMember->fetchColumn() && $_POST['action'] === 'assign') {
            $pdo->prepare("INSERT INTO client_assignments (client_id, team_member_id, assigned_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE team_member_id=VALUES(team_member_id), assigned_by=VALUES(assigned_by), assigned_at=NOW()")
                ->execute([$clientId, $memberId, $_SESSION['admin_id']]);
            flash('admin', 'Client assigned.', 'success');
        } elseif ($clientId && $_POST['action'] === 'remove') {
            $pdo->prepare("DELETE FROM client_assignments WHERE client_id=? AND team_member_id=?")
                ->execute([$clientId, $memberId]);
            flash('admin', 'Client removed from assignment.', 'success');
        }

        redirect(APP_URL . '/admin/team/index.php?member_id=' . $memberId);
    }
}

$members = $pdo->query("
    SELECT au.*,
           COUNT(DISTINCT ca.client_id) client_count,
           GROUP_CONCAT(DISTINCT ap.permission ORDER BY ap.permission SEPARATOR ', ') permissions
    FROM admin_users au
    LEFT JOIN client_assignments ca ON ca.team_member_id=au.id
    LEFT JOIN admin_permissions ap ON ap.admin_user_id=au.id
    WHERE au.role='team_member'
    GROUP BY au.id
    ORDER BY au.created_at DESC
")->fetchAll();

$selectedMemberId = (int)($_GET['member_id'] ?? ($members[0]->id ?? 0));
$selectedMember = null;
foreach ($members as $member) {
    if ((int)$member->id === $selectedMemberId) {
        $selectedMember = $member;
        break;
    }
}

$assigned = [];
$unassigned = [];
if ($selectedMember) {
    $stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u JOIN client_assignments ca ON ca.client_id=u.id LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE ca.team_member_id=? ORDER BY u.name");
    $stmt->execute([$selectedMember->id]);
    $assigned = $stmt->fetchAll();

    $unassigned = $pdo->query("SELECT u.*, pa.area_name FROM users u LEFT JOIN client_assignments ca ON ca.client_id=u.id LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE ca.client_id IS NULL ORDER BY u.name")->fetchAll();
}

adminPageStart('Team Members', 'team');
?>

<div class="admin-page-action">
  <button class="admin-action-btn primary" type="button" data-modal-open="team-member-modal">+ Add Team Member</button>
</div>

<section class="admin-table-card">
  <div class="admin-table-title">Team Members (<?= count($members) ?>)</div>
  <table class="table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Clients</th>
        <th>Permissions</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$members): ?>
        <tr><td colspan="6" style="color:#64748b;">No team members yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($members as $m): ?>
        <?php $permissionList = array_filter(array_map('trim', explode(',', $m->permissions ?? ''))); ?>
        <tr>
          <td><strong><?= e($m->name) ?></strong></td>
          <td><?= e($m->email) ?></td>
          <td><strong><?= (int)$m->client_count ?></strong></td>
          <td>
            <div class="permission-pills">
              <?php if (!$permissionList): ?>
                <span class="permission-pill muted">none</span>
              <?php endif; ?>
              <?php foreach ($permissionList as $permission): ?>
                <span class="permission-pill"><?= e($permission) ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td><span class="badge <?= $m->is_active ? 'badge-success' : 'badge-gray' ?>"><?= $m->is_active ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <div class="admin-row-actions">
              <a class="admin-row-btn" href="<?= APP_URL ?>/admin/team/edit.php?id=<?= $m->id ?>">Edit</a>
              <a class="admin-row-btn outline" href="<?= APP_URL ?>/admin/team/index.php?member_id=<?= $m->id ?>">Assign</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php if ($selectedMember): ?>
  <section class="admin-assignment-card">
    <div class="admin-assignment-title">🤝 <?= e($selectedMember->name) ?> — Client Assignments</div>
    <div class="admin-assignment-grid">
      <div>
        <h3>Assigned Clients (<?= count($assigned) ?>)</h3>
        <div class="assignment-list">
          <?php if (!$assigned): ?>
            <p class="assignment-empty">No assigned clients.</p>
          <?php endif; ?>
          <?php foreach ($assigned as $c): ?>
            <form method="POST" class="assignment-row">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="member_id" value="<?= (int)$selectedMember->id ?>">
              <input type="hidden" name="client_id" value="<?= (int)$c->id ?>">
              <span><strong><?= e($c->name) ?></strong><small><?= e($c->area_name ?? 'Unassigned') ?></small></span>
              <button class="assignment-btn danger">Remove</button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <h3>Unassigned Clients (<?= count($unassigned) ?>)</h3>
        <div class="assignment-list">
          <?php if (!$unassigned): ?>
            <p class="assignment-empty">All clients are assigned.</p>
          <?php endif; ?>
          <?php foreach ($unassigned as $c): ?>
            <form method="POST" class="assignment-row">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
              <input type="hidden" name="action" value="assign">
              <input type="hidden" name="member_id" value="<?= (int)$selectedMember->id ?>">
              <input type="hidden" name="client_id" value="<?= (int)$c->id ?>">
              <span><strong><?= e($c->name) ?></strong><small><?= e($c->area_name ?? 'Unassigned') ?></small></span>
              <button class="assignment-btn success">Assign</button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<div class="admin-modal-backdrop" id="team-member-modal" aria-hidden="true">
  <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="team-member-modal-title">
    <div class="admin-modal-header">
      <h2 id="team-member-modal-title">Add Team Member</h2>
      <button class="admin-modal-close" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <form method="POST" class="admin-modal-body">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
      <input type="hidden" name="action" value="create_member">
      <div class="admin-form-grid">
        <div class="form-group">
          <label class="form-label">Name</label>
          <input class="form-control" name="name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Initial Password</label>
        <input class="form-control" type="password" name="password" minlength="8" required>
      </div>
      <label class="form-label">Permissions</label>
      <div class="modal-permission-grid">
        <?php foreach ($allPerms as $p): ?>
          <label><input type="checkbox" name="permissions[]" value="<?= e($p) ?>"> <span><?= e($p) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="admin-modal-actions">
        <button type="button" class="admin-action-btn" data-modal-close>Cancel</button>
        <button class="admin-action-btn primary">Create Member</button>
      </div>
    </form>
  </div>
</div>

<script>
  const modal = document.getElementById('team-member-modal');
  document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
      modal?.classList.add('is-open');
      modal?.setAttribute('aria-hidden', 'false');
      modal?.querySelector('input[name="name"]')?.focus();
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => {
      modal?.classList.remove('is-open');
      modal?.setAttribute('aria-hidden', 'true');
    });
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }
  });
</script>

<?php adminPageEnd(); ?>
