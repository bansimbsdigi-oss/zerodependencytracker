<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/whatsapp.php';
requirePermission('view_scores');
$pdo = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $type      = $_POST['audit_type']  ?? '';
    $month     = (int)($_POST['audit_month'] ?? 0);
    $year      = (int)($_POST['audit_year']  ?? 0);
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date']   ?? '';

    if (!in_array($type, ['mid_month','month_end'], true)) $errors[] = 'Select a valid audit type.';
    if ($month < 1 || $month > 12)                        $errors[] = 'Select a valid month.';
    if ($year < 2020 || $year > 2100)                     $errors[] = 'Select a valid year.';
    if (empty($startDate))                                 $errors[] = 'Start date is required.';
    if (empty($endDate))                                   $errors[] = 'End date is required.';
    if ($startDate && $endDate && $endDate <= $startDate)  $errors[] = 'End date must be after start date.';

    if (!$errors) {
        try {
            $pdo->prepare("INSERT INTO audit_windows (audit_type, audit_month, audit_year, start_date, end_date, opened_by, is_open) VALUES (?, ?, ?, ?, ?, ?, 1)")
                ->execute([$type, $month, $year, $startDate, $endDate, $_SESSION['admin_id']]);

            // Send WhatsApp notification to all active clients
            $auditTypeLabel = $type === 'mid_month' ? 'Mid Month' : 'Month End';
            $auditMonthYear = date('F Y', mktime(0, 0, 0, $month, 1, $year));

            $clients = $pdo->query("SELECT name, mobile FROM users WHERE is_graduated = 0 AND mobile <> ''")->fetchAll();
            foreach ($clients as $client) {
                $firstName = explode(' ', trim($client->name))[0] ?: 'there';
                sendWhatsAppAuditStartReminder($client->mobile, $firstName, $auditTypeLabel, $auditMonthYear);
            }

            flash('admin', 'Audit window opened and clients notified on WhatsApp.', 'success');
            redirect(APP_URL . '/admin/audits/index.php');
        } catch (PDOException $e) {
            $errors[] = 'An audit window for that type/month/year already exists.';
        }
    }
}

adminPageStart('Open Audit Window', 'audits');
$curMonth = (int)date('n');
$curYear  = (int)date('Y');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>

<div class="card"><div class="card-body">
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

  <div class="form-group">
    <label class="form-label">Audit Type</label>
    <select class="form-control" name="audit_type">
      <option value="mid_month"  <?= ($_POST['audit_type'] ?? '') === 'mid_month'  ? 'selected' : '' ?>>Mid Month</option>
      <option value="month_end"  <?= ($_POST['audit_type'] ?? '') === 'month_end'  ? 'selected' : '' ?>>Month End</option>
    </select>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label class="form-label">Month</label>
      <select class="form-control" name="audit_month">
        <?php
          $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
          foreach ($months as $i => $mName):
            $val = $i + 1;
        ?>
          <option value="<?= $val ?>" <?= ((int)($_POST['audit_month'] ?? $curMonth)) === $val ? 'selected' : '' ?>><?= $mName ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Year</label>
      <input class="form-control" type="number" name="audit_year" min="2020" max="2100" value="<?= e($_POST['audit_year'] ?? $curYear) ?>">
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label class="form-label">Start Date <span style="color:#dc2626">*</span></label>
      <input class="form-control" type="date" name="start_date" value="<?= e($_POST['start_date'] ?? '') ?>" required>
      <span style="font-size:.8rem;color:#6b7280;margin-top:.3rem;display:block;">Clients can start the audit from this date.</span>
    </div>
    <div class="form-group">
      <label class="form-label">End Date <span style="color:#dc2626">*</span></label>
      <input class="form-control" type="date" name="end_date" value="<?= e($_POST['end_date'] ?? '') ?>" required>
      <span style="font-size:.8rem;color:#6b7280;margin-top:.3rem;display:block;">Clients cannot submit after this date.</span>
    </div>
  </div>

  <button class="btn btn-primary">Open Window</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/audits/index.php">Cancel</a>
</form>
</div></div>

<script>
// Auto-set end date when start date changes (default +7 days)
document.querySelector('[name=start_date]').addEventListener('change', function () {
    const endInput = document.querySelector('[name=end_date]');
    if (!endInput.value) {
        const d = new Date(this.value);
        d.setDate(d.getDate() + 7);
        endInput.value = d.toISOString().split('T')[0];
    }
    endInput.min = this.value;
});
</script>
<?php adminPageEnd(); ?>
