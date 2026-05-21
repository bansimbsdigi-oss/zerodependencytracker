<?php
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
              <a class="admin-row-btn" href="<?= APP_URL ?>/admin/team/edit/<?= $m->id ?>">Edit</a>
              <a class="admin-row-btn outline" href="<?= APP_URL ?>/admin/team?member_id=<?= $m->id ?>">Assign</a>
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
            <form method="POST" action="<?= APP_URL ?>/admin/team" class="assignment-row">
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
            <form method="POST" action="<?= APP_URL ?>/admin/team" class="assignment-row">
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
    <form method="POST" action="<?= APP_URL ?>/admin/team" class="admin-modal-body">
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
