<?php adminPageStart('Add Section', 'sections'); ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST" action="<?= APP_URL ?>/admin/sections/create">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

  <div class="form-group">
    <label class="form-label">Problem Area <span style="color:#dc2626">*</span></label>
    <select class="form-control" name="area_id" required>
      <option value="">— Select Area —</option>
      <?php foreach ($areas as $area): ?>
        <option value="<?= $area->id ?>" <?= (($_POST['area_id'] ?? '') == $area->id) ? 'selected' : '' ?>><?= e($area->area_name) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label class="form-label">Section Name <span style="color:#dc2626">*</span></label>
    <input class="form-control" name="section_name" value="<?= e($_POST['section_name'] ?? '') ?>" placeholder="e.g. Mobility" required>
  </div>

  <div class="form-group">
    <label class="form-label">Display Order</label>
    <input class="form-control" type="number" name="display_order" value="<?= e($_POST['display_order'] ?? '0') ?>">
    <span style="font-size:.8rem;color:#6b7280;">Lower number appears first within the same area.</span>
  </div>

  <button class="btn btn-primary">Save Section</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/sections">Cancel</a>
</form>
</div></div>
<?php adminPageEnd(); ?>
