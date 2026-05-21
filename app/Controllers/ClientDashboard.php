<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class ClientDashboard extends Controller
{
    public function index()
    {
        requireLogin();
        $userId = currentUserId();
        $pdo    = getDB();

        $stmt = $pdo->prepare('SELECT u.*, pa.area_name FROM users u LEFT JOIN problem_areas pa ON u.area_id = pa.id WHERE u.id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT aw.*, aus.id AS session_id, aus.status AS session_status
            FROM audit_windows aw
            LEFT JOIN audit_sessions aus ON aus.audit_window_id = aw.id AND aus.user_id = ?
            WHERE aw.is_open = 1 AND (aus.id IS NULL OR aus.status = 'in_progress')
        ");
        $stmt->execute([$userId]);
        $activeAudits = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT aus.*, aw.audit_type, aw.audit_month, aw.audit_year, pa.area_name,
                   au.name AS coach_name
            FROM audit_sessions aus
            JOIN audit_windows aw ON aus.audit_window_id = aw.id
            LEFT JOIN problem_areas pa ON aus.area_id = pa.id
            LEFT JOIN admin_users au ON au.id = aus.admin_feedback_by
            WHERE aus.user_id = ? AND aus.status = 'completed'
            ORDER BY aus.completed_at DESC
        ");
        $stmt->execute([$userId]);
        $history = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT aus.*, aw.audit_type, aw.audit_month, aw.audit_year, pa.area_name,
                   au.name AS coach_name
            FROM audit_sessions aus
            JOIN audit_windows aw ON aus.audit_window_id = aw.id
            LEFT JOIN problem_areas pa ON aus.area_id = pa.id
            LEFT JOIN admin_users au ON au.id = aus.admin_feedback_by
            WHERE aus.user_id = ?
            ORDER BY aus.started_at DESC
        ");
        $stmt->execute([$userId]);
        $allSessions = $stmt->fetchAll();

        $totalAudits = count($history);
        $latestScore = 0;
        $improvement = null;
        if ($totalAudits > 0) {
            $latestScore = $history[0]->max_score > 0 ? round(($history[0]->total_score / $history[0]->max_score) * 100) : 0;
            if ($totalAudits > 1) {
                $oldest      = $history[$totalAudits - 1];
                $oldestPct   = $oldest->max_score > 0 ? round(($oldest->total_score / $oldest->max_score) * 100) : 0;
                $improvement = $latestScore - $oldestPct;
            }
        }

        $areaAuditRows = [];
        foreach ($history as $h) {
            $area = $h->area_name ?? 'General';
            if (!isset($areaAuditRows[$area])) $areaAuditRows[$area] = [];
            if (count($areaAuditRows[$area]) < 3) {
                $areaAuditRows[$area][] = $h;
            }
        }

        $sessionIds    = array_column($history, 'id');
        $sectionScores = [];
        if (!empty($sessionIds)) {
            $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $stmt = $pdo->prepare("
                SELECT ar.audit_session_id,
                       qs.section_name,
                       qs.display_order,
                       SUM(ar.points_earned)       AS earned,
                       SUM(ar.max_question_points) AS max_pts
                FROM audit_responses ar
                JOIN questions q  ON q.sno = ar.question_id
                JOIN question_sections qs ON qs.id = q.section_id
                WHERE ar.audit_session_id IN ($placeholders)
                  AND q.section_id IS NOT NULL
                GROUP BY ar.audit_session_id, qs.id
                ORDER BY ar.audit_session_id, qs.display_order, qs.section_name
            ");
            $stmt->execute($sessionIds);
            foreach ($stmt->fetchAll() as $row) {
                $sectionScores[$row->audit_session_id][] = $row;
            }
        }

        return view('client/dashboard', compact(
            'user', 'activeAudits', 'history', 'allSessions',
            'totalAudits', 'latestScore', 'improvement',
            'areaAuditRows', 'sectionScores'
        ));
    }
}
