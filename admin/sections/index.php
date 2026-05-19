<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $sid = (int)($_POST['section_id'] ?? 0);
    // Unlink questions from this section before deleting
    $pdo->prepare("UPDATE questions SET section_id = NULL WHERE section_id = ?")->execute([$sid]);
    $pdo->prepare("DELETE FROM question_sections WHERE id = ?")->execute([$sid]);
    flash('admin', 'Section deleted.', 'success');
    redirect(APP_URL . '/admin/sections/index.php');
}

$sections = $pdo->query("
    SELECT qs.*, pa.area_name, COUNT(q.sno) AS question_count
    FROM question_sections qs
    JOIN problem_areas pa ON pa.id = qs.area_id
    LEFT JOIN questions q ON q.section_id = qs.id
    GROUP BY qs.id
    ORDER BY pa.display_order, pa.area_name, qs.display_order, qs.id
")->fetchAll();

adminPageStart('Sections', 'sections');
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
  <a class="btn btn-primary" href="<?= APP_URL ?>/admin/sections/create.php">Add Section</a>
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
        <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/sections/edit.php?id=<?= $s->id ?>">Edit</a>
        <form method="POST" onsubmit="return confirm('Delete this section? Questions in it will become unassigned.');">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="section_id" value="<?= $s->id ?>">
          <button class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#6b7280;padding:3rem;">
    No sections yet. <a href="<?= APP_URL ?>/admin/sections/create.php">Add the first section</a>.
  </div></div>
<?php endif; ?>
<?php adminPageEnd(); ?>
