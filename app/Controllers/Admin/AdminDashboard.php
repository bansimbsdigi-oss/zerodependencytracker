<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

class AdminDashboard extends Controller
{
    public function index()
    {
        requireAdminLogin();
        $pdo = getDB();

        $totalClients       = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $graduates          = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_graduated = 1')->fetchColumn();
        $activeWindows      = (int)$pdo->query('SELECT COUNT(*) FROM audit_windows WHERE is_open = 1')->fetchColumn();
        $unreadNotifications = (int)$pdo->query('SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0')->fetchColumn();
        $auditsCompleted    = (int)$pdo->query("SELECT COUNT(*) FROM audit_sessions WHERE status = 'completed'")->fetchColumn();
        $activeTeamMembers  = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'team_member' AND is_active = 1")->fetchColumn();

        $recentAudits = $pdo->query("
            SELECT aus.id, u.name AS client_name, pa.area_name, aw.audit_type, aw.audit_month, aw.audit_year,
                   aus.total_score, aus.max_score, aus.is_perfect, aus.completed_at, u.is_graduated
            FROM audit_sessions aus
            JOIN users u ON u.id = aus.user_id
            JOIN audit_windows aw ON aw.id = aus.audit_window_id
            LEFT JOIN problem_areas pa ON pa.id = aus.area_id
            WHERE aus.status = 'completed'
            ORDER BY aus.completed_at DESC
            LIMIT 8
        ")->fetchAll();

        return view('admin/dashboard', compact(
            'totalClients', 'graduates', 'activeWindows', 'unreadNotifications',
            'auditsCompleted', 'activeTeamMembers', 'recentAudits'
        ));
    }
}
