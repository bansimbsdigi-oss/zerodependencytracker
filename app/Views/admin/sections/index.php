<?php adminPageStart('Sections', 'sections'); ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
  <a class="btn btn-primary" href="<?= APP_URL ?>/admin/sections/create">Add Section</a>
</div>

<?php if ($sections): ?>
<table class="table">
  <thead>
    <tr><th>ID</th><th>Section Name</th><th>Problem Area</th><th>Order</th><th>Questions</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($sections as $s): ?>
    <tr>
      <td><?= (int)$s->id ?></td>
      <td><strong><?= e($s->section_name) ?></strong></td>
      <td><span style="padding:.25rem .65rem;border-radius:999px;font-size:.82rem;font-weight:600;background:#f0fdfa;color:#0f766e;"><?= e($s->area_name) ?></span></td>
      <td><?= (int)$s->display_order ?></td>
      <td><?= (int)$s->question_count ?></td>
      <td style="display:flex;gap:.5rem;">
        <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/sections/edit/<?= $s->id ?>">Edit</a>
        <form method="POST" action="<?= APP_URL ?>/admin/sections/delete/<?= $s->id ?>" onsubmit="return confirm('Delete this section? Questions in it will become unassigned.');">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <button class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#6b7280;padding:3rem;">
    No sections yet. <a href="<?= APP_URL ?>/admin/sections/create">Add the first section</a>.
  </div></div>
<?php endif; ?>
<?php adminPageEnd(); ?>
