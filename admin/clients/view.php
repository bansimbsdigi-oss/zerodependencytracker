<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('view_clients');
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!adminCanAccessClient($id)) redirect(APP_URL . '/admin/clients/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'review_feedback' && hasPermission('edit_clients')) {
        $pdo->prepare("UPDATE client_feedback SET is_reviewed=1 WHERE id=? AND user_id=?")->execute([(int)$_POST['feedback_id'], $id]);
        $clientName = $pdo->prepare("SELECT name FROM users WHERE id=?");
        $clientName->execute([$id]);
        $cname = $clientName->fetchColumn();
        teamNotify('team_feedback_reviewed', ($_SESSION['admin_name'] ?? 'Team member') . " marked feedback reviewed for client: $cname", $id);
        flash('admin', 'Feedback marked reviewed.', 'success');
        redirect(APP_URL . '/admin/clients/view.php?id=' . $id . '#feedback');
    }
    if ($action === 'change_area' && hasPermission('edit_clients')) {
        $fbId   = (int)$_POST['feedback_id'];
        $fbRow  = $pdo->prepare("SELECT cf.suggested_area_id, pa.area_name, u.name client_name FROM client_feedback cf LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id JOIN users u ON u.id=cf.user_id WHERE cf.id=? AND cf.user_id=? AND cf.suggested_area_id IS NOT NULL");
        $fbRow->execute([$fbId, $id]);
        $fbData = $fbRow->fetch();
        if ($fbData) {
            $pdo->prepare("UPDATE users SET area_id=? WHERE id=?")->execute([(int)$fbData->suggested_area_id, $id]);
            $pdo->prepare("UPDATE client_feedback SET is_reviewed=1 WHERE id=? AND user_id=?")->execute([$fbId, $id]);
            teamNotify('team_area_changed', ($_SESSION['admin_name'] ?? 'Team member') . " changed area of {$fbData->client_name} to {$fbData->area_name}", $id);
            flash('admin', 'Client area updated and feedback marked reviewed.', 'success');
        }
        redirect(APP_URL . '/admin/clients/view.php?id=' . $id . '#feedback');
    }
    redirect(APP_URL . '/admin/clients/view.php?id=' . $id . '#feedback');
}

$stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON pa.id=u.area_id WHERE u.id=?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) redirect(APP_URL . '/admin/clients/index.php');

