<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('manage_questions');
$pdo = getDB();
$errors = [];

$areas    = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order, area_name")->fetchAll();
$sections = $pdo->query("SELECT id, area_id, section_name, display_order FROM question_sections ORDER BY display_order, section_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $text      = trim($_POST['question_text'] ?? '');
    $type      = $_POST['question_type'] ?? 'mcq';
    $ratingMin = (int)($_POST['rating_min'] ?? 1);
    $ratingMax = (int)($_POST['rating_max'] ?? 10);
    $sectionId = (int)($_POST['section_id'] ?? 0) ?: null;

    $optTexts  = array_map('trim', $_POST['option_text']   ?? []);
    $optPoints = $_POST['option_points'] ?? [];
    $optTexts  = array_values(array_filter($optTexts, fn($t) => $t !== ''));

    if (strlen($text) < 5) $errors[] = 'Question text must be at least 5 characters.';
    if (!in_array($type, ['mcq','text','multi_select','rating'], true)) $errors[] = 'Invalid question type.';
    if ($type === 'rating' && $ratingMax <= $ratingMin) $errors[] = 'Rating max must be greater than min.';
    if (in_array($type, ['mcq','multi_select'], true) && count($optTexts) < 2) $errors[] = 'Add at least 2 options for this question type.';

    if (!$errors) {
        $pdo->prepare("INSERT INTO questions (question_text, question_type, rating_min, rating_max, flag, section_id) VALUES (?, ?, ?, ?, 1, ?)")
            ->execute([$text, $type, $ratingMin, $ratingMax, $sectionId]);
        $qId = (int)$pdo->lastInsertId();

        // Auto-map to area via section
        if ($sectionId) {
            $sec = $pdo->prepare("SELECT area_id FROM question_sections WHERE id = ?");
            $sec->execute([$sectionId]);
            $secRow = $sec->fetch();
            if ($secRow) {
                $pdo->prepare("INSERT IGNORE INTO question_area_map (question_id, area_id) VALUES (?, ?)")->execute([$qId, $secRow->area_id]);
            }
        }

        // Save inline options
        foreach ($optTexts as $i => $optText) {
            $pts = max(0, (int)($optPoints[$i] ?? 0));
            $pdo->prepare("INSERT INTO options (question_id, option_text, points, display_order) VALUES (?, ?, ?, ?)")
                ->execute([$qId, $optText, $pts, $i + 1]);
        }

        flash('admin', 'Question created.', 'success');
        redirect(APP_URL . '/admin/questions/index.php');
    }
}

