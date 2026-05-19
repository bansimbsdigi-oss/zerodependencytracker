<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/whatsapp.php';
requirePermission('view_scores');
$pdo = getDB();

$sessionId = (int)($_GET['session_id'] ?? 0);
$clientId  = (int)($_GET['client_id']  ?? 0);
if (!$sessionId || !adminCanAccessClient($clientId)) {
    redirect(APP_URL . '/admin/clients/index.php');
}

// Handle feedback save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fbText = trim($_POST['admin_feedback'] ?? '');
    $pdo->prepare("UPDATE audit_sessions SET admin_feedback=?, admin_feedback_by=?, admin_feedback_at=NOW() WHERE id=?")
        ->execute([$fbText ?: null, $_SESSION['admin_id'], $sessionId]);

    if ($fbText) {
        // Fetch client and audit details for notification
        $clientRow = $pdo->prepare("
            SELECT u.name, u.mobile, aw.audit_type, aw.audit_month, aw.audit_year
            FROM users u
            JOIN audit_sessions aus ON aus.user_id = u.id
            JOIN audit_windows aw ON aw.id = aus.audit_window_id
            WHERE aus.id = ?
        ");
        $clientRow->execute([$sessionId]);
        $clientData = $clientRow->fetch();

        if ($clientData) {
            $firstName      = explode(' ', trim($clientData->name))[0] ?: 'there';
            $auditTypeLabel = $clientData->audit_type === 'mid_month' ? 'Mid Month' : 'Month End';
            $auditMonthYear = date('F Y', mktime(0, 0, 0, (int)$clientData->audit_month, 1, (int)$clientData->audit_year));
            sendWhatsAppAuditFeedback($clientData->mobile, $firstName, $auditTypeLabel, $auditMonthYear, $fbText);

            teamNotify('team_feedback_saved', ($_SESSION['admin_name'] ?? 'Team member') . " saved coach feedback for client: {$clientData->name}", $clientId, $sessionId);
        }
    }

    flash('admin', 'Feedback saved and client notified on WhatsApp.', 'success');
    redirect(APP_URL . '/admin/audits/report.php?session_id=' . $sessionId . '&client_id=' . $clientId);
}

