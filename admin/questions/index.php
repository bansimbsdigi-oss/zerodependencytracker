<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $flag = (int)($_POST['flag'] ?? 0);
    $pdo->prepare("UPDATE questions SET flag = ? WHERE sno = ?")->execute([$flag, $id]);
    flash('admin', 'Question visibility updated.', 'success');
    redirect(APP_URL . '/admin/questions/index.php');
}

$questions = $pdo->query("SELECT q.*, COUNT(DISTINCT qam.area_id) area_count, COUNT(DISTINCT o.id) option_count FROM questions q LEFT JOIN question_area_map qam ON qam.question_id=q.sno LEFT JOIN options o ON o.question_id=q.sno GROUP BY q.sno ORDER BY q.sno DESC")->fetchAll();

function questionTypeLabel($type) {
    return [
        'mcq'          => ['label' => 'Single Select', 'icon' => '◉', 'color' => '#1d4ed8', 'bg' => '#eff6ff'],
        'multi_select' => ['label' => 'Multi Select',  'icon' => '☑', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
        'text'         => ['label' => 'Long Text',     'icon' => '✎', 'color' => '#b45309', 'bg' => '#fffbeb'],
        'rating'       => ['label' => 'Rating Scale',  'icon' => '★', 'color' => '#0f766e', 'bg' => '#f0fdfa'],
    ][$type] ?? ['label' => $type, 'icon' => '?', 'color' => '#64748b', 'bg' => '#f8fafc'];
}

adminPageStart('Questions', 'questions');
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;"><a class="btn btn-primary" href="<?= APP_URL ?>/admin/questions/create.php">Add Question</a></div>
<table class="table">
  <thead><tr><th>ID</th><th>Question</th><th>Type</th><th>Areas</th><th>Options</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($questions as $q): ?>
    <tr>
      <td><?= (int)$q->sno ?></td>
      <td><?= e(mb_strimwidth($q->question_text, 0, 90, '...')) ?></td>
      <?php $tl = questionTypeLabel($q->question_type); ?>
      <td><span style="display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .65rem;border-radius:999px;font-size:.82rem;font-weight:700;background:<?= $tl['bg'] ?>;color:<?= $tl['color'] ?>;"><?= $tl['icon'] ?> <?= $tl['label'] ?></span></td>
      <td><?= (int)$q->area_count ?></td>
      <td><?= in_array($q->question_type, ['mcq','multi_select'], true) ? (int)$q->option_count : '-' ?></td>
      <td><span class="badge <?= $q->flag ? 'badge-success' : 'badge-gray' ?>"><?= $q->flag ? 'Visible' : 'Hidden' ?></span></td>
      <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/questions/edit.php?id=<?= $q->sno ?>">Edit</a>
        <?php if (in_array($q->question_type, ['mcq','multi_select'], true)): ?><a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/options/index.php?question_id=<?= $q->sno ?>">Options</a><?php endif; ?>
        <form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><input type="hidden" name="id" value="<?= $q->sno ?>"><input type="hidden" name="flag" value="<?= $q->flag ? 0 : 1 ?>"><button class="btn btn-sm <?= $q->flag ? 'btn-secondary' : 'btn-primary' ?>"><?= $q->flag ? 'Hide' : 'Show' ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php adminPageEnd(); ?>
