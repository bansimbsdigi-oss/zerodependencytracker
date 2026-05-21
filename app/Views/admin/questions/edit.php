<?php adminPageStart('Edit Question', 'questions'); ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST" action="<?= APP_URL ?>/admin/questions/edit/<?= $id ?>">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

  <?php
  $typeNames = [
      'mcq'          => 'Single Select (Radio buttons)',
      'multi_select' => 'Multi Select (Checkboxes)',
      'text'         => 'Long Text (Open-ended)',
      'rating'       => 'Rating Scale (Numeric)',
  ];
  ?>
  <p class="text-muted" style="margin-bottom:1.25rem;">
    Type: <strong><?= e($typeNames[$q->question_type] ?? $q->question_type) ?></strong>
    <small style="color:#64748b;"> — cannot be changed after creation</small>
  </p>

  <div class="form-grid-2">
    <div class="form-group">
      <label class="form-label">Problem Area</label>
      <select class="form-control" id="area_select" name="area_id">
        <option value="">— Select Area —</option>
        <?php foreach ($areas as $area): ?>
          <option value="<?= $area->id ?>" <?= $selectedArea === (int)$area->id ? 'selected' : '' ?>><?= e($area->area_name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Section</label>
      <select class="form-control" id="section_select" name="section_id">
        <option value="">— No section —</option>
        <?php foreach ($sections as $sec): ?>
          <option value="<?= $sec->id ?>" data-area="<?= $sec->area_id ?>" <?= $selectedSection === (int)$sec->id ? 'selected' : '' ?> style="display:none;">
            <?= e($sec->section_name) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label class="form-label">Question Text</label>
    <textarea class="form-control" name="question_text" rows="3" required><?= e($_POST['question_text'] ?? $q->question_text) ?></textarea>
  </div>

  <?php if ($q->question_type === 'rating'): ?>
  <div class="form-grid-2">
    <div class="form-group"><label class="form-label">Rating Min</label><input class="form-control" type="number" name="rating_min" value="<?= e($_POST['rating_min'] ?? $q->rating_min) ?>"></div>
    <div class="form-group"><label class="form-label">Rating Max</label><input class="form-control" type="number" name="rating_max" value="<?= e($_POST['rating_max'] ?? $q->rating_max) ?>"></div>
  </div>
  <?php endif; ?>

  <?php if ($isChoiceType): ?>
  <!-- ── Inline Options Manager ─────────────────────────────────── -->
  <div style="margin-bottom:1.5rem;">
    <label class="form-label" style="font-weight:700;margin-bottom:.75rem;display:block;">Answer Options</label>

    <div class="opt-header opt-header-edit">
      <span style="font-size:.78rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Option Text</span>
      <span style="font-size:.78rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Pts</span>
      <span></span>
    </div>

    <!-- Existing options (editable) -->
    <div id="existing-options-list">
      <?php foreach ($existingOptions as $opt): ?>
      <div class="existing-opt-row opt-row-edit" data-opt-id="<?= $opt->id ?>">
        <input type="hidden" name="edit_option_id[]" value="<?= $opt->id ?>">
        <input type="text"   name="edit_option_text[]"   class="form-control" value="<?= e($opt->option_text) ?>">
        <input type="number" name="edit_option_points[]" class="form-control" min="0" max="999" value="<?= (int)$opt->points ?>">
        <div style="display:flex;gap:.4rem;">
          <input type="checkbox" name="delete_option_id[]" value="<?= $opt->id ?>" id="del_<?= $opt->id ?>" style="display:none;">
          <button type="button" class="btn btn-danger btn-sm toggle-delete" data-target="del_<?= $opt->id ?>" style="flex:1;">Delete</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- New options -->
    <div id="new-options-list"></div>

    <button type="button" id="add-new-opt" class="btn btn-secondary btn-sm" style="margin-top:.25rem;">+ Add Option</button>
    <p style="margin:.6rem 0 0;font-size:.8rem;color:#6b7280;">Check "Delete" on any row and save to remove it. Use "+ Add Option" to add more choices.</p>
  </div>
  <?php endif; ?>

  <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:1.25rem;">
    <input type="checkbox" name="flag" <?= ($_POST ? isset($_POST['flag']) : $q->flag) ? 'checked' : '' ?>> Visible in audits
  </label>

  <button class="btn btn-primary">Save Changes</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/questions">Cancel</a>
</form>
</div></div>

<script>
// ── Section filter ───────────────────────────────────────────────
const areaSelect    = document.getElementById('area_select');
const sectionSelect = document.getElementById('section_select');
const allSectOpts   = Array.from(sectionSelect.querySelectorAll('option[data-area]'));

function filterSections() {
    const areaId = areaSelect.value;
    allSectOpts.forEach(opt => {
        const show = opt.dataset.area === areaId;
        opt.style.display = show ? '' : 'none';
        if (!show && opt.selected) opt.selected = false;
    });
    sectionSelect.options[0].textContent = areaId ? '— Select Section —' : '— No section —';
}
areaSelect.addEventListener('change', filterSections);
if (areaSelect.value) filterSections();

<?php if ($isChoiceType): ?>
// ── Delete toggle ────────────────────────────────────────────────
document.getElementById('existing-options-list').addEventListener('click', (e) => {
    const btn = e.target.closest('.toggle-delete');
    if (!btn) return;
    const cb  = document.getElementById(btn.dataset.target);
    const row = btn.closest('.existing-opt-row');
    cb.checked = !cb.checked;
    if (cb.checked) {
        row.style.opacity = '0.4';
        btn.textContent = 'Undo';
        btn.classList.replace('btn-danger', 'btn-secondary');
    } else {
        row.style.opacity = '';
        btn.textContent = 'Delete';
        btn.classList.replace('btn-secondary', 'btn-danger');
    }
});

// ── New options builder ──────────────────────────────────────────
const newList   = document.getElementById('new-options-list');
const addNewBtn = document.getElementById('add-new-opt');

addNewBtn.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'opt-row-edit';
    row.style.marginBottom = '.5rem';
    row.innerHTML = `
      <input type="text"   name="new_option_text[]"   class="form-control" placeholder="Option text">
      <input type="number" name="new_option_points[]" class="form-control" placeholder="0" min="0" max="999" value="0">
      <button type="button" class="btn btn-danger btn-sm remove-new">Remove</button>
    `;
    newList.appendChild(row);
    row.querySelector('input[type=text]').focus();
});

newList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-new')) {
        e.target.closest('div').remove();
    }
});
<?php endif; ?>
</script>
<?php adminPageEnd(); ?>
