<?php adminPageStart('All Clients', 'clients'); ?>
<?php if (hasPermission('register_clients')): ?>
  <div class="admin-page-action">
    <a class="admin-action-btn primary" href="<?= APP_URL ?>/admin/clients/create">+ Add Client</a>
  </div>
<?php endif; ?>

<?php if ($featured): ?>
  <section class="admin-client-hero">
    <div class="admin-client-avatar"><?= e(strtoupper(substr($featured->name, 0, 1))) ?></div>
    <div class="admin-client-hero-main">
      <h2><?= e($featured->name) ?></h2>
      <p><?= e($featured->email) ?> <span>&bull;</span> <?= e($featured->mobile) ?></p>
      <div class="admin-client-pills">
        <span class="client-pill area"><?= e($featured->area_name ?? 'Unassigned') ?></span>
        <span class="badge <?= $featured->is_graduated ? 'badge-warning' : 'badge-success' ?>"><?= $featured->is_graduated ? 'Graduated' : 'Active' ?></span>
        <span class="client-pill assigned">Assigned: <?= e($featured->team_member ?: 'Unassigned') ?></span>
      </div>
    </div>
    <div class="admin-client-hero-actions">
      <?php if (hasPermission('edit_clients')): ?>
        <a class="admin-action-btn" href="<?= APP_URL ?>/admin/clients/edit/<?= $featured->id ?>">Edit</a>
      <?php endif; ?>
      <a class="admin-action-btn outline" href="<?= APP_URL ?>/admin/clients/view/<?= $featured->id ?>">View Report</a>
    </div>
  </section>
<?php endif; ?>

<section class="admin-table-card">
  <div class="admin-table-toolbar">
    <h2>Clients (<?= count($clients) ?>)</h2>
    <input type="search" id="client-search" class="admin-search" placeholder="Search clients..." autocomplete="off">
  </div>
  <table class="table" id="clients-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Area</th>
        <th>Assigned To</th>
        <th>Last Audit</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$clients): ?>
        <tr><td colspan="7" style="color:#64748b;">No clients found.</td></tr>
      <?php endif; ?>
      <?php foreach ($clients as $c): ?>
        <tr data-client-row>
          <td><strong><?= e($c->name) ?></strong></td>
          <td><?= e($c->email) ?></td>
          <td><span class="client-pill area"><?= e($c->area_name ?? '-') ?></span></td>
          <td><?= e($c->team_member ?: 'Unassigned') ?></td>
          <td><?= $c->last_audit ? e(date('d M Y', strtotime($c->last_audit))) : '-' ?></td>
          <td><span class="badge <?= $c->is_graduated ? 'badge-warning' : 'badge-success' ?>"><?= $c->is_graduated ? 'Graduated' : 'Active' ?></span></td>
          <td>
            <div class="admin-row-actions">
              <?php if (hasPermission('edit_clients')): ?>
                <a class="admin-row-btn" href="<?= APP_URL ?>/admin/clients/edit/<?= $c->id ?>">Edit</a>
              <?php endif; ?>
              <a class="admin-row-btn outline" href="<?= APP_URL ?>/admin/clients/view/<?= $c->id ?>">View</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
  const clientSearch = document.getElementById('client-search');
  const clientRows = Array.from(document.querySelectorAll('[data-client-row]'));
  clientSearch?.addEventListener('input', () => {
    const term = clientSearch.value.trim().toLowerCase();
    clientRows.forEach((row) => {
      row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  });
</script>
<?php adminPageEnd(); ?>
