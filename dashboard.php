<?php
// dashboard.php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = currentUserId();
$pdo = getDB();

// Fetch user
$stmt = $pdo->prepare("SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON u.area_id = pa.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Check for active audits that are not yet completed or in progress for this user
$stmt = $pdo->prepare("
    SELECT aw.*, aus.id AS session_id, aus.status AS session_status
    FROM audit_windows aw
    LEFT JOIN audit_sessions aus ON aus.audit_window_id = aw.id AND aus.user_id = ?
    WHERE aw.is_open = 1 AND (aus.id IS NULL OR aus.status = 'in_progress')
");
$stmt->execute([$userId]);
$activeAudits = $stmt->fetchAll();

// Fetch completed audit sessions for history
$stmt = $pdo->prepare("
    SELECT aus.*, aw.audit_type, aw.audit_month, aw.audit_year, pa.area_name 
    FROM audit_sessions aus
    JOIN audit_windows aw ON aus.audit_window_id = aw.id
    LEFT JOIN problem_areas pa ON aus.area_id = pa.id
    WHERE aus.user_id = ? AND aus.status = 'completed'
    ORDER BY aus.completed_at DESC
");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

// Group history by area
$historyByArea = [];
foreach ($history as $h) {
    $areaName = $h->area_name ?? 'General';
    if (!isset($historyByArea[$areaName])) {
        $historyByArea[$areaName] = [];
    }
    $historyByArea[$areaName][] = $h;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <meta name="csrf-token" content="<?= e(getCsrf()) ?>">
  <style>
    /* Tutorial Overlay CSS */
    .tutorial-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .tutorial-modal { background: #fff; padding: 2rem; border-radius: var(--radius-md); max-width: 500px; text-align: center; }
    
    .grad-banner { background: linear-gradient(135deg, var(--success), #047857); color: #fff; padding: 2rem; border-radius: var(--radius-md); margin-bottom: 2rem; text-align: center; }
    
    .audit-cta { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: 1.5rem 2rem; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .audit-cta h3 { color: #fff; margin-bottom: 0.25rem; }
    .audit-cta-btn { background: #fff; color: var(--primary); padding: 0.5rem 1.5rem; border-radius: var(--radius-full); font-weight: bold; text-decoration: none; }
    
    details.history-details summary::-webkit-details-marker { display: none; }
    details.history-details[open] summary span:last-child { transform: rotate(180deg); }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="container">
      <a href="<?= APP_URL ?>" class="brand">&#x2665; <?= APP_NAME ?></a>
      <div class="nav-links">
        <a href="<?= APP_URL ?>/dashboard.php" class="active">Dashboard</a>
        <a href="<?= APP_URL ?>/profile.php">Profile</a>
        <form method="POST" action="<?= APP_URL ?>/logout.php" style="display:inline;margin-left:1rem;">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <button type="submit" class="btn btn-primary" style="padding:0.4rem 1rem;border:0;cursor:pointer;">Logout</button>
        </form>
      </div>
    </div>
  </header>

  <main class="container">
    <h1 style="margin-top: 1rem;">Welcome, <?= e(explode(' ', $user->name)[0]) ?></h1>
    <p class="text-muted" style="margin-bottom: 2rem;">Track your progress and complete your scheduled audits.</p>

    <?php if ($user->is_graduated == 1): ?>
      <div class="grad-banner">
        <h2 style="color: #fff;">🎉 Congratulations!</h2>
        <p>You have hit 100% performance and graduated from your primary recovery program.</p>
      </div>
    <?php endif; ?>

    <?php if (!$user->is_graduated && !empty($activeAudits)): ?>
      <h2 style="margin-bottom: 1rem;">Action Required</h2>
      <?php foreach ($activeAudits as $audit): ?>
        <div class="audit-cta">
          <div>
            <h3><?= ucwords(str_replace('_', ' ', $audit->audit_type)) ?> Audit</h3>
            <p style="opacity: 0.9;">Available for Month <?= e($audit->audit_month) ?>, <?= e($audit->audit_year) ?></p>
          </div>
          <a href="<?= APP_URL ?>/audit.php?window_id=<?= $audit->id ?>" class="audit-cta-btn"><?= $audit->session_status === 'in_progress' ? 'Continue' : 'Start' ?> Audit &rarr;</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <h2 style="margin-top: 3rem; margin-bottom: 1.5rem;">Audit History</h2>
    <?php if (empty($historyByArea)): ?>
      <p class="text-muted">You haven't completed any audits yet.</p>
    <?php else: ?>
      <?php foreach ($historyByArea as $area => $records): ?>
        <div class="card" style="margin-bottom: 1rem;">
          <details class="history-details">
            <summary class="card-header" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none;">
              <span><?= e($area) ?> Progress</span>
              <span class="text-muted" style="font-size: 0.9rem;">(<?= count($records) ?> Audits) ▼</span>
            </summary>
            <div class="card-body" style="border-top: 1px solid var(--gray-200);">
              <table style="width:100%; border-collapse: collapse;">
                <?php 
                  for ($i = 0; $i < count($records); $i++) {
                    $record = $records[$i];
                    $percentage = $record->max_score > 0 ? round(($record->total_score / $record->max_score) * 100) : 0;
                    
                    // Calculate improvement vs previous (which is the next item in the array since it's sorted DESC)
                    $diffStr = '';
                    if (isset($records[$i + 1])) {
                        $prevRecord = $records[$i + 1];
                        $prevPercentage = $prevRecord->max_score > 0 ? round(($prevRecord->total_score / $prevRecord->max_score) * 100) : 0;
                        $diff = $percentage - $prevPercentage;
                        if ($diff > 0) {
                            $diffStr = "<span style='color:var(--success); font-size: 0.9rem; font-weight: bold;'>+$diff%</span>";
                        } elseif ($diff < 0) {
                            $diffStr = "<span style='color:var(--danger); font-size: 0.9rem; font-weight: bold;'>$diff%</span>";
                        } else {
                            $diffStr = "<span style='color:var(--gray-500); font-size: 0.9rem;'>No change</span>";
                        }
                    } else {
                        $diffStr = "<span style='color:var(--gray-500); font-size: 0.9rem;'>First audit</span>";
                    }
                ?>
                  <tr style="border-bottom: 1px solid var(--gray-200);">
                    <td style="padding: 1rem 0;">
                      <strong><?= ucwords(str_replace('_', ' ', $record->audit_type)) ?></strong> (<?= e($record->audit_month) ?>/<?= e($record->audit_year) ?>)<br>
                      <span class="text-muted" style="font-size:0.85rem;"><?= date('M j, Y', strtotime($record->completed_at)) ?></span>
                    </td>
                    <td style="padding: 1rem 0; text-align: center; vertical-align: middle;">
                      <?= $diffStr ?>
                    </td>
                    <td style="padding: 1rem 0; text-align: right;">
                      <div style="font-size: 1.25rem; font-weight: 700; color: <?= $percentage >= 80 ? 'var(--success)' : 'var(--primary)' ?>;">
                        <?= $percentage ?>%
                      </div>
                      <a href="<?= APP_URL ?>/audit-report.php?session_id=<?= $record->id ?>" style="font-size:0.85rem;">View Report</a>
                    </td>
                  </tr>
                <?php } ?>
              </table>
            </div>
          </details>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <?php if ($user->tutorial_done == 0 && empty($history)): ?>
    <div id="tutorial-overlay" class="tutorial-overlay">
      <div class="tutorial-modal">
        <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">👋</div>
        <h2 style="margin-bottom: 1rem;">Welcome to your Dashboard</h2>
        <p style="color: var(--gray-600); margin-bottom: 2rem;">
          This is where you will track your physiotherapy progress. Your coach will open audits (questionnaires) at the middle and end of every month. Complete them to see your performance chart rise!
        </p>
        <button id="btn-tutorial-done" class="btn btn-primary btn-block">Got it, let's start</button>
      </div>
    </div>
  <?php endif; ?>

  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
  <script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
