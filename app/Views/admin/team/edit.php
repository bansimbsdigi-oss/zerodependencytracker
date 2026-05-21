<?php
adminPageStart('Edit Team Member', 'team');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="POST" action="<?= APP_URL ?>/admin/team/edit/<?= $id ?>">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
          <label class="form-label">Name</label>
          <input class="form-control" name="name" value="<?= e($_POST['name'] ?? $member->name) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? $member->email) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">New Password (leave blank to keep current)</label>
        <input class="form-control" type="password" name="password">
      </div>

      <label style="display:flex;gap:.5rem;margin-bottom:1rem;">
        <input type="checkbox" name="is_active" <?= (!empty($_POST) ? isset($_POST['is_active']) : (bool)$member->is_active) ? 'checked' : '' ?>> Active
      </label>
      
      <h4>Permissions</h4>
      <div style="margin-bottom:1.5rem;">
        <?php foreach ($allPerms as $p): ?>
          <label style="display:inline-flex;gap:.4rem;margin:.3rem 1rem .3rem 0;cursor:pointer;">
            <input type="checkbox" name="permissions[]" value="<?= e($p) ?>" <?= in_array($p, $existing, true) ? 'checked' : '' ?>> <?= e($p) ?>
          </label>
        <?php endforeach; ?>
      </div>
      
      <button class="btn btn-primary">Save Member</button>
      <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/team?member_id=<?= $id ?>">Assign Clients</a>
      <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/team">Cancel</a>
    </form>
  </div>
</div>
<?php adminPageEnd(); ?>
