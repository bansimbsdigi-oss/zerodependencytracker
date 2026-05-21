<?php
adminPageStart('Question Area Mapping', 'mappings');
?>
<div class="card"><div class="card-body">
<form method="POST" action="<?= APP_URL ?>/admin/mappings">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
  <div style="overflow:auto;">
    <table class="table">
      <thead>
        <tr>
          <th>Question</th>
          <?php foreach ($areas as $area): ?>
            <th><?= e($area->area_name) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($questions as $q): ?>
        <tr>
          <td>
            <strong><?= e(mb_strimwidth($q->question_text, 0, 70, '...')) ?></strong>
            <br><span class="text-muted"><?= e($q->question_type) ?></span>
          </td>
          <?php foreach ($areas as $area): $key = $q->sno . '_' . $area->id; ?>
            <td style="text-align:center;">
              <input type="checkbox" name="mapping[<?= $key ?>]" <?= isset($mapped[$key]) ? 'checked' : '' ?>>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button class="btn btn-primary" style="margin-top:1rem;">Save Mappings</button>
</form>
</div></div>
<?php adminPageEnd(); ?>
