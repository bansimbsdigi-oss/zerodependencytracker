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
        <a href="<?= APP_URL ?>/dashboard">Dashboard</a>
        <a href="<?= APP_URL ?>/profile">Profile</a>
      </div>
    </div>
  </header>

  <main class="container">
    <div style="background:#fff; border-radius: var(--radius-md); padding: 2.5rem; box-shadow: var(--shadow);">

      <?php $flash = getFlash('report'); ?>
      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
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

        <form method="POST" action="<?= APP_URL ?>/audit-report/<?= (int)$sessionId ?>">
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
            <a href="<?= APP_URL ?>/dashboard" class="btn btn-secondary">Back to Dashboard</a>
          </div>
        </form>
      </div>

    </div>
  </main>

</body>
</html>
