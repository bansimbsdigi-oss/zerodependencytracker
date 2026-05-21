<?php adminPageStart('Problem Areas', 'areas'); ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
  <a class="btn btn-primary" href="<?= APP_URL ?>/admin/areas/create">Add Area</a>
</div>
<table class="table">
  <thead><tr><th>Order</th><th>Area</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($areas as $area): ?>
    <tr>
      <td><?= (int)$area->display_order ?></td>
      <td><strong><?= e($area->area_name) ?></strong></td>
      <td><span class="badge <?= $area->is_active ? 'badge-success' : 'badge-gray' ?>"><?= $area->is_active ? 'Active' : 'Hidden' ?></span></td>
      <td><?= e(date('d M Y', strtotime($area->created_at))) ?></td>
      <td style="display:flex;gap:.5rem;">
        <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/areas/edit/<?= $area->id ?>">Edit</a>
        <form method="POST" action="<?= APP_URL ?>/admin/areas/toggle/<?= $area->id ?>">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="is_active" value="<?= $area->is_active ? 0 : 1 ?>">
          <button class="btn btn-sm <?= $area->is_active ? 'btn-secondary' : 'btn-primary' ?>"><?= $area->is_active ? 'Hide' : 'Activate' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php adminPageEnd(); ?>
