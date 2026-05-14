<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_mappings');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $submitted = array_keys($_POST['mapping'] ?? []);
    $submittedSet = array_fill_keys($submitted, true);
    $existing = $pdo->query("SELECT question_id, area_id FROM question_area_map")->fetchAll();
    $existingSet = [];
    foreach ($existing as $row) $existingSet[$row->question_id . '_' . $row->area_id] = true;
    $pdo->beginTransaction();
    foreach ($submittedSet as $key => $_) {
        if (!isset($existingSet[$key]) && preg_match('/^(\d+)_(\d+)$/', $key, $m)) {
            $pdo->prepare("INSERT IGNORE INTO question_area_map (question_id, area_id) VALUES (?, ?)")->execute([(int)$m[1], (int)$m[2]]);
        }
    }
    foreach ($existingSet as $key => $_) {
        if (!isset($submittedSet[$key])) {
            [$q, $a] = array_map('intval', explode('_', $key));
            $pdo->prepare("DELETE FROM question_area_map WHERE question_id = ? AND area_id = ?")->execute([$q, $a]);
        }
    }
    $pdo->commit();
    flash('admin', 'Mappings saved.', 'success');
    redirect(APP_URL . '/admin/mappings/index.php');
}

$questions = $pdo->query("SELECT sno, question_text, question_type FROM questions ORDER BY sno")->fetchAll();
$areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
$mapped = [];
foreach ($pdo->query("SELECT question_id, area_id FROM question_area_map")->fetchAll() as $m) $mapped[$m->question_id . '_' . $m->area_id] = true;
adminPageStart('Question Area Mapping', 'mappings');
?>
<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
  <div style="overflow:auto;">
    <table class="table">
      <thead><tr><th>Question</th><?php foreach ($areas as $area): ?><th><?= e($area->area_name) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($questions as $q): ?>
        <tr>
          <td><strong><?= e(mb_strimwidth($q->question_text, 0, 70, '...')) ?></strong><br><span class="text-muted"><?= e($q->question_type) ?></span></td>
          <?php foreach ($areas as $area): $key = $q->sno . '_' . $area->id; ?>
            <td style="text-align:center;"><input type="checkbox" name="mapping[<?= $key ?>]" <?= isset($mapped[$key]) ? 'checked' : '' ?>></td>
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
