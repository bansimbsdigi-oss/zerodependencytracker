<?php
// audit-report.php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = currentUserId();
$pdo = getDB();

$sessionId = $_GET['session_id'] ?? null;
if (!$sessionId) redirect(APP_URL . '/dashboard.php');

// Fetch session and verify ownership
$stmt = $pdo->prepare("
    SELECT aus.*, pa.area_name, aw.audit_type, aw.audit_month, aw.audit_year
    FROM audit_sessions aus
    JOIN audit_windows aw ON aus.audit_window_id = aw.id
    LEFT JOIN problem_areas pa ON aus.area_id = pa.id
    WHERE aus.id = ? AND aus.user_id = ? AND aus.status = 'completed'
");
$stmt->execute([$sessionId, $userId]);
$session = $stmt->fetch();

if (!$session) redirect(APP_URL . '/dashboard.php');

$percentage = $session->max_score > 0 ? round(($session->total_score / $session->max_score) * 100) : 0;

// Fetch previous session in same area to compare
$stmt = $pdo->prepare("
    SELECT total_score, max_score FROM audit_sessions
    WHERE user_id = ? AND area_id = ? AND status = 'completed' AND id != ?
      AND (completed_at < ? OR (completed_at = ? AND id < ?))
    ORDER BY completed_at DESC, id DESC LIMIT 1
");
$stmt->execute([$userId, $session->area_id, $sessionId, $session->completed_at, $session->completed_at, $sessionId]);
$prevSession = $stmt->fetch();

$diffStr = '';
if ($prevSession) {
    $prevPercentage = $prevSession->max_score > 0 ? round(($prevSession->total_score / $prevSession->max_score) * 100) : 0;
    $diff = $percentage - $prevPercentage;
    if ($diff > 0) {
        $diffStr = "<span style='color:var(--success); font-weight:bold;'>&uarr; $diff% improvement</span> since last audit.";
    } elseif ($diff < 0) {
        $absDiff = abs($diff);
        $diffStr = "<span style='color:var(--danger); font-weight:bold;'>&darr; $absDiff% decrease</span> since last audit.";
    } else {
        $diffStr = "<span style='color:var(--gray-500); font-weight:bold;'>No change</span> since last audit.";
    }
}

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $suggestedAreaId = empty($_POST['suggested_area_id']) ? null : $_POST['suggested_area_id'];
    $feedbackText = trim($_POST['feedback_text'] ?? '');
    
    if (!empty($suggestedAreaId) || !empty($feedbackText)) {
        $exists = $pdo->prepare("SELECT id FROM client_feedback WHERE user_id = ? AND audit_session_id = ?");
        $exists->execute([$userId, $sessionId]);
        if ($exists->fetch()) {
            $flash = 'Feedback was already submitted for this audit.';
        } else {
            if ($suggestedAreaId) {
                $areaCheck = $pdo->prepare("SELECT id FROM problem_areas WHERE id = ? AND is_active = 1");
                $areaCheck->execute([$suggestedAreaId]);
                if (!$areaCheck->fetch()) $suggestedAreaId = null;
            }
            $stmt = $pdo->prepare("INSERT INTO client_feedback (user_id, audit_session_id, suggested_area_id, feedback_text) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $sessionId, $suggestedAreaId, $feedbackText]);
            $feedbackId = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO admin_notifications (type, message, related_user_id, related_audit_session_id, related_feedback_id) VALUES ('area_feedback', 'New client feedback submitted', ?, ?, ?)")
                ->execute([$userId, $sessionId, $feedbackId]);

            $flash = 'Feedback submitted successfully. Thank you!';
        }
    }
}

// Fetch areas for feedback dropdown
$areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Report — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

  <header class="site-header">
    <div class="container">
      <a href="<?= APP_URL ?>" class="brand">&#x2665; <?= APP_NAME ?></a>
      <div class="nav-links">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <a href="<?= APP_URL ?>/profile.php">Profile</a>
      </div>
    </div>
  </header>

  <main class="container">
    <div style="background:#fff; border-radius: var(--radius-md); padding: 2.5rem; box-shadow: var(--shadow);">
      
      <?php if ($flash): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
      <?php endif; ?>

      <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; color: <?= $percentage >= 80 ? 'var(--success)' : 'var(--primary)' ?>;">
          <?= $percentage ?>%
        </h1>
        <p class="text-muted" style="font-size: 1.1rem;">
          <?= e($session->area_name) ?> Score<br>
          <small><?= ucwords(str_replace('_', ' ', $session->audit_type)) ?> (<?= e($session->audit_month) ?>/<?= e($session->audit_year) ?>)</small>
        </p>
        <?php if ($diffStr): ?>
          <p style="margin-top: 1rem;"><?= $diffStr ?></p>
        <?php endif; ?>
      </div>

      <hr style="border: 0; border-top: 1px solid var(--gray-200); margin: 2rem 0;">

      <div style="background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius);">
        <h3 style="margin-bottom: 0.5rem;">💬 Any other concerns?</h3>
        <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1rem;">Let your coach know if you have another area of concern.</p>
        
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          
          <div class="form-group">
            <label class="form-label">Area of Concern (Optional)</label>
            <select name="suggested_area_id" class="form-control">
              <option value="">-- Select --</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= $area->id ?>"><?= e($area->area_name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label">Notes (Optional)</label>
            <textarea name="feedback_text" class="form-control" rows="3" placeholder="Describe your concern..."></textarea>
          </div>
          
          <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Submit Feedback</button>
            <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
          </div>
        </form>
      </div>

    </div>
  </main>

</body>
</html>
