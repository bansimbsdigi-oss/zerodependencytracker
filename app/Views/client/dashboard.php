<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <meta name="csrf-token" content="<?= e(getCsrf()) ?>">
  <style>
    .dash-welcome { margin: 1.5rem 0 1.25rem; }
    .dash-welcome h1 { margin: 0 0 0.2rem; font-size: 1.5rem; }
    .dash-welcome p  { margin: 0; font-size: 0.92rem; color: var(--gray-500); }
    .dash-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
    .dash-stat { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 1.1rem 1.25rem; display: flex; flex-direction: column; gap: 0.25rem; box-shadow: var(--shadow-sm); }
    .dash-stat-label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); }
    .dash-stat-value { font-size: 2rem; font-weight: 800; line-height: 1; color: var(--gray-900); }
    .dash-stat-sub   { font-size: 0.78rem; color: var(--gray-400); margin-top: 0.1rem; }
    .dash-stat.stat-green .dash-stat-value { color: var(--success); }
    .dash-stat.stat-teal  .dash-stat-value { color: var(--primary); }
    .dash-stat.stat-up    .dash-stat-value { color: #7c3aed; }
    .audit-cta { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: 1.1rem 1.25rem; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.875rem; }
    .audit-cta h3 { color: #fff; margin: 0 0 0.15rem; font-size: 0.97rem; }
    .audit-cta p  { opacity: 0.85; margin: 0; font-size: 0.83rem; }
    .audit-cta-btn { background: #fff; color: var(--primary); padding: 0.45rem 1.1rem; border-radius: var(--radius-full); font-weight: 700; font-size: 0.85rem; text-decoration: none; white-space: nowrap; flex-shrink: 0; }
    .audit-cta-section { margin-bottom: 1.25rem; }
    .audit-cta-section h2 { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-500); margin-bottom: 0.6rem; }
    .grad-banner { background: linear-gradient(135deg, var(--success), #047857); color: #fff; padding: 1.25rem 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; }
    .grad-banner h2 { color: #fff; margin: 0 0 0.25rem; font-size: 1.1rem; }
    .grad-banner p  { color: rgba(255,255,255,0.88); margin: 0; font-size: 0.88rem; }
    .section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-500); margin-bottom: 0.75rem; }
    .progress-card { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1rem; box-shadow: var(--shadow-sm); }
    .progress-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.1rem; }
    .progress-card-title { font-size: 1rem; font-weight: 700; color: var(--gray-800); }
    .progress-card-badge { font-size: 0.72rem; font-weight: 600; color: var(--gray-500); background: var(--gray-100); border: 1px solid var(--gray-200); border-radius: var(--radius-full); padding: 0.2rem 0.65rem; }
    .progress-row { margin-bottom: 0.85rem; }
    .progress-row:last-child { margin-bottom: 0; }
    .progress-row-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.3rem; }
    .progress-row-name { font-size: 0.83rem; font-weight: 500; color: var(--gray-700); }
    .progress-row-pct  { font-size: 0.83rem; font-weight: 700; color: var(--gray-800); }
    .progress-track { background: var(--gray-100); border-radius: var(--radius-full); height: 8px; overflow: hidden; }
    .progress-fill  { height: 100%; border-radius: var(--radius-full); background: #374151; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
    .progress-fill.fill-green { background: #16a34a; }
    .progress-fill.fill-warn  { background: #ca8a04; }
    .progress-fill.fill-red   { background: #b91c1c; }
    .progress-row-pct.pct-red { color: #b91c1c; }
    .history-card { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 1.5rem; }
    .history-card-head { padding: 1rem 1.25rem; border-bottom: 1px solid var(--gray-100); display: flex; justify-content: space-between; align-items: center; }
    .history-card-head span { font-size: 1rem; font-weight: 700; color: var(--gray-800); }
    .history-table { width: 100%; border-collapse: collapse; }
    .history-table th { text-align: left; padding: 0.65rem 1.25rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
    .history-table td { padding: 0.85rem 1.25rem; font-size: 0.88rem; color: var(--gray-700); border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .history-table tr:last-child td { border-bottom: none; }
    .history-table tr:hover td { background: var(--gray-50); }
    .score-pill { display: inline-block; padding: 0.2rem 0.65rem; border-radius: var(--radius-full); font-size: 0.8rem; font-weight: 700; }
    .status-badge { display: inline-block; padding: 0.2rem 0.65rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; background: #ecfdf5; color: #065f46; }
    .report-link { font-size: 0.8rem; color: var(--primary); font-weight: 600; white-space: nowrap; }
    .coach-feedback-row { display: none; }
    .coach-feedback-row.open { display: table-row; }
    .coach-feedback-row td { background: #eef2ff; padding: .75rem 1.25rem 1rem; border-top: 1px solid #c7d2fe; }
    .coach-feedback-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #4f46e5; margin-bottom: .35rem; }
    .coach-feedback-text  { font-size: .88rem; color: #1e1b4b; line-height: 1.55; white-space: pre-wrap; }
    .coach-feedback-meta  { font-size: .73rem; color: #818cf8; margin-top: .35rem; }
    .has-feedback-dot { width: 7px; height: 7px; border-radius: 50%; background: #4f46e5; display: inline-block; margin-left: .35rem; vertical-align: middle; flex-shrink: 0; }
    .expand-btn { background: none; border: none; cursor: pointer; color: var(--primary); font-size: 0.78rem; font-weight: 600; padding: 0; white-space: nowrap; display: flex; align-items: center; gap: 0.3rem; }
    .expand-btn svg { transition: transform 0.2s; }
    .expand-btn.open svg { transform: rotate(180deg); }
    .tutorial-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .tutorial-modal { background: #fff; padding: 2rem; border-radius: var(--radius-md); max-width: 500px; width: 100%; text-align: center; }
    @media (max-width: 600px) {
      .dash-stats { gap: 0.6rem; }
      .dash-stat  { padding: 0.875rem 0.75rem; }
      .dash-stat-value { font-size: 1.6rem; }
      .audit-cta  { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
      .audit-cta-btn { align-self: stretch; text-align: center; }
      .history-table th:nth-child(2), .history-table td:nth-child(2) { display: none; }
      .history-table th, .history-table td { padding: 0.75rem 0.875rem; }
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="container">
      <a href="<?= APP_URL ?>/dashboard" class="brand">&#x2665; Zero Dependency Tracker</a>
      <div class="nav-links">
        <a href="<?= APP_URL ?>/dashboard" class="active">Dashboard</a>
        <div class="nav-avatar-wrap">
          <button class="nav-avatar" id="navAvatarBtn" aria-label="Account menu" aria-expanded="false">
            <?= e(strtoupper(substr($user->name, 0, 1))) ?>
          </button>
          <div class="nav-dropdown" id="navDropdown">
            <a href="<?= APP_URL ?>/profile">Profile</a>
            <hr>
            <form method="POST" action="<?= APP_URL ?>/logout">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
              <button type="submit">Logout</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="container">

    <div class="dash-welcome">
      <h1>Welcome back, <?= e(explode(' ', $user->name)[0]) ?></h1>
      <p>Track your physiotherapy progress and complete your scheduled audits.</p>
    </div>

    <div class="dash-stats">
      <div class="dash-stat stat-teal">
        <span class="dash-stat-label">Audits Done</span>
        <span class="dash-stat-value"><?= $totalAudits ?></span>
        <span class="dash-stat-sub">total completed</span>
      </div>
      <div class="dash-stat stat-green">
        <span class="dash-stat-label">Latest Score</span>
        <span class="dash-stat-value"><?= $totalAudits > 0 ? $latestScore . '%' : '—' ?></span>
        <span class="dash-stat-sub"><?= $totalAudits > 0 ? date('M Y', strtotime($history[0]->completed_at)) : 'no audits yet' ?></span>
      </div>
      <div class="dash-stat stat-up">
        <span class="dash-stat-label">Improvement</span>
        <span class="dash-stat-value"><?php
          if ($improvement === null) echo '—';
          elseif ($improvement > 0) echo '+' . $improvement . '%';
          elseif ($improvement < 0) echo $improvement . '%';
          else echo '0%';
        ?></span>
        <span class="dash-stat-sub"><?= $totalAudits > 1 ? 'since first audit' : 'need 2+ audits' ?></span>
      </div>
    </div>

    <?php if ($user->is_graduated): ?>
      <div class="grad-banner">
        <h2>Congratulations!</h2>
        <p>You have achieved 100% performance and graduated from your primary recovery program.</p>
      </div>
    <?php endif; ?>

    <?php if (!$user->is_graduated && !empty($activeAudits)): ?>
      <div class="audit-cta-section">
        <h2>Action Required</h2>
        <?php foreach ($activeAudits as $audit): ?>
          <div class="audit-cta">
            <div>
              <h3><?= ucwords(str_replace('_', ' ', $audit->audit_type)) ?> Audit</h3>
              <p>Month <?= e($audit->audit_month) ?>, <?= e($audit->audit_year) ?></p>
            </div>
            <a href="<?= APP_URL ?>/audit?window_id=<?= $audit->id ?>" class="audit-cta-btn">
              <?= $audit->session_status === 'in_progress' ? 'Continue' : 'Start' ?> &rarr;
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($areaAuditRows)): ?>
      <?php foreach ($areaAuditRows as $areaName => $audits):
        $auditCount = count($audits);
        $badgeLabel = 'Last ' . $auditCount . ' audit' . ($auditCount !== 1 ? 's' : '');
      ?>
      <div class="progress-card">
        <div class="progress-card-head">
          <span class="progress-card-title"><?= e($areaName) ?> &mdash; Progress</span>
          <span class="progress-card-badge"><?= $badgeLabel ?></span>
        </div>
        <?php foreach ($audits as $h):
          $pct       = $h->max_score > 0 ? round(($h->total_score / $h->max_score) * 100) : 0;
          $fillClass = $pct >= 75 ? 'fill-green' : ($pct >= 50 ? '' : ($pct >= 30 ? 'fill-warn' : 'fill-red'));
          $pctClass  = $pct < 40 ? 'pct-red' : '';
          $typeLabel = ucwords(str_replace('_', '-', $h->audit_type));
          $monthYear = date('M Y', mktime(0, 0, 0, (int)$h->audit_month, 1, (int)$h->audit_year));
          $doneDate  = $h->completed_at ? date('M j', strtotime($h->completed_at)) : '';
        ?>
          <div class="progress-row">
            <div class="progress-row-head">
              <span class="progress-row-name"><?= e($typeLabel) ?> <?= e($monthYear) ?><?= $doneDate ? ' &mdash; ' . e($doneDate) : '' ?></span>
              <span class="progress-row-pct <?= $pctClass ?>"><?= $pct ?>%</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill <?= $fillClass ?>" style="width:<?= $pct ?>%;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($allSessions)): ?>
      <div class="progress-card" style="text-align:center;color:var(--gray-400);padding:2rem 1.25rem;">
        No audits yet. Start your first audit above.
      </div>
    <?php else: ?>
      <div class="history-card">
        <div class="history-card-head"><span>Audit History</span></div>
        <table class="history-table">
          <thead>
            <tr>
              <th>Window</th>
              <th>Area</th>
              <th>Score</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allSessions as $h):
              $typeName    = ucwords(str_replace('_', '-', $h->audit_type));
              $monthYear   = date('M Y', mktime(0, 0, 0, (int)$h->audit_month, 1, (int)$h->audit_year));
              $hasFeedback = !empty($h->admin_feedback);
              $fbRowId     = 'fb-' . $h->id;
              $isCompleted = $h->status === 'completed';
              $isPerfect   = $h->is_perfect;
              $scoreLabel  = $h->total_score . '/' . $h->max_score;
            ?>
            <tr>
              <td>
                <strong><?= e($typeName) ?> <?= e($monthYear) ?></strong>
                <?php if ($hasFeedback): ?><span class="has-feedback-dot" title="Coach feedback available"></span><?php endif; ?>
              </td>
              <td><?= e($h->area_name ?? 'General') ?></td>
              <td><span style="font-size:0.88rem;font-weight:600;color:var(--gray-800);"><?= e($scoreLabel) ?></span></td>
              <td>
                <?php if ($isPerfect): ?>
                  <span class="status-badge" style="background:#fef9c3;color:#854d0e;">Perfect</span>
                <?php elseif ($isCompleted): ?>
                  <span class="status-badge">Completed</span>
                <?php else: ?>
                  <span class="status-badge" style="background:#fef9c3;color:#854d0e;">In progress</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <?php if ($hasFeedback): ?>
                  <button class="expand-btn" data-target="<?= $fbRowId ?>" style="color:#4f46e5;justify-content:flex-end;">
                    Coach Notes
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </button>
                <?php endif; ?>
                <?php if ($isCompleted): ?>
                  <a href="<?= APP_URL ?>/audit-report/<?= $h->id ?>" class="report-link" style="display:block;margin-top:0.2rem;">Report</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($hasFeedback): ?>
            <tr class="coach-feedback-row" id="<?= $fbRowId ?>">
              <td colspan="5">
                <div class="coach-feedback-label">Coach Feedback</div>
                <div class="coach-feedback-text"><?= e($h->admin_feedback) ?></div>
                <div class="coach-feedback-meta">
                  — <?= e($h->coach_name ?? 'Your Coach') ?>
                  <?php if ($h->admin_feedback_at): ?> &middot; <?= e(date('d M Y', strtotime($h->admin_feedback_at))) ?><?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </main>

  <?php if ($user->tutorial_done == 0 && empty($history)): ?>
    <div id="tutorial-overlay" class="tutorial-overlay">
      <div class="tutorial-modal">
        <div style="font-size:2.5rem;color:var(--primary);margin-bottom:1rem;">&#x2665;</div>
        <h2 style="margin-bottom:1rem;">Welcome to your Dashboard</h2>
        <p style="color:var(--gray-600);margin-bottom:2rem;font-size:0.95rem;">
          This is where you will track your physiotherapy progress. Your coach will open audits at the middle and end of every month. Complete them to watch your recovery chart rise.
        </p>
        <button id="btn-tutorial-done" class="btn btn-primary btn-block">Got it, let's start</button>
      </div>
    </div>
  <?php endif; ?>

  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
  <script src="<?= APP_URL ?>/assets/js/main.js"></script>
  <script>
    document.querySelectorAll('.expand-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var target = document.getElementById(btn.dataset.target);
        if (!target) return;
        var open = target.classList.toggle('open');
        btn.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open);
      });
    });
  </script>
</body>
</html>
