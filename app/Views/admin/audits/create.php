<?php
adminPageStart('Open Audit Window', 'audits');
$curMonth = (int)date('n');
$curYear  = (int)date('Y');
?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>

<div class="card"><div class="card-body">
<form method="POST" action="<?= APP_URL ?>/admin/audits/create">
  <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

  <div class="form-group">
    <label class="form-label">Audit Type</label>
    <select class="form-control" name="audit_type">
      <option value="mid_month"  <?= ($this->request->getPost('audit_type') ?? '') === 'mid_month'  ? 'selected' : '' ?>>Mid Month</option>
      <option value="month_end"  <?= ($this->request->getPost('audit_type') ?? '') === 'month_end'  ? 'selected' : '' ?>>Month End</option>
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
          <option value="<?= $val ?>" <?= ((int)($this->request->getPost('audit_month') ?? $curMonth)) === $val ? 'selected' : '' ?>><?= $mName ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Year</label>
      <input class="form-control" type="number" name="audit_year" min="2020" max="2100" value="<?= e($this->request->getPost('audit_year') ?? $curYear) ?>">
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label class="form-label">Start Date <span style="color:#dc2626">*</span></label>
      <input class="form-control" type="date" name="start_date" value="<?= e($this->request->getPost('start_date') ?? '') ?>" required>
      <span style="font-size:.8rem;color:#6b7280;margin-top:.3rem;display:block;">Clients can start the audit from this date.</span>
    </div>
    <div class="form-group">
      <label class="form-label">End Date <span style="color:#dc2626">*</span></label>
      <input class="form-control" type="date" name="end_date" value="<?= e($this->request->getPost('end_date') ?? '') ?>" required>
      <span style="font-size:.8rem;color:#6b7280;margin-top:.3rem;display:block;">Clients cannot submit after this date.</span>
    </div>
  </div>

  <button class="btn btn-primary">Open Window</button>
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/audits">Cancel</a>
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
