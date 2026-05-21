<?php
adminPageStart('Question Area Mapping', 'mappings');
?>

<!-- Quick-jump links to view questions per area -->
<div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center;">
  <span style="font-size:.85rem;font-weight:700;color:#374151;">View questions for:</span>
  <?php foreach ($areas as $area): ?>
    <a href="<?= APP_URL ?>/admin/questions?area_id=<?= $area->id ?>"
       class="btn btn-secondary btn-sm"><?= e($area->area_name) ?></a>
  <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
<form method="POST" action="<?= APP_URL ?>/admin/mappings">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
  <div style="overflow:auto;">
    <table class="table">
      <thead>
        <tr>
          <th>Question</th>
          <?php foreach ($areas as $area): ?>
            <th style="text-align:center;"><?= e($area->area_name) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($questions as $q): ?>
        <tr>
          <td>
            <strong><?= e(mb_strimwidth($q->question_text, 0, 70, '...')) ?></strong>
            <br><span class="text-muted" style="font-size:.8rem;"><?= e($q->question_type) ?></span>
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
  <div style="display:flex;align-items:center;gap:1rem;margin-top:1rem;flex-wrap:wrap;">
    <button class="btn btn-primary">Save Mappings</button>
    <span style="font-size:.83rem;color:#6b7280;">After saving, use the links above to verify which questions appear for each area.</span>
  </div>
</form>
</div></div>
<?php adminPageEnd(); ?>
