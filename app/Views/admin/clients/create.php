<?php adminPageStart('Register Client', 'clients'); ?>
<?php if (!empty($errors)): ?>
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
      <div class="form-group">
        <label class="form-label">Name</label>
        <input class="form-control" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">WhatsApp Mobile</label>
          <input class="form-control" name="mobile" value="<?= e($_POST['mobile'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Area</label>
        <select class="form-control" name="area_id">
          <?php foreach ($areas as $a): ?>
            <option value="<?= $a->id ?>"><?= e($a->area_name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary">Register Client</button>
      <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/clients">Cancel</a>
    </form>
  </div>
</div>
<?php adminPageEnd(); ?>
