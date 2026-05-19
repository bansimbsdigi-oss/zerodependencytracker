<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $qid  = (int)($_POST['id'] ?? 0);
    $flag = (int)($_POST['flag'] ?? 0);
    $pdo->prepare("UPDATE questions SET flag = ? WHERE sno = ?")->execute([$flag, $qid]);
    flash('admin', 'Question visibility updated.', 'success');
    $back = (int)($_POST['area_id'] ?? 0);
    redirect(APP_URL . '/admin/questions/index.php' . ($back ? '?area_id=' . $back : ''));
}

$areas       = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active=1 ORDER BY display_order, area_name")->fetchAll();
$filterAreaId = (int)($_GET['area_id'] ?? 0);

$questions = [];
$grouped   = [];

if ($filterAreaId) {
    $stmt = $pdo->prepare("
        SELECT q.*, qs.section_name, qs.display_order AS sec_order,
               COUNT(DISTINCT o.id) AS option_count
        FROM questions q
        LEFT JOIN question_sections qs ON qs.id = q.section_id
        LEFT JOIN problem_areas pa ON pa.id = qs.area_id
        LEFT JOIN options o ON o.question_id = q.sno
        WHERE pa.id = ?
        GROUP BY q.sno
        ORDER BY qs.display_order, qs.id, q.sno
    ");
    $stmt->execute([$filterAreaId]);
    $questions = $stmt->fetchAll();

    foreach ($questions as $q) {
        $grouped[$q->section_name ?? '— No Section —'][] = $q;
    }
}

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

<!-- ── Toolbar ────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">

  <form method="GET" id="area-filter-form" style="display:flex;align-items:center;gap:.6rem;flex:1;min-width:220px;max-width:380px;">
    <label style="font-weight:700;white-space:nowrap;color:#374151;">Problem Area</label>
    <select name="area_id" class="form-control" id="area-filter-select" onchange="this.form.submit()" style="margin:0;">
      <option value="">— Select Area —</option>
      <?php foreach ($areas as $a): ?>
        <option value="<?= $a->id ?>" <?= $filterAreaId === (int)$a->id ? 'selected' : '' ?>><?= e($a->area_name) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <a class="btn btn-primary" href="<?= APP_URL ?>/admin/questions/create.php<?= $filterAreaId ? '?area_id=' . $filterAreaId : '' ?>" style="margin-left:auto;">
    Add Question
  </a>
</div>

<?php if (!$filterAreaId): ?>
  <!-- No area selected yet -->
  <div class="card">
    <div class="card-body" style="text-align:center;padding:3.5rem 2rem;color:#6b7280;">
      <div style="font-size:2.5rem;margin-bottom:.75rem;">📋</div>
      <p style="font-size:1.1rem;font-weight:600;color:#374151;margin-bottom:.35rem;">Select a Problem Area</p>
      <p style="margin:0;">Choose an area from the dropdown above to view its questions and sections.</p>
    </div>
  </div>

<?php elseif (empty($grouped)): ?>
  <!-- Area selected but no questions -->
  <div class="card">
    <div class="card-body" style="text-align:center;padding:3rem 2rem;color:#6b7280;">
      <p style="font-size:1rem;font-weight:600;color:#374151;margin-bottom:.35rem;">No questions yet for this area.</p>
      <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/admin/questions/create.php">Add the first question</a>
    </div>
  </div>

<?php else: ?>

  <!-- Selected area label -->
  <?php
    $selectedArea = null;
    foreach ($areas as $a) { if ((int)$a->id === $filterAreaId) { $selectedArea = $a; break; } }
  ?>
  <div style="margin-bottom:.75rem;padding:.55rem 1rem;background:#0f172a;color:#fff;border-radius:.5rem;font-weight:700;font-size:.9rem;letter-spacing:.08em;text-transform:uppercase;">
    <?= e($selectedArea->area_name ?? '') ?>
    <span style="font-weight:400;opacity:.6;margin-left:.75rem;font-size:.8rem;"><?= count($questions) ?> question<?= count($questions) !== 1 ? 's' : '' ?></span>
  </div>

  <?php foreach ($grouped as $sectionName => $qs): ?>

    <!-- Section sub-header -->
    <div style="padding:.5rem 1rem;background:#c8a84b;color:#fff;font-weight:600;font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">
      <?= e($sectionName) ?>
      <span style="opacity:.75;font-weight:400;margin-left:.5rem;">(<?= count($qs) ?> question<?= count($qs) !== 1 ? 's' : '' ?>)</span>
    </div>

    <table class="table" style="margin-bottom:0;border-radius:0;">
      <thead>
        <tr><th>ID</th><th>Question</th><th>Type</th><th>Options</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($qs as $q): ?>
        <tr>
          <td><?= (int)$q->sno ?></td>
          <td><?= e(mb_strimwidth($q->question_text, 0, 90, '...')) ?></td>
          <?php $tl = questionTypeLabel($q->question_type); ?>
          <td><span style="display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .65rem;border-radius:999px;font-size:.82rem;font-weight:700;background:<?= $tl['bg'] ?>;color:<?= $tl['color'] ?>;"><?= $tl['icon'] ?> <?= $tl['label'] ?></span></td>
          <td><?= in_array($q->question_type, ['mcq','multi_select'], true) ? (int)$q->option_count : '-' ?></td>
          <td><span class="badge <?= $q->flag ? 'badge-success' : 'badge-gray' ?>"><?= $q->flag ? 'Visible' : 'Hidden' ?></span></td>
          <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/questions/edit.php?id=<?= $q->sno ?>">Edit</a>
            <?php if (in_array($q->question_type, ['mcq','multi_select'], true)): ?>
              <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/options/index.php?question_id=<?= $q->sno ?>">Options</a>
            <?php endif; ?>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
              <input type="hidden" name="id"      value="<?= $q->sno ?>">
              <input type="hidden" name="flag"    value="<?= $q->flag ? 0 : 1 ?>">
              <input type="hidden" name="area_id" value="<?= $filterAreaId ?>">
              <button class="btn btn-sm <?= $q->flag ? 'btn-secondary' : 'btn-primary' ?>"><?= $q->flag ? 'Hide' : 'Show' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="margin-bottom:1.25rem;"></div>

  <?php endforeach; ?>

<?php endif; ?>

<?php adminPageEnd(); ?>