$stmt = $pdo->prepare("
    SELECT aus.*, pa.area_name, aw.audit_type, aw.audit_month, aw.audit_year,
           u.name AS client_name, u.id AS client_id,
           au.name AS feedback_by_name
    FROM audit_sessions aus
    JOIN audit_windows aw ON aw.id = aus.audit_window_id
    LEFT JOIN problem_areas pa ON pa.id = aus.area_id
    JOIN users u ON u.id = aus.user_id
    LEFT JOIN admin_users au ON au.id = aus.admin_feedback_by
    WHERE aus.id = ? AND aus.user_id = ? AND aus.status = 'completed'
");
$stmt->execute([$sessionId, $clientId]);
$session = $stmt->fetch();
if (!$session) redirect(APP_URL . '/admin/clients/index.php');

$percentage = $session->max_score > 0 ? round(($session->total_score / $session->max_score) * 100) : 0;

// Previous session for comparison
$prev = $pdo->prepare("
    SELECT total_score, max_score FROM audit_sessions
    WHERE user_id=? AND area_id=? AND status='completed' AND id!=?
      AND (completed_at < ? OR (completed_at = ? AND id < ?))
    ORDER BY completed_at DESC, id DESC LIMIT 1
");
$prev->execute([$clientId, $session->area_id, $sessionId, $session->completed_at, $session->completed_at, $sessionId]);
$prevSession = $prev->fetch();
$diff = null;
if ($prevSession && $prevSession->max_score > 0) {
    $prevPct = round(($prevSession->total_score / $prevSession->max_score) * 100);
    $diff    = $percentage - $prevPct;
}

// Section scores
$secStmt = $pdo->prepare("
    SELECT qs.id AS section_id, qs.section_name, qs.display_order,
           SUM(ar.points_earned)       AS earned,
           SUM(ar.max_question_points) AS max_pts
    FROM audit_responses ar
    JOIN questions q ON q.sno = ar.question_id
    JOIN question_sections qs ON qs.id = q.section_id
    WHERE ar.audit_session_id = ? AND q.section_id IS NOT NULL
    GROUP BY qs.id
    ORDER BY qs.display_order, qs.section_name
");
$secStmt->execute([$sessionId]);
$sections = $secStmt->fetchAll();

// Responses grouped by section
$respStmt = $pdo->prepare("
    SELECT ar.*, q.question_text, q.question_type, q.section_id,
           qs.section_name, qs.display_order AS sec_order,
           o.option_text,
           (SELECT GROUP_CONCAT(o2.option_text SEPARATOR ', ')
            FROM audit_response_selections ars
            JOIN options o2 ON o2.id = ars.option_id
            WHERE ars.audit_response_id = ar.id) AS multi_answer
    FROM audit_responses ar
    JOIN questions q ON q.sno = ar.question_id
    LEFT JOIN question_sections qs ON qs.id = q.section_id
    LEFT JOIN options o ON o.id = ar.option_id
    WHERE ar.audit_session_id = ?
    ORDER BY qs.display_order, qs.section_name, ar.id
");
$respStmt->execute([$sessionId]);
$responsesBySection = [];
foreach ($respStmt->fetchAll() as $r) {
    $key = $r->section_name ?? 'General';
    $responsesBySection[$key][] = $r;
}

// Client feedback
$fbStmt = $pdo->prepare("SELECT cf.*, pa.area_name suggested_area FROM client_feedback cf LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id WHERE cf.audit_session_id=?");
$fbStmt->execute([$sessionId]);
$feedback = $fbStmt->fetch();

$monthName = date('M', mktime(0,0,0,(int)$session->audit_month,1));
$typeName  = ucwords(str_replace('_', ' ', $session->audit_type));
$pageTitle = $typeName . ' Report — ' . $session->client_name;

adminPageStart($pageTitle, 'clients');
?>
<style>
/* ── Layout ─────────────────────────────────────────── */
.report-tabs        { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:1.5rem; }
.report-tab         { padding:.65rem 1.5rem; font-size:.88rem; font-weight:600; color:#6b7280; border-bottom:2px solid transparent; margin-bottom:-2px; cursor:pointer; transition:color .15s,border-color .15s; }
.report-tab.active  { color:#0d9488; border-bottom-color:#0d9488; }
.report-tab:hover:not(.active) { color:#374151; }
.report-panel       { display:none; }
.report-panel.active{ display:block; }

/* ── Hero ───────────────────────────────────────────── */
.report-hero        { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1.75rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:1.75rem; flex-wrap:wrap; }
.score-circle       { width:96px; height:96px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.6rem; font-weight:900; }
.sc-high { background:#ecfdf5; color:#059669; }
.sc-mid  { background:#f0f9ff; color:#0369a1; }
.sc-low  { background:#fef2f2; color:#dc2626; }
.hero-meta h2 { font-size:1.1rem; font-weight:700; color:#111827; margin:0 0 .25rem; }
.hero-meta p  { font-size:.85rem; color:#6b7280; margin:0 0 .35rem; }
.diff-up   { color:#059669; font-weight:700; }
.diff-down { color:#dc2626; font-weight:700; }
.diff-flat { color:#9ca3af; font-weight:700; }

/* ── Stats row ──────────────────────────────────────── */
.report-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.25rem; }
.rstat        { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1rem 1.25rem; }
.rstat-label  { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; margin-bottom:.3rem; }
.rstat-value  { font-size:1.5rem; font-weight:800; line-height:1; }
.rv-teal   { color:#0d9488; }
.rv-green  { color:#059669; }
.rv-red    { color:#dc2626; }
.rv-gray   { color:#6b7280; }

/* ── Section bars ───────────────────────────────────── */
.section-bars       { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.25rem; }
.section-bars-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; margin-bottom:1.1rem; }
.sbar-row      { margin-bottom:.875rem; }
.sbar-row:last-child { margin-bottom:0; }
.sbar-head     { display:flex; justify-content:space-between; align-items:baseline; font-size:.84rem; font-weight:600; color:#374151; margin-bottom:.3rem; }
.sbar-pct      { color:#0d9488; font-weight:700; font-size:.84rem; }
.sbar-pts      { font-size:.73rem; color:#9ca3af; font-weight:400; margin-left:.4rem; }
.sbar-track    { background:#f3f4f6; border-radius:9999px; height:8px; overflow:hidden; }
.sbar-fill     { height:100%; border-radius:9999px; background:linear-gradient(90deg,#0d9488,#14b8a6); }
.sbar-fill.sg  { background:linear-gradient(90deg,#059669,#34d399); }
.sbar-fill.sw  { background:linear-gradient(90deg,#d97706,#fbbf24); }
.sbar-fill.sr  { background:linear-gradient(90deg,#dc2626,#f87171); }

/* ── Client feedback ────────────────────────────────── */
.client-fb  { background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.25rem; }
.client-fb-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#92400e; margin-bottom:.4rem; }

/* ── Coach feedback form ────────────────────────────── */
.coach-fb   { background:#fff; border:1.5px solid #c7d2fe; border-radius:12px; overflow:hidden; margin-bottom:1.25rem; }
.coach-fb-head { padding:.875rem 1.25rem; border-bottom:1px solid #e0e7ff; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; }
.coach-fb-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#4f46e5; }
.coach-fb-body  { padding:1.25rem; }

/* ── Q&A Section ────────────────────────────────────── */
.qa-section-card    { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:1rem; overflow:hidden; }
.qa-section-head    { padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; cursor:pointer; user-select:none; border-bottom:1px solid transparent; transition:background .12s; }
.qa-section-head:hover { background:#f9fafb; }
.qa-section-head.open  { border-bottom-color:#f3f4f6; }
.qa-section-head svg   { transition:transform .2s; flex-shrink:0; }
.qa-section-head.open svg { transform:rotate(180deg); }
.qa-section-title   { font-size:.88rem; font-weight:700; color:#111827; }
.qa-section-meta    { font-size:.75rem; color:#9ca3af; margin-left:.5rem; font-weight:400; }
.qa-section-badge   { font-size:.75rem; font-weight:700; padding:.18rem .6rem; border-radius:9999px; margin-right:.75rem; }
.qsb-high { background:#ecfdf5; color:#065f46; }
.qsb-mid  { background:#f0f9ff; color:#0369a1; }
.qsb-low  { background:#fef2f2; color:#991b1b; }
.qa-body   { display:none; }
.qa-body.open { display:block; }

.q-row  { display:grid; grid-template-columns:1fr 160px 72px; gap:.5rem 1rem; align-items:start; padding:.8rem 1.25rem; border-bottom:1px solid #f9fafb; font-size:.84rem; }
.q-row:last-child { border-bottom:none; }
.q-num  { font-size:.7rem; font-weight:700; color:#9ca3af; margin-bottom:.2rem; }
.q-text { color:#374151; line-height:1.45; }
.q-ans  { color:#111827; font-weight:600; font-size:.83rem; }
.q-pts  { font-size:.75rem; color:#9ca3af; text-align:right; font-weight:600; white-space:nowrap; }
.q-pts.good { color:#059669; }
.q-pts.bad  { color:#dc2626; }
</style>

<div style="margin-bottom:1rem;">
  <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/clients/view.php?id=<?= $clientId ?>">&larr; Back to <?= e($session->client_name) ?></a>
</div>

<!-- Tab switcher -->
<div class="report-tabs">
  <div class="report-tab active" data-tab="analytics">Analytics</div>
  <div class="report-tab" data-tab="qa">Questions &amp; Answers <?php if(!empty($responsesBySection)): ?><span style="font-size:.72rem;color:#9ca3af;font-weight:400;">(<?= array_sum(array_map('count', $responsesBySection)) ?> questions)</span><?php endif; ?></div>
</div>

<!-- ═══════════════════════════════════════════════
     PANEL 1 — ANALYTICS
     ═══════════════════════════════════════════════ -->
<div class="report-panel active" id="panel-analytics">

  <!-- Hero -->
  <div class="report-hero">
    <?php $scClass = $percentage >= 75 ? 'sc-high' : ($percentage >= 50 ? 'sc-mid' : 'sc-low'); ?>
    <div class="score-circle <?= $scClass ?>"><?= $percentage ?>%</div>
    <div class="hero-meta">
      <h2><?= e($session->client_name) ?> &mdash; <?= e($typeName) ?></h2>
      <p><?= e($session->area_name ?? 'General') ?> &middot; <?= $monthName ?> <?= e($session->audit_year) ?> &middot; <?= $session->completed_at ? e(date('d M Y', strtotime($session->completed_at))) : '' ?></p>
      <p>Score: <strong><?= $session->total_score ?> / <?= $session->max_score ?> points</strong></p>
      <?php if ($diff !== null): ?>
        <?php if ($diff > 0): ?><span class="diff-up">&uarr; +<?= $diff ?>% improvement</span> vs previous audit
        <?php elseif ($diff < 0): ?><span class="diff-down">&darr; <?= $diff ?>% decrease</span> vs previous audit
        <?php else: ?><span class="diff-flat">No change</span> vs previous audit
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats row -->
  <div class="report-stats">
    <div class="rstat">
      <div class="rstat-label">Overall Score</div>
      <div class="rstat-value <?= $percentage >= 75 ? 'rv-green' : ($percentage >= 50 ? 'rv-teal' : 'rv-red') ?>"><?= $percentage ?>%</div>
    </div>
    <div class="rstat">
      <div class="rstat-label">Points Earned</div>
      <div class="rstat-value rv-teal"><?= $session->total_score ?><span style="font-size:.9rem;font-weight:500;color:#9ca3af;"> / <?= $session->max_score ?></span></div>
    </div>
    <div class="rstat">
      <div class="rstat-label">vs Previous</div>
      <div class="rstat-value <?= $diff === null ? 'rv-gray' : ($diff > 0 ? 'rv-green' : ($diff < 0 ? 'rv-red' : 'rv-gray')) ?>">
        <?php if ($diff === null) echo '—';
        elseif ($diff > 0) echo '+' . $diff . '%';
        elseif ($diff < 0) echo $diff . '%';
        else echo '0%'; ?>
      </div>
    </div>
  </div>

  <!-- Section breakdown bars -->
  <?php if (!empty($sections)): ?>
  <div class="section-bars">
    <div class="section-bars-title">Section Breakdown</div>
    <?php foreach ($sections as $sec):
      $sPct  = $sec->max_pts > 0 ? round(($sec->earned / $sec->max_pts) * 100) : 0;
      $sfCls = $sPct >= 75 ? 'sg' : ($sPct >= 40 ? '' : ($sPct >= 20 ? 'sw' : 'sr'));
    ?>
      <div class="sbar-row">
        <div class="sbar-head">
          <span><?= e($sec->section_name) ?></span>
          <span>
            <span class="sbar-pct"><?= $sPct ?>%</span>
            <span class="sbar-pts">(<?= $sec->earned ?>/<?= $sec->max_pts ?>)</span>
          </span>
        </div>
        <div class="sbar-track">
          <div class="sbar-fill <?= $sfCls ?>" style="width:<?= $sPct ?>%;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Client feedback -->
  <?php if ($feedback): ?>
  <div class="client-fb">
    <div class="client-fb-title">Client Feedback</div>
    <?php if ($feedback->suggested_area): ?><p style="font-size:.85rem;margin-bottom:.3rem;">Suggested Area: <strong><?= e($feedback->suggested_area) ?></strong></p><?php endif; ?>
    <?php if ($feedback->feedback_text): ?><p style="font-size:.85rem;color:#374151;margin:0;"><?= e($feedback->feedback_text) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Coach feedback form -->
  <div class="coach-fb">
    <div class="coach-fb-head">
      <div>
        <span class="coach-fb-label">Coach Feedback to Client</span>
        <?php if ($session->admin_feedback_at): ?>
          <span style="font-size:.73rem;color:#9ca3af;margin-left:.75rem;">Last saved by <?= e($session->feedback_by_name ?? 'Admin') ?> on <?= e(date('d M Y', strtotime($session->admin_feedback_at))) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($session->admin_feedback): ?>
        <span style="font-size:.72rem;background:#ecfdf5;color:#065f46;padding:.15rem .65rem;border-radius:9999px;font-weight:700;">Visible to client</span>
      <?php else: ?>
        <span style="font-size:.72rem;background:#f3f4f6;color:#9ca3af;padding:.15rem .65rem;border-radius:9999px;font-weight:700;">Not yet sent</span>
      <?php endif; ?>
    </div>
    <div class="coach-fb-body">
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
        <textarea name="admin_feedback" rows="4" style="width:100%;padding:.75rem 1rem;border:1.5px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:.9rem;resize:vertical;line-height:1.6;color:#374151;" placeholder="Write coaching notes, observations, or recommendations for this client..."><?= e($session->admin_feedback ?? '') ?></textarea>
        <div style="margin-top:.75rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary btn-sm">Save &amp; Publish to Client</button>
          <?php if ($session->admin_feedback): ?>
            <button type="submit" name="admin_feedback" value="" class="btn btn-secondary btn-sm" onclick="return confirm('Remove feedback so client can no longer see it?');">Remove</button>
          <?php endif; ?>
          <span style="font-size:.78rem;color:#9ca3af;">Visible to client on their dashboard.</span>
        </div>
      </form>
    </div>
  </div>

</div><!-- /panel-analytics -->


<!-- ═══════════════════════════════════════════════
     PANEL 2 — QUESTIONS & ANSWERS
     ═══════════════════════════════════════════════ -->
<div class="report-panel" id="panel-qa">

  <?php if (empty($responsesBySection)): ?>
    <div style="text-align:center;padding:3rem;color:#9ca3af;font-size:.875rem;">No responses recorded for this audit.</div>
  <?php endif; ?>

  <?php
  $qCounter = 0;
  foreach ($responsesBySection as $secName => $responses):
    // Get section score for badge
    $secEarned = 0; $secMax = 0;
    foreach ($responses as $r) {
      $secEarned += (int)$r->points_earned;
      $secMax    += (int)$r->max_question_points;
    }
    $secPct   = $secMax > 0 ? round(($secEarned / $secMax) * 100) : 0;
    $badgeCls = $secPct >= 75 ? 'qsb-high' : ($secPct >= 50 ? 'qsb-mid' : 'qsb-low');
  ?>
  <div class="qa-section-card">
    <div class="qa-section-head" onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
      <div>
        <span class="qa-section-title"><?= e($secName) ?></span>
        <span class="qa-section-meta"><?= count($responses) ?> questions</span>
      </div>
      <div style="display:flex;align-items:center;">
        <span class="qa-section-badge <?= $badgeCls ?>"><?= $secPct ?>%</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
    </div>
    <div class="qa-body">
      <?php foreach ($responses as $r):
        $qCounter++;
        $isGood = $r->max_question_points > 0 && ($r->points_earned / $r->max_question_points) >= 0.75;
        $isBad  = $r->max_question_points > 0 && ($r->points_earned / $r->max_question_points) < 0.4;
        if ($r->question_type === 'text')              $ans = $r->text_response ?? '-';
        elseif ($r->question_type === 'rating')        $ans = 'Rating: ' . ($r->numeric_response ?? '-');
        elseif ($r->question_type === 'multi_select')  $ans = $r->multi_answer ?: '-';
        else                                           $ans = $r->option_text ?? '-';
      ?>
        <div class="q-row">
          <div>
            <div class="q-num">Q<?= $qCounter ?></div>
            <div class="q-text"><?= e($r->question_text) ?></div>
          </div>
          <div class="q-ans"><?= e($ans) ?></div>
          <div class="q-pts <?= $isGood ? 'good' : ($isBad ? 'bad' : '') ?>"><?= (int)$r->points_earned ?>/<?= (int)$r->max_question_points ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

</div><!-- /panel-qa -->

<script>
document.querySelectorAll('.report-tab').forEach(function(tab) {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.report-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.report-panel').forEach(function(p) { p.classList.remove('active'); });
    tab.classList.add('active');
    document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
  });
});
</script>

<?php adminPageEnd(); ?>
