<?php adminPageStart('Dashboard', 'dashboard'); ?>

<div class="admin-stats-grid">
  <a class="admin-stat-card" href="<?= APP_URL ?>/admin/clients" style="text-decoration:none;cursor:pointer;" title="View all clients">
    <span class="admin-stat-icon" style="background:#ecfdf5;color:#059669;"><?= adminIcon('clients', 28) ?></span>
    <div><div class="admin-stat-value"><?= $totalClients ?></div><div class="admin-stat-label">Total Clients</div></div>
  </a>
  <a class="admin-stat-card" href="<?= APP_URL ?>/admin/clients" style="text-decoration:none;cursor:pointer;" title="View graduated clients">
    <span class="admin-stat-icon" style="background:#ecfdf5;color:#059669;"><?= adminIcon('trophy', 28) ?></span>
    <div><div class="admin-stat-value"><?= $graduates ?></div><div class="admin-stat-label">Graduated</div></div>
  </a>
  <a class="admin-stat-card" href="<?= APP_URL ?>/admin/audits" style="text-decoration:none;cursor:pointer;" title="View audit windows">
    <span class="admin-stat-icon" style="background:#fefce8;color:#ca8a04;"><?= adminIcon('audits', 28) ?></span>
    <div><div class="admin-stat-value"><?= $activeWindows ?></div><div class="admin-stat-label">Open Audit Windows</div></div>
  </a>
  <a class="admin-stat-card" href="<?= APP_URL ?>/admin/notifications" style="text-decoration:none;cursor:pointer;" title="View notifications">
    <span class="admin-stat-icon" style="background:#f0f9ff;color:#0284c7;"><?= adminIcon('bell', 28) ?></span>
    <div><div class="admin-stat-value"><?= $unreadNotifications ?></div><div class="admin-stat-label">Unread Notifications</div></div>
  </a>
  <div class="admin-stat-card">
    <span class="admin-stat-icon" style="background:#f5f3ff;color:#7c3aed;"><?= adminIcon('chart', 28) ?></span>
    <div><div class="admin-stat-value"><?= $auditsCompleted ?></div><div class="admin-stat-label">Audits Completed</div></div>
  </div>
  <a class="admin-stat-card" href="<?= APP_URL ?>/admin/team" style="text-decoration:none;cursor:pointer;" title="View team">
    <span class="admin-stat-icon" style="background:#fdf2f8;color:#db2777;"><?= adminIcon('team', 28) ?></span>
    <div><div class="admin-stat-value"><?= $activeTeamMembers ?></div><div class="admin-stat-label">Active Team<br>Members</div></div>
  </a>
</div>

<div class="admin-actions-row">
  <a class="admin-action-btn primary" href="<?= APP_URL ?>/admin/audits/create"><?= adminIcon('plus', 16) ?> Open Audit Window</a>
  <?php if (hasPermission('view_clients')): ?>
    <a class="admin-action-btn primary" href="<?= APP_URL ?>/admin/clients"><?= adminIcon('clients', 16) ?> View Clients</a>
  <?php endif; ?>
  <?php if (hasPermission('register_clients')): ?>
    <a class="admin-action-btn outline" href="<?= APP_URL ?>/admin/clients/create"><?= adminIcon('user', 16) ?> Add Client</a>
  <?php endif; ?>
  <?php if (hasPermission('manage_questions')): ?>
    <a class="admin-action-btn" href="<?= APP_URL ?>/admin/questions/create"><?= adminIcon('questions', 16) ?> Add Question</a>
  <?php endif; ?>
  <?php if (($_SESSION['admin_role'] ?? '') === 'admin'): ?>
    <a class="admin-action-btn" href="<?= APP_URL ?>/admin/team"><?= adminIcon('team', 16) ?> Add Team Member</a>
  <?php endif; ?>
</div>

<div class="admin-table-card">
  <div class="admin-table-title" style="display:flex;justify-content:space-between;align-items:center;">
    <span>Recent Completed Audits</span>
    <?php if (hasPermission('view_clients')): ?>
      <a href="<?= APP_URL ?>/admin/clients" style="font-size:.82rem;font-weight:600;color:#0f8f83;text-decoration:none;">View All Clients →</a>
    <?php endif; ?>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>Client</th>
        <th>Area</th>
        <th>Audit</th>
        <th>Score</th>
        <th>Status</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$recentAudits): ?>
        <tr>
          <td colspan="6" style="color:#64748b;">No completed audits yet.</td>
        </tr>
      <?php endif; ?>
      <?php foreach ($recentAudits as $audit): ?>
        <?php 
          $pct = $audit->max_score > 0 ? round(($audit->total_score / $audit->max_score) * 100) : 0; 
          $auditMonthName = date('M', mktime(0, 0, 0, (int)$audit->audit_month, 1)) . ' ' . ucwords(str_replace('_', '-', $audit->audit_type));
        ?>
        <tr>
          <td><strong><?= e($audit->client_name) ?></strong></td>
          <td><?= e($audit->area_name ?? 'Unassigned') ?></td>
          <td><?= e($auditMonthName) ?></td>
          <td style="font-weight:900;color:<?= $pct >= 75 ? '#059669' : ($pct >= 50 ? '#0f8f83' : '#ea580c') ?>;"><?= $pct ?>%</td>
          <td><span class="badge <?= $audit->is_graduated ? 'badge-warning' : 'badge-success' ?>"><?= $audit->is_graduated ? 'Graduated' : 'Active' ?></span></td>
          <td><?= $audit->completed_at ? e(date('d M Y', strtotime($audit->completed_at))) : '-' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php adminPageEnd(); ?>
