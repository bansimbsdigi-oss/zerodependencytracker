<?php adminPageStart('Options', 'questions'); ?>
<div class="card"><div class="card-body"><strong><?= e($question->question_text) ?></strong></div></div>
<div style="display:flex;justify-content:space-between;align-items:center;margin:1rem 0;">
  <a class="btn btn-secondary" href="<?= APP_URL ?>/admin/questions">Back to Questions</a>
  <a class="btn btn-primary" href="<?= APP_URL ?>/admin/questions/edit/<?= $questionId ?>">Manage Options Inline</a>
</div>
<table class="table">
  <thead><tr><th>Order</th><th>Option</th><th>Points</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (!$options): ?>
    <tr><td colspan="4" style="color:#64748b;text-align:center;">No options defined.</td></tr>
  <?php endif; ?>
  <?php foreach ($options as $opt): ?>
    <tr>
      <td><?= (int)$opt->display_order ?></td>
      <td><?= e($opt->option_text) ?></td>
      <td><?= (int)$opt->points ?></td>
      <td style="display:flex;gap:.5rem;">
        <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/admin/questions/edit/<?= $questionId ?>">Edit</a>
        <form method="POST" action="<?= APP_URL ?>/admin/options/delete/<?= $opt->id ?>" onsubmit="return confirm('Delete this option?');">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="question_id" value="<?= $questionId ?>">
          <button class="btn btn-danger btn-sm btn-delete">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php adminPageEnd(); ?>
