<?php adminPageStart('Edit Problem Area', 'areas'); ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
  <form method="POST" action="<?= APP_URL ?>/admin/areas/edit/<?= $id ?>">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
    <div class="form-group"><label class="form-label">Area Name</label><input class="form-control" name="area_name" value="<?= e($_POST['area_name'] ?? $area->area_name) ?>" required></div>
    <div class="form-group"><label class="form-label">Display Order</label><input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? $area->display_order) ?>"></div>
    <label style="display:flex;gap:.5rem;margin-bottom:1rem;"><input type="checkbox" name="is_active" <?= ($_POST ? isset($_POST['is_active']) : $area->is_active) ? 'checked' : '' ?>> Active</label>
    <button class="btn btn-primary">Save Changes</button>
    <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/areas">Cancel</a>
  </form>
</div></div>
<?php adminPageEnd(); ?>