adminPageStart('Add Question', 'questions');
$selectedType    = $_POST['question_type'] ?? 'mcq';
$selectedSection = (int)($_POST['section_id'] ?? 0);
$selectedArea    = (int)($_POST['area_id'] ?? 0);
$postedOptTexts  = array_map('trim', $_POST['option_text']   ?? []);
$postedOptPoints = $_POST['option_points'] ?? [];
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST" id="question-form">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

  <div class="form-grid-2">
    <div class="form-group">
      <label class="form-label">Problem Area <span style="color:#dc2626">*</span></label>
      <select class="form-control" id="area_select" name="area_id">
        <option value="">— Select Area —</option>
        <?php foreach ($areas as $area): ?>
          <option value="<?= $area->id ?>" <?= $selectedArea === (int)$area->id ? 'selected' : '' ?>><?= e($area->area_name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Section <span style="color:#dc2626">*</span></label>
      <select class="form-control" id="section_select" name="section_id">
        <option value="">— Select Area first —</option>
        <?php foreach ($sections as $sec): ?>
          <option value="<?= $sec->id ?>" data-area="<?= $sec->area_id ?>" <?= $selectedSection === (int)$sec->id ? 'selected' : '' ?> style="display:none;">
            <?= e($sec->section_name) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span style="font-size:.8rem;color:#6b7280;margin-top:.3rem;display:block;">
        Don't see your section? <a href="<?= APP_URL ?>/admin/sections/create.php" target="_blank">Create one</a>.
      </span>
    </div>
  </div>

  <div class="form-group">
    <label class="form-label">Question Text <span style="color:#dc2626">*</span></label>
    <textarea class="form-control" name="question_text" rows="3" required><?= e($_POST['question_text'] ?? '') ?></textarea>
  </div>

  <div class="form-group">
    <label class="form-label">Question Type</label>
    <select class="form-control" name="question_type" id="question_type">
      <option value="mcq"          <?= $selectedType === 'mcq'          ? 'selected' : '' ?>>Single Select (one answer)</option>
      <option value="multi_select" <?= $selectedType === 'multi_select' ? 'selected' : '' ?>>Multi Select (multiple answers)</option>
      <option value="text"         <?= $selectedType === 'text'         ? 'selected' : '' ?>>Long Text (open-ended)</option>
      <option value="rating"       <?= $selectedType === 'rating'       ? 'selected' : '' ?>>Rating Scale (numeric 1–10)</option>
    </select>
  </div>

  <div id="type-hint" style="margin-bottom:1rem;padding:.75rem 1rem;border-radius:8px;background:#f0fdfa;border:1px solid #99f6e4;color:#0f766e;font-size:.9rem;"></div>

  <div id="rating-fields" style="display:none;">
    <div class="form-grid-2">
      <div class="form-group"><label class="form-label">Rating Min</label><input class="form-control" type="number" name="rating_min" value="<?= e($_POST['rating_min'] ?? '1') ?>"></div>
      <div class="form-group"><label class="form-label">Rating Max</label><input class="form-control" type="number" name="rating_max" value="<?= e($_POST['rating_max'] ?? '10') ?>"></div>
    </div>
  </div>

  <!-- ── Inline Options Builder ──────────────────────────────────── -->
  <div id="options-section" style="display:none;margin-bottom:1.25rem;">
    <label class="form-label" style="font-weight:700;">Answer Options <span style="color:#dc2626">*</span> <small style="font-weight:400;color:#6b7280;">(min 2)</small></label>

    <div class="opt-header">
      <span style="font-size:.78rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Option Text</span>
      <span style="font-size:.78rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Pts</span>
      <span></span>
    </div>

    <div id="options-list">
      <?php
        $restoreOpts = count($postedOptTexts) > 0 ? $postedOptTexts : ['', ''];
        foreach ($restoreOpts as $ri => $rt):
      ?>
      <div class="option-row">
        <input type="text"   name="option_text[]"   class="form-control" placeholder="e.g. Slightly better movement" value="<?= e($rt) ?>">
        <input type="number" name="option_points[]" class="form-control" placeholder="0" min="0" max="999" value="<?= e($postedOptPoints[$ri] ?? 0) ?>">
        <button type="button" class="btn btn-danger btn-sm remove-opt" title="Remove" style="padding:.35rem .6rem;">✕</button>
      </div>
      <?php endforeach; ?>
    </div>

    <button type="button" id="add-opt-btn" class="btn btn-secondary btn-sm" style="margin-top:.25rem;">+ Add Option</button>
    <p style="margin:.6rem 0 0;font-size:.8rem;color:#6b7280;">Points are used to calculate the client's score. Set 0 for no points.</p>
  </div>
  <!-- ──────────────────────────────────────────────────────────── -->

  <button class="btn btn-primary">Save Question</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/questions/index.php">Cancel</a>
</form>
</div></div>

<script>
// ── Section filter ───────────────────────────────────────────────
const areaSelect    = document.getElementById('area_select');
const sectionSelect = document.getElementById('section_select');
const allSectOpts   = Array.from(sectionSelect.querySelectorAll('option[data-area]'));

function filterSections() {
    const areaId = areaSelect.value;
    let hasVisible = false;
    allSectOpts.forEach(opt => {
        const show = opt.dataset.area === areaId;
        opt.style.display = show ? '' : 'none';
        if (!show && opt.selected) opt.selected = false;
        if (show) hasVisible = true;
    });
    sectionSelect.options[0].textContent = hasVisible ? '— Select Section —' : (areaId ? '— No sections for this area —' : '— Select Area first —');
}
areaSelect.addEventListener('change', filterSections);
if (areaSelect.value) filterSections();

// ── Question type UI ─────────────────────────────────────────────
const typeHints = {
    mcq:          'Client sees a list of options with radio buttons — they must pick exactly one answer.',
    multi_select: 'Client sees a list of options with checkboxes — they can pick one or more answers.',
    text:         'Client sees a text area — they type a free-form written answer. Does not contribute to score.',
    rating:       'Client sees numbered buttons from Min to Max — they tap one number as their score.',
};
const typeSel     = document.getElementById('question_type');
const hint        = document.getElementById('type-hint');
const ratingDiv   = document.getElementById('rating-fields');
const optSection  = document.getElementById('options-section');

function updateType() {
    const val = typeSel.value;
    hint.textContent = typeHints[val] || '';
    ratingDiv.style.display  = val === 'rating' ? '' : 'none';
    optSection.style.display = (val === 'mcq' || val === 'multi_select') ? '' : 'none';
}
typeSel.addEventListener('change', updateType);
updateType();

// ── Options builder ──────────────────────────────────────────────
const optList    = document.getElementById('options-list');
const addOptBtn  = document.getElementById('add-opt-btn');

function buildRow(text, pts) {
    const row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML = `
      <input type="text"   name="option_text[]"   class="form-control" placeholder="e.g. Slightly better movement" value="${text || ''}">
      <input type="number" name="option_points[]" class="form-control" placeholder="0" min="0" max="999" value="${pts !== undefined ? pts : 0}">
      <button type="button" class="btn btn-danger btn-sm remove-opt" title="Remove" style="padding:.35rem .6rem;">✕</button>
    `;
    return row;
}

addOptBtn.addEventListener('click', () => {
    optList.appendChild(buildRow('', 0));
    optList.lastElementChild.querySelector('input[type=text]').focus();
});

optList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-opt')) {
        const rows = optList.querySelectorAll('.option-row');
        if (rows.length > 2) {
            e.target.closest('.option-row').remove();
        } else {
            alert('You need at least 2 options.');
        }
    }
});
</script>
<?php adminPageEnd(); ?>