$sessions     = [];
$sectionScores = [];
if (hasPermission('view_scores')) {
    $stmt = $pdo->prepare("
        SELECT aus.*, aw.audit_type, aw.audit_month, aw.audit_year, pa.area_name
        FROM audit_sessions aus
        JOIN audit_windows aw ON aw.id = aus.audit_window_id
        LEFT JOIN problem_areas pa ON pa.id = aus.area_id
        WHERE aus.user_id = ? AND aus.status = 'completed'
        ORDER BY aus.completed_at DESC
    ");
    $stmt->execute([$id]);
    $sessions = $stmt->fetchAll();

    if (!empty($sessions)) {
        $sessionIds   = array_column($sessions, 'id');
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));

        // Section totals
        $stmt = $pdo->prepare("
            SELECT ar.audit_session_id,
                   qs.id AS section_id,
                   qs.section_name,
                   qs.display_order,
                   SUM(ar.points_earned)       AS earned,
                   SUM(ar.max_question_points) AS max_pts
            FROM audit_responses ar
            JOIN questions q  ON q.sno = ar.question_id
            JOIN question_sections qs ON qs.id = q.section_id
            WHERE ar.audit_session_id IN ($placeholders)
              AND q.section_id IS NOT NULL
            GROUP BY ar.audit_session_id, qs.id
            ORDER BY ar.audit_session_id, qs.display_order, qs.section_name
        ");
        $stmt->execute($sessionIds);
        foreach ($stmt->fetchAll() as $row) {
            $sectionScores[$row->audit_session_id][$row->section_id] = $row;
        }

        // Individual responses grouped by session → section
        $stmt = $pdo->prepare("
            SELECT ar.audit_session_id,
                   q.section_id,
                   q.question_text,
                   q.question_type,
                   ar.points_earned,
                   ar.max_question_points,
                   ar.numeric_response,
                   ar.text_response,
                   o.option_text,
                   (SELECT GROUP_CONCAT(o2.option_text SEPARATOR ', ')
                    FROM audit_response_selections ars
                    JOIN options o2 ON o2.id = ars.option_id
                    WHERE ars.audit_response_id = ar.id) AS multi_answer
            FROM audit_responses ar
            JOIN questions q ON q.sno = ar.question_id
            LEFT JOIN options o ON o.id = ar.option_id
            WHERE ar.audit_session_id IN ($placeholders)
              AND q.section_id IS NOT NULL
            ORDER BY ar.audit_session_id, q.section_id, ar.id
        ");
        $stmt->execute($sessionIds);
        $responsesBySection = [];
        foreach ($stmt->fetchAll() as $r) {
            $responsesBySection[$r->audit_session_id][$r->section_id][] = $r;
        }
    }
}

// Stats
$totalAudits = count($sessions);
$latestScore = 0;
$improvement = null;
if ($totalAudits > 0) {
    $s0 = $sessions[0];
    $latestScore = $s0->max_score > 0 ? round(($s0->total_score / $s0->max_score) * 100) : 0;
    if ($totalAudits > 1) {
        $sl = $sessions[$totalAudits - 1];
        $oldestPct   = $sl->max_score > 0 ? round(($sl->total_score / $sl->max_score) * 100) : 0;
        $improvement = $latestScore - $oldestPct;
    }
}

// Latest score per area
$areaProgress = [];
foreach ($sessions as $s) {
    $area = $s->area_name ?? 'General';
    if (!isset($areaProgress[$area])) {
        $pct = $s->max_score > 0 ? round(($s->total_score / $s->max_score) * 100) : 0;
        $areaProgress[$area] = $pct;
    }
}

$feedbacks = $pdo->prepare("SELECT cf.*, pa.area_name suggested_area FROM client_feedback cf LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id WHERE cf.user_id=? ORDER BY cf.created_at DESC");
$feedbacks->execute([$id]);
$feedbacks = $feedbacks->fetchAll();

adminPageStart('Client: ' . $client->name, 'clients');
?>
<style>
.client-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
.client-stat  { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1rem 1.25rem; }
.client-stat-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; margin-bottom:.3rem; }
.client-stat-value { font-size:1.75rem; font-weight:800; line-height:1; }
.client-stat-sub   { font-size:.75rem; color:#9ca3af; margin-top:.2rem; }
.cv-teal   { color:#0d9488; }
.cv-green  { color:#059669; }
.cv-purple { color:#7c3aed; }

.progress-bar-wrap { margin-bottom:.875rem; }
.progress-bar-head { display:flex; justify-content:space-between; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:.3rem; }
.progress-bar-pct  { color:#0d9488; font-weight:700; }
.progress-track    { background:#f3f4f6; border-radius:9999px; height:8px; overflow:hidden; }
.progress-fill     { height:100%; border-radius:9999px; background:linear-gradient(90deg,#0d9488,#14b8a6); }
.progress-fill.pf-green  { background:linear-gradient(90deg,#059669,#34d399); }
.progress-fill.pf-warn   { background:linear-gradient(90deg,#d97706,#fbbf24); }
.progress-fill.pf-red    { background:linear-gradient(90deg,#dc2626,#f87171); }

.section-grid-admin { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.5rem 1.25rem; padding:.875rem 0 .25rem; }
.sec-item-head  { display:flex; justify-content:space-between; font-size:.73rem; font-weight:600; color:#4b5563; margin-bottom:.25rem; }
.sec-item-pct   { color:#0d9488; }
.sec-track      { background:#e5e7eb; border-radius:9999px; height:5px; overflow:hidden; }
.sec-fill       { height:100%; border-radius:9999px; background:#0d9488; }
.sec-fill.sf-green { background:#059669; }
.sec-fill.sf-warn  { background:#d97706; }
.sec-fill.sf-red   { background:#dc2626; }

.history-tbl { width:100%; border-collapse:collapse; font-size:.875rem; }
.history-tbl th { text-align:left; padding:.6rem 1rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
.history-tbl td { padding:.8rem 1rem; color:#374151; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
.history-tbl tr:last-child td { border-bottom:none; }
.history-tbl tr.data-row:hover td { background:#f9fafb; }
.sec-expand-btn { background:none; border:none; cursor:pointer; color:#0d9488; font-size:.75rem; font-weight:600; padding:0; display:inline-flex; align-items:center; gap:.25rem; }
.sec-expand-btn svg { transition:transform .2s; }
.sec-expand-btn.open svg { transform:rotate(180deg); }
.sec-breakdown-row { display:none; }
.sec-breakdown-row.open { display:table-row; }
.sec-breakdown-row td { background:#f9fafb; padding:.5rem 1rem .875rem; }
.score-pill { display:inline-block; padding:.18rem .6rem; border-radius:9999px; font-size:.78rem; font-weight:700; }
.sp-high { background:#ecfdf5; color:#065f46; }
.sp-mid  { background:#f0f9ff; color:#0369a1; }
.sp-low  { background:#fef2f2; color:#991b1b; }

/* Q&A inside section breakdown */
.qa-table { width:100%; border-collapse:collapse; margin-top:.6rem; font-size:.8rem; }
.qa-table th { text-align:left; padding:.4rem .5rem; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#9ca3af; border-bottom:1px solid #e5e7eb; background:#f9fafb; }
.qa-table td { padding:.5rem .5rem; color:#374151; border-bottom:1px solid #f9fafb; vertical-align:top; line-height:1.4; }
.qa-table tr:last-child td { border-bottom:none; }
.qa-pts { white-space:nowrap; font-weight:700; font-size:.75rem; }
.qa-pts.good { color:#059669; }
.qa-pts.bad  { color:#dc2626; }
</style>

<div style="margin-bottom:1rem;">
  <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/clients/index.php">&larr; All Clients</a>
</div>

<!-- Client info card -->
<div class="admin-table-card" style="margin-bottom:1.25rem;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
  <div>
    <div style="font-size:1.1rem;font-weight:700;color:#111827;"><?= e($client->name) ?></div>
    <div style="font-size:.85rem;color:#6b7280;margin-top:.2rem;"><?= e($client->email) ?> &middot; <?= e($client->mobile) ?></div>
    <div style="margin-top:.4rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
      <span style="font-size:.82rem;color:#374151;">Area: <strong><?= e($client->area_name ?? 'Unassigned') ?></strong></span>
      <span class="badge <?= $client->is_graduated ? 'badge-success' : 'badge-gray' ?>"><?= $client->is_graduated ? 'Graduated' : 'Active' ?></span>
    </div>
  </div>
  <?php if (hasPermission('edit_clients')): ?>
    <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/clients/edit.php?id=<?= $id ?>">Edit Client</a>
  <?php endif; ?>
</div>

<?php if (hasPermission('view_scores')): ?>

<!-- Stats row -->
<div class="client-stats">
  <div class="client-stat">
    <div class="client-stat-label">Audits Done</div>
    <div class="client-stat-value cv-teal"><?= $totalAudits ?></div>
    <div class="client-stat-sub">total completed</div>
  </div>
  <div class="client-stat">
    <div class="client-stat-label">Latest Score</div>
    <div class="client-stat-value cv-green"><?= $totalAudits > 0 ? $latestScore . '%' : '—' ?></div>
    <div class="client-stat-sub"><?= $totalAudits > 0 ? date('M Y', strtotime($sessions[0]->completed_at)) : 'no audits yet' ?></div>
  </div>
  <div class="client-stat">
    <div class="client-stat-label">Improvement</div>
    <div class="client-stat-value cv-purple"><?php
      if ($improvement === null) echo '—';
      elseif ($improvement > 0) echo '+' . $improvement . '%';
      elseif ($improvement < 0) echo $improvement . '%';
      else echo '0%';
    ?></div>
    <div class="client-stat-sub"><?= $totalAudits > 1 ? 'since first audit' : 'need 2+ audits' ?></div>
  </div>
</div>

<!-- Progress by area -->
<?php if (!empty($areaProgress)): ?>
<div class="admin-table-card" style="margin-bottom:1.25rem;padding:1.25rem 1.5rem;">
  <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:1rem;">Progress by Area</div>
  <?php foreach ($areaProgress as $areaName => $pct):
    $pfClass = $pct >= 75 ? 'pf-green' : ($pct >= 40 ? '' : ($pct >= 20 ? 'pf-warn' : 'pf-red'));
  ?>
    <div class="progress-bar-wrap">
      <div class="progress-bar-head">
        <span><?= e($areaName) ?></span>
        <span class="progress-bar-pct"><?= $pct ?>%</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill <?= $pfClass ?>" style="width:<?= $pct ?>%;"></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Audit history -->
<?php if (!$sessions): ?>
  <p class="text-muted">No completed audits.</p>
<?php else: ?>
<div class="admin-table-card" style="margin-bottom:1.5rem;overflow:hidden;">
  <div style="padding:.875rem 1.25rem;border-bottom:1px solid #f3f4f6;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;">Audit History</div>
  <table class="history-tbl">
    <thead>
      <tr>
        <th>Window</th>
        <th>Area</th>
        <th>Score</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sessions as $s):
        $pct = $s->max_score > 0 ? round(($s->total_score / $s->max_score) * 100) : 0;
        $spClass  = $pct >= 75 ? 'sp-high' : ($pct >= 50 ? 'sp-mid' : 'sp-low');
        $monthName = date('M', mktime(0,0,0,(int)$s->audit_month,1));
      ?>
      <tr class="data-row">
        <td>
          <strong><?= e(ucwords(str_replace('_',' ',$s->audit_type))) ?></strong><br>
          <span style="font-size:.75rem;color:#9ca3af;"><?= $monthName ?> <?= e($s->audit_year) ?></span>
        </td>
        <td><?= e($s->area_name ?? 'General') ?></td>
        <td><span class="score-pill <?= $spClass ?>"><?= $pct ?>%</span></td>
        <td style="font-size:.8rem;color:#9ca3af;"><?= $s->completed_at ? e(date('d M Y', strtotime($s->completed_at))) : '-' ?></td>
        <td style="text-align:right;">
          <a href="<?= APP_URL ?>/admin/audits/report.php?session_id=<?= $s->id ?>&client_id=<?= $id ?>" class="btn btn-secondary btn-sm">Report</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php endif; // hasPermission('view_scores') ?>

<!-- Feedback -->
<div id="feedback" style="margin-bottom:.5rem;font-size:1rem;font-weight:700;color:#111827;">Client Feedback</div>
<?php if (!$feedbacks): ?>
  <p class="text-muted" style="font-size:.875rem;">No feedback submitted.</p>
<?php else: ?>
<div class="admin-table-card" style="margin-bottom:1.5rem;overflow:hidden;">
  <table class="table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Suggested Area</th>
        <th>Notes</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($feedbacks as $f): ?>
      <tr id="feedback-<?= $f->id ?>">
        <td style="white-space:nowrap;"><?= e(date('d M Y', strtotime($f->created_at))) ?></td>
        <td><?= e($f->suggested_area ?? '-') ?></td>
        <td><?= e($f->feedback_text ?? '-') ?></td>
        <td><span class="badge <?= $f->is_reviewed ? 'badge-success' : 'badge-warning' ?>"><?= $f->is_reviewed ? 'Reviewed' : 'Pending' ?></span></td>
        <td>
          <?php if (!$f->is_reviewed && hasPermission('edit_clients')): ?>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
                <input type="hidden" name="action" value="review_feedback">
                <input type="hidden" name="feedback_id" value="<?= $f->id ?>">
                <button class="btn btn-primary btn-sm">Mark Reviewed</button>
              </form>
              <?php if ($f->suggested_area_id): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Change area to &quot;<?= e(addslashes($f->suggested_area)) ?>&quot;?');">
                  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
                  <input type="hidden" name="action" value="change_area">
                  <input type="hidden" name="feedback_id" value="<?= $f->id ?>">
                  <button class="btn btn-secondary btn-sm">Set Area</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>


<?php adminPageEnd(); ?>
