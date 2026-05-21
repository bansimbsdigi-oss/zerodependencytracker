<?php adminPageStart('Edit Client', 'clients'); ?>
<?php if (!empty($errors)): ?>
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="POST" action="<?= APP_URL ?>/admin/clients/edit/<?= (int)$id ?>" id="edit-client-form">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

      <div class="form-group">
        <label class="form-label">Name</label>
        <input class="form-control" name="name" value="<?= e($_POST['name'] ?? $client->name) ?>" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? $client->email) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">WhatsApp Mobile</label>
          <input class="form-control" name="mobile" value="<?= e($_POST['mobile'] ?? $client->mobile) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Area</label>
        <select class="form-control" name="area_id">
          <?php foreach ($areas as $a): ?>
            <option value="<?= $a->id ?>" <?= (($_POST['area_id'] ?? $client->area_id) == $a->id) ? 'selected' : '' ?>><?= e($a->area_name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php
        $isGraduated = (bool)(($_POST['is_graduated'] ?? null) !== null
            ? $_POST['is_graduated']
            : $client->is_graduated);
        $notesValue = e($_POST['graduation_notes'] ?? $client->graduation_notes ?? '');
      ?>
      <label style="display:flex;gap:.6rem;align-items:center;margin-bottom:1rem;cursor:pointer;">
        <input type="checkbox" id="graduated_cb" name="is_graduated" <?= $isGraduated ? 'checked' : '' ?>> Graduated
      </label>

      <div id="graduation-feedback-wrap" style="<?= $isGraduated ? '' : 'display:none;' ?>margin-bottom:1.25rem;">
        <label class="form-label" style="font-weight:700;">
          Graduation Feedback / Notes <span style="color:#dc2626">*</span>
          <small style="font-weight:400;color:#6b7280;"> — visible only to admins</small>
        </label>
        <textarea
          id="graduation_notes"
          name="graduation_notes"
          class="form-control"
          rows="5"
          placeholder="Describe the client's progress, achievements, and reason for graduation…"
          style="resize:vertical;"
        ><?= $notesValue ?></textarea>
      </div>

      <button class="btn btn-primary">Save Changes</button>
      <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/clients/view/<?= $client->id ?>">Cancel</a>
    </form>
  </div>
</div>

<script>
(function () {
  const cb   = document.getElementById('graduated_cb');
  const wrap = document.getElementById('graduation-feedback-wrap');
  const ta   = document.getElementById('graduation_notes');

  cb.addEventListener('change', function () {
    wrap.style.display = this.checked ? '' : 'none';
    if (this.checked) ta.focus();
  });
})();
</script>
<?php adminPageEnd(); ?>
