<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <style>
    body.audit-page { background: #f8fafc; color: #0f172a; }
    .audit-shell { min-height: 100vh; }
    .audit-topbar { background: #fff; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 20; }
    .audit-topbar-inner { max-width: 1460px; margin: 0 auto; height: 72px; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; }
    .audit-brand { display: inline-flex; align-items: center; gap: 0.75rem; color: #0f8f83; font-size: 1.35rem; font-weight: 800; }
    .audit-brand-mark { width: 42px; height: 42px; border-radius: 10px; background: #0f8f83; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .audit-nav { display: flex; align-items: center; gap: 0.75rem; }
    .audit-nav a, .audit-avatar { min-height: 38px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
    .audit-nav a { padding: 0 1rem; color: #374151; background: #f3f4f6; }
    .audit-nav a.active { background: #ecfdf5; color: #0f8f83; }
    .audit-avatar { width: 40px; background: #0f8f83; color: #fff; }
    .audit-progress-wrap { background: #fff; border-bottom: 1px solid #e5e7eb; }
    .audit-progress { max-width: 1460px; margin: 0 auto; padding: 1rem 1.5rem; display: grid; grid-template-columns: 130px 1fr 54px; gap: 1rem; align-items: center; font-weight: 700; }
    .audit-progress-track { height: 10px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
    .audit-progress-bar { height: 100%; width: 0; border-radius: inherit; background: #14b8a6; transition: width 180ms ease; }
    .audit-progress-percent { color: #0f8f83; text-align: right; }
    .audit-main { max-width: 790px; margin: 2.5rem auto 4rem; padding: 0 1rem; }
    .audit-card { background: #fff; border: 1px solid #eef2f7; border-radius: 22px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); overflow: hidden; display: none; }
    .audit-card.is-active { display: block; }
    .audit-card-head { padding: 2rem 2.15rem 1.25rem; border-bottom: 1px solid #eef2f7; }
    .audit-kicker { color: #0f8f83; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 0.7rem; }
    .audit-question-title { font-size: 1.35rem; line-height: 1.4; color: #020617; margin: 0 0 0.6rem; }
    .audit-type-badge { display: flex; align-items: center; gap: 0.65rem; margin-top: 0.4rem; }
    .audit-type-pill { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.75rem; border-radius: 999px; background: #f0fdfa; color: #0f766e; font-size: 0.8rem; font-weight: 800; border: 1px solid #99f6e4; }
    .audit-type-hint { color: #94a3b8; font-size: 0.88rem; font-style: italic; }
    .audit-card-body { padding: 1.8rem 2.15rem; }
    .choice-list { display: grid; gap: 0.8rem; }
    .choice-option { min-height: 64px; border: 1px solid #dfe5ec; border-radius: 11px; padding: 0.8rem 1.2rem; display: flex; align-items: center; gap: 0.9rem; cursor: pointer; transition: border-color 160ms ease, background 160ms ease; }
    .choice-option:hover { border-color: #14b8a6; background: #f0fdfa; }
    .choice-option:has(input:checked) { border-color: #0f8f83; background: #ecfdf5; }
    .choice-option input { width: 20px; height: 20px; accent-color: #0f8f83; flex: 0 0 auto; }
    .choice-text { flex: 1; color: #071226; font-size: 1.1rem; font-weight: 700; }
    .point-pill { color: #0f8f83; background: #ccfbf1; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.86rem; font-weight: 800; white-space: nowrap; }
    .rating-note { color: #64748b; margin-bottom: 1rem; }
    .rating-grid.audit-rating { display: flex; gap: 0.55rem; flex-wrap: wrap; }
    .rating-grid.audit-rating .rating-btn { width: 64px; height: 64px; font-size: 1.25rem; }
    .audit-textarea { min-height: 130px; resize: vertical; }
    .audit-actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 0.4rem; }
    .audit-action { min-width: 140px; min-height: 50px; border-radius: 12px; font-size: 1rem; font-weight: 800; border: 1px solid #dfe5ec; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .audit-action.secondary { background: #f8fafc; color: #334155; }
    .audit-action.primary { background: #0f8f83; color: #fff; border-color: #0f8f83; }
    .audit-error { display: none; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-weight: 700; }
    .audit-section-badge { display: inline-flex; align-items: center; padding: 0.3rem 0.9rem; border-radius: 999px; background: #0f8f83; color: #fff; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.75rem; }
    @media (max-width: 760px) {
      .audit-topbar-inner { height: auto; padding: 0.9rem 1rem; gap: 0.5rem; align-items: center; justify-content: space-between; flex-wrap: wrap; }
      .audit-brand { font-size: 1.05rem; }
      .audit-brand-mark { width: 34px; height: 34px; font-size: 0.9rem; }
      .audit-progress { grid-template-columns: 1fr 48px; }
      .audit-main { margin-top: 1.25rem; }
      .audit-card-head, .audit-card-body { padding: 1.25rem; }
      .audit-question-title { font-size: 1.1rem; }
      .choice-text { font-size: 0.95rem; }
      .choice-option { min-height: 54px; padding: 0.65rem 0.9rem; }
      .audit-action { min-width: 100px; min-height: 44px; font-size: 0.9rem; }
      .rating-grid.audit-rating .rating-btn { width: 50px; height: 50px; font-size: 1rem; }
    }
  </style>
</head>
<body class="audit-page">
  <div class="audit-shell">
    <header class="audit-topbar">
      <div class="audit-topbar-inner">
        <a href="<?= APP_URL ?>/dashboard" class="audit-brand">
          <span class="audit-brand-mark">&#x2665;</span>
          <span>Zero Dependency Tracker</span>
        </a>
        <nav class="audit-nav" aria-label="Primary">
          <a href="<?= APP_URL ?>/dashboard" class="active">Dashboard</a>
          <a href="<?= APP_URL ?>/profile">Profile</a>
          <span class="audit-avatar"><?= e($userInitial) ?></span>
          <form method="POST" action="<?= APP_URL ?>/logout" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
            <button type="submit" style="border:0;background:transparent;cursor:pointer;font:inherit;color:inherit;">Logout</button>
          </form>
        </nav>
      </div>
    </header>

    <div class="audit-progress-wrap">
      <div class="audit-progress">
        <div class="audit-progress-label" id="audit-progress-label">Question 1 of <?= (int)$totalQuestions ?></div>
        <div class="audit-progress-track" aria-hidden="true">
          <div class="audit-progress-bar" id="audit-progress-bar"></div>
        </div>
        <div class="audit-progress-percent" id="audit-progress-percent">0%</div>
      </div>
    </div>

    <main class="audit-main">
      <?php if ($totalQuestions === 0): ?>
        <div class="audit-card is-active">
          <div class="audit-card-head">
            <div class="audit-kicker">No questions</div>
            <h1 class="audit-question-title">No active questions are mapped to your problem area yet.</h1>
          </div>
          <div class="audit-card-body">
            <a class="audit-action primary" href="<?= APP_URL ?>/dashboard">Back to Dashboard</a>
          </div>
        </div>
      <?php else: ?>
        <form method="POST" action="<?= APP_URL ?>/audit" id="audit-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="window_id" value="<?= (int)$windowId ?>">
          <input type="hidden" name="session_id" value="<?= (int)$sessionId ?>">
          <div class="audit-error" id="audit-error">Please answer this question before continuing.</div>

          <?php foreach ($questions as $index => $q): ?>
            <?php
              $typeInfo = [
                  'mcq'          => ['label' => 'Single Select',  'hint' => 'Choose one answer',              'icon' => '◉'],
                  'multi_select' => ['label' => 'Multi Select',   'hint' => 'You may select multiple answers', 'icon' => '☑'],
                  'rating'       => ['label' => 'Rating Scale',   'hint' => 'Tap a number to rate',           'icon' => '★'],
                  'text'         => ['label' => 'Long Text',      'hint' => 'Type your answer below',         'icon' => '✎'],
              ][$q->question_type] ?? ['label' => 'Answer', 'hint' => '', 'icon' => '?'];
              $sectionPrefix = $q->section_name ? 'Section ' . $q->_sectionNum . ' — ' . strtoupper($q->section_name) . ' · ' : '';
              $kicker = $index === 0 ? $sectionPrefix . 'Question ' . ($index + 1) . ' of ' . $totalQuestions . ' — ' . $auditLabel : 'Preview — ' . $typeInfo['label'];
            ?>
            <section class="audit-card <?= $index === 0 ? 'is-active' : '' ?>" data-question-card data-index="<?= $index ?>" data-question-id="<?= (int)$q->sno ?>" data-type="<?= e($q->question_type) ?>" data-section-name="<?= e($q->section_name ?? '') ?>" data-section-num="<?= (int)$q->_sectionNum ?>">
              <div class="audit-card-head">
                <?php if ($q->section_name): ?>
                  <div class="audit-section-badge">Section <?= (int)$q->_sectionNum ?> &mdash; <?= e(strtoupper($q->section_name)) ?></div>
                <?php endif; ?>
                <div class="audit-kicker" data-kicker><?= e($kicker) ?></div>
                <h1 class="audit-question-title"><?= e($q->question_text) ?></h1>
                <div class="audit-type-badge">
                  <span class="audit-type-pill"><?= $typeInfo['icon'] ?> <?= e($typeInfo['label']) ?></span>
                  <span class="audit-type-hint"><?= e($typeInfo['hint']) ?></span>
                </div>
              </div>

              <div class="audit-card-body">
                <?php if ($q->question_type === 'text'): ?>
                  <textarea name="q_<?= $q->sno ?>" class="form-control audit-textarea" rows="4" placeholder="Type your answer here"></textarea>

                <?php elseif ($q->question_type === 'rating'): ?>
                  <p class="rating-note"><?= (int)$q->rating_min ?> = Least&nbsp;&nbsp;•&nbsp;&nbsp;<?= (int)$q->rating_max ?> = Most</p>
                  <div class="rating-grid audit-rating">
                    <?php for ($i = $q->rating_min; $i <= $q->rating_max; $i++): ?>
                      <button type="button" class="rating-btn" data-value="<?= $i ?>"><?= $i ?></button>
                      <input type="radio" name="q_<?= $q->sno ?>" value="<?= $i ?>">
                    <?php endfor; ?>
                  </div>

                <?php elseif ($q->question_type === 'mcq'): ?>
                  <div class="choice-list">
                    <?php
                      $opts = $pdo->prepare('SELECT * FROM options WHERE question_id = ? ORDER BY display_order ASC');
                      $opts->execute([$q->sno]);
                      foreach ($opts->fetchAll() as $opt):
                    ?>
                      <label class="choice-option">
                        <input type="radio" name="q_<?= $q->sno ?>" value="<?= $opt->id ?>">
                        <span class="choice-text"><?= e($opt->option_text) ?></span>
                        <span class="point-pill"><?= (int)$opt->points ?> <?= (int)$opt->points === 1 ? 'pt' : 'pts' ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>

                <?php elseif ($q->question_type === 'multi_select'): ?>
                  <div class="choice-list">
                    <?php
                      $opts = $pdo->prepare('SELECT * FROM options WHERE question_id = ? ORDER BY display_order ASC');
                      $opts->execute([$q->sno]);
                      foreach ($opts->fetchAll() as $opt):
                    ?>
                      <label class="choice-option">
                        <input type="checkbox" name="q_<?= $q->sno ?>[]" value="<?= $opt->id ?>">
                        <span class="choice-text"><?= e($opt->option_text) ?></span>
                        <span class="point-pill"><?= (int)$opt->points ?> <?= (int)$opt->points === 1 ? 'pt' : 'pts' ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="audit-actions">
                  <button type="button" class="audit-action secondary" data-prev>Previous</button>
                  <button type="button" class="audit-action primary" data-next>Next</button>
                  <button type="submit" class="audit-action primary" data-submit>Submit Audit</button>
                </div>
              </div>
            </section>
          <?php endforeach; ?>
        </form>
      <?php endif; ?>
    </main>
  </div>

  <script>
    document.querySelectorAll('.rating-grid').forEach(function(group) {
      group.querySelectorAll('.rating-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          group.querySelectorAll('.rating-btn').forEach(function(b) { b.classList.remove('selected'); });
          btn.classList.add('selected');
          var radio = group.querySelector('input[type="radio"][value="' + btn.dataset.value + '"]');
          if (radio) radio.checked = true;
        });
      });
    });

    const cards = Array.from(document.querySelectorAll('[data-question-card]'));
    const form = document.getElementById('audit-form');
    const errorBox = document.getElementById('audit-error');
    const label = document.getElementById('audit-progress-label');
    const bar = document.getElementById('audit-progress-bar');
    const percent = document.getElementById('audit-progress-percent');
    let currentIndex = 0;

    function questionAnswered(card) {
      const type = card.dataset.type;
      const qid = card.dataset.questionId;
      if (type === 'text') {
        const input = card.querySelector(`[name="q_${qid}"]`);
        return input && input.value.trim().length > 0;
      }
      if (type === 'multi_select') {
        return card.querySelectorAll(`[name="q_${qid}[]"]:checked`).length > 0;
      }
      return !!card.querySelector(`[name="q_${qid}"]:checked`);
    }

    function updateCards() {
      const total = cards.length;
      const pct = total ? Math.round(((currentIndex + 1) / total) * 100) : 0;
      cards.forEach((card, index) => {
        card.classList.toggle('is-active', index === currentIndex);
        const kicker = card.querySelector('[data-kicker]');
        if (kicker && index === currentIndex) {
          const sName = card.dataset.sectionName;
          const sNum  = card.dataset.sectionNum;
          const sectionPart = sName ? `Section ${sNum} — ${sName.toUpperCase()} · ` : '';
          kicker.textContent = `${sectionPart}Question ${index + 1} of ${total} — <?= e($auditLabel) ?>`;
        }
        const prev = card.querySelector('[data-prev]');
        const next = card.querySelector('[data-next]');
        const submit = card.querySelector('[data-submit]');
        if (prev) prev.style.visibility = currentIndex === 0 ? 'hidden' : 'visible';
        if (next) next.style.display = currentIndex === total - 1 ? 'none' : 'inline-flex';
        if (submit) submit.style.display = currentIndex === total - 1 ? 'inline-flex' : 'none';
      });
      if (label) label.textContent = `Question ${currentIndex + 1} of ${total}`;
      if (bar) bar.style.width = `${pct}%`;
      if (percent) percent.textContent = `${pct}%`;
      if (errorBox) errorBox.style.display = 'none';
      cards[currentIndex]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.addEventListener('click', (event) => {
      const prev = event.target.closest('[data-prev]');
      const next = event.target.closest('[data-next]');
      if (prev) { currentIndex = Math.max(0, currentIndex - 1); updateCards(); }
      if (next) {
        if (!questionAnswered(cards[currentIndex])) { if (errorBox) errorBox.style.display = 'block'; return; }
        currentIndex = Math.min(cards.length - 1, currentIndex + 1);
        updateCards();
      }
    });

    if (form) {
      form.addEventListener('submit', (event) => {
        const missingIndex = cards.findIndex((card) => !questionAnswered(card));
        if (missingIndex !== -1) {
          event.preventDefault();
          currentIndex = missingIndex;
          updateCards();
          if (errorBox) errorBox.style.display = 'block';
        }
      });
      updateCards();
    }
  </script>
</body>
</html>
