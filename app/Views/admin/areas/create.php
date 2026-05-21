<?php adminPageStart('Add Problem Area', 'areas'); ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
  <form method="POST" action="<?= APP_URL ?>/admin/areas/create">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
    <div class="form-group"><label class="form-label">Area Name</label><input class="form-control" name="area_name" value="<?= e($_POST['area_name'] ?? '') ?>" required></div>
    <div class="form-group"><label class="form-label">Display Order</label><input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? '0') ?>"></div>
    <button class="btn btn-primary">Save Area</button>
    <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/areas">Cancel</a>
  </form>
</div></div>
<?php adminPageEnd(); ?>
