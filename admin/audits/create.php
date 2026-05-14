<?php
require_once __DIR__ . '/../_bootstrap.php';
requirePermission('view_scores');
$pdo = getDB();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $type = $_POST['audit_type'] ?? '';
    $month = (int)($_POST['audit_month'] ?? 0);
    $year = (int)($_POST['audit_year'] ?? 0);
    if (!in_array($type, ['mid_month','month_end'], true)) $errors[] = 'Select a valid audit type.';
    if ($month < 1 || $month > 12) $errors[] = 'Select a valid month.';
    if ($year < 2020 || $year > 2100) $errors[] = 'Select a valid year.';
    if (!$errors) {
        try {
            $pdo->prepare("INSERT INTO audit_windows (audit_type, audit_month, audit_year, opened_by, is_open) VALUES (?, ?, ?, ?, 1)")->execute([$type, $month, $year, $_SESSION['admin_id']]);
            flash('admin', 'Audit window opened.', 'success');
            redirect(APP_URL . '/admin/audits/index.php');
        } catch (PDOException $e) {
            $errors[] = 'An audit window for that type/month/year already exists.';
        }
    }
}
adminPageStart('Open Audit Window', 'audits');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>"><div class="form-group"><label class="form-label">Audit Type</label><select class="form-control" name="audit_type"><option value="mid_month">Mid Month</option><option value="month_end">Month End</option></select></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="form-group"><label class="form-label">Month</label><input class="form-control" type="number" min="1" max="12" name="audit_month" value="<?= date('n') ?>"></div><div class="form-group"><label class="form-label">Year</label><input class="form-control" type="number" name="audit_year" value="<?= date('Y') ?>"></div></div><button class="btn btn-primary">Open Window</button> <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/audits/index.php">Cancel</a></form></div></div>
<?php adminPageEnd(); ?>
