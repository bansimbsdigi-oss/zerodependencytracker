<?php
// admin/dashboard.php

require_once __DIR__ . '/_bootstrap.php';

$pdo = getDB();

$totalClients = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$graduates = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_graduated = 1")->fetchColumn();
$activeWindows = (int)$pdo->query("SELECT COUNT(*) FROM audit_windows WHERE is_open = 1")->fetchColumn();
$unreadNotifications = (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
$auditsCompleted = (int)$pdo->query("SELECT COUNT(*) FROM audit_sessions WHERE status = 'completed'")->fetchColumn();
$activeTeamMembers = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'team_member' AND is_active = 1")->fetchColumn();

$stmt = $pdo->query("
    SELECT aus.id, u.name AS client_name, pa.area_name, aw.audit_type, aw.audit_month, aw.audit_year,
           aus.total_score, aus.max_score, aus.is_perfect, aus.completed_at, u.is_graduated
    FROM audit_sessions aus
    JOIN users u ON u.id = aus.user_id
    JOIN audit_windows aw ON aw.id = aus.audit_window_id
    LEFT JOIN problem_areas pa ON pa.id = aus.area_id
    WHERE aus.status = 'completed'
    ORDER BY aus.completed_at DESC
    LIMIT 8
");
$recentAudits = $stmt->fetchAll();

function statPercent($score, $max) {
    return $max > 0 ? round(($score / $max) * 100) : 0;
}

function auditName($audit) {
    return date('M', mktime(0, 0, 0, (int)$audit->audit_month, 1)) . ' ' . ucwords(str_replace('_', '-', $audit->audit_type));
}

adminPageStart('Dashboard', 'dashboard');
?>

<div class="admin-stats-grid">
  <div class="admin-stat-card">
    <span class="admin-stat-icon" style="background:#ecfdf5;color:#059669;"><?= adminIcon('clients', 28) ?></span>
    <div><div class="admin-stat-value"><?= $totalClients ?></div><div class="admin-stat-label">Total Clients</div></div>
  </div>
  <div class="admin-stat-card">
    <span class="admin-stat-icon" style="background:#ecfdf5;color:#059669;"><?= adminIcon('trophy', 28) ?></span>
    <div><div class="admin-stat-value"><?= $graduates ?></div><div class="admin-stat-label">Graduated</div></div>
  </div>
  <div class="admin-stat-card">
    <span class="admin-stat-icon" style="background:#fefce8;color:#ca8a04;"><?= adminIcon('audits', 28) ?></span>
    <div><div class="admin-stat-value"><?= $activeWindows ?></div><div class="admin-stat-label">Open Audit Windows</div></div>
  </div>
  <div class="admin-stat-card">
    <span class="admin-stat-icon" style="background:#f0f9ff;color:#0284c7;"><?= adminIcon('bell', 28) ?></span>
    <div><div class="admin-stat-value"><?= $unreadNotifications ?></div><div class="admin-stat-label">Unread Notifications</div></div>
  </div>
  <div class="admin-stat-card">
    <span class="admin-stat-icon" style="background:#f5f3ff;color:#7c3aed;"><?= adminIcon('chart', 28) ?></span>
    <div><div class="admin-stat-value"><?= $auditsCompleted ?></div><div class="admin-stat-label">Audits Completed</div></div>
  </div>
  <div class="admin-stat-card">
    <span class="admin-stat-icon" style="background:#fdf2f8;color:#db2777;"><?= adminIcon('team', 28) ?></span>
    <div><div class="admin-stat-value"><?= $activeTeamMembers ?></div><div class="admin-stat-label">Active Team<br>Members</div></div>
  </div>
</div>

<div class="admin-actions-row">
  <a class="admin-action-btn primary" href="<?= APP_URL ?>/admin/audits/create.php"><?= adminIcon('plus', 16) ?> Open Audit Window</a>
  <?php if (hasPermission('register_clients')): ?>
    <a class="admin-action-btn outline" href="<?= APP_URL ?>/admin/clients/create.php"><?= adminIcon('user', 16) ?> Add Client</a>
  <?php endif; ?>
  <?php if (hasPermission('manage_questions')): ?>
    <a class="admin-action-btn" href="<?= APP_URL ?>/admin/questions/create.php"><?= adminIcon('questions', 16) ?> Add Question</a>
  <?php endif; ?>
  <?php if (($_SESSION['admin_role'] ?? '') === 'admin'): ?>
    <a class="admin-action-btn" href="<?= APP_URL ?>/admin/team/create.php"><?= adminIcon('team', 16) ?> Add Team Member</a>
  <?php endif; ?>
</div>

<div class="admin-table-card">
  <div class="admin-table-title">Recent Completed Audits</div>
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
        <?php $pct = statPercent((int)$audit->total_score, (int)$audit->max_score); ?>
        <tr>
          <td><strong><?= e($audit->client_name) ?></strong></td>
          <td><?= e($audit->area_name ?? 'Unassigned') ?></td>
          <td><?= e(auditName($audit)) ?></td>
          <td style="font-weight:900;color:<?= $pct >= 75 ? '#059669' : ($pct >= 50 ? '#0f8f83' : '#ea580c') ?>;"><?= $pct ?>%</td>
          <td><span class="badge <?= $audit->is_graduated ? 'badge-warning' : 'badge-success' ?>"><?= $audit->is_graduated ? 'Graduated' : 'Active' ?></span></td>
          <td><?= $audit->completed_at ? e(date('d M Y', strtotime($audit->completed_at))) : '-' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php adminPageEnd(); ?>
