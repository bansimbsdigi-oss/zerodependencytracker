<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;
use App\Libraries\WhatsAppService;
use App\Libraries\AuditReminderService;

class AuditsController extends Controller
{
    public function index()
    {
        requirePermission('view_scores');
        $pdo = getDB();

        // Auto-close windows whose end_date has passed
        $pdo->exec("UPDATE audit_windows SET is_open = 0, closed_at = NOW() WHERE is_open = 1 AND end_date IS NOT NULL AND end_date < CURDATE()");

        $windows = $pdo->query("
            SELECT aw.*, au.name opened_by_name, COUNT(aus.id) completions
            FROM audit_windows aw
            LEFT JOIN admin_users au ON au.id = aw.opened_by
            LEFT JOIN audit_sessions aus ON aus.audit_window_id = aw.id AND aus.status = 'completed'
            GROUP BY aw.id
            ORDER BY aw.audit_year DESC, aw.audit_month DESC, aw.id DESC
        ")->fetchAll();

        return view('admin/audits/index', compact('windows'));
    }

    public function create()
    {
        requirePermission('view_scores');
        return view('admin/audits/create', [
            'errors' => []
        ]);
    }

    public function store()
    {
        requirePermission('view_scores');
        verifyCsrf();
        $pdo = getDB();

        $type      = $this->request->getPost('audit_type')  ?? '';
        $month     = (int)($this->request->getPost('audit_month') ?? 0);
        $year      = (int)($this->request->getPost('audit_year')  ?? 0);
        $startDate = $this->request->getPost('start_date') ?? '';
        $endDate   = $this->request->getPost('end_date')   ?? '';

        $errors = [];
        if (!in_array($type, ['mid_month', 'month_end'], true)) $errors[] = 'Select a valid audit type.';
        if ($month < 1 || $month > 12)                        $errors[] = 'Select a valid month.';
        if ($year < 2020 || $year > 2100)                     $errors[] = 'Select a valid year.';
        if (empty($startDate))                                 $errors[] = 'Start date is required.';
        if (empty($endDate))                                   $errors[] = 'End date is required.';
        if ($startDate && $endDate && $endDate <= $startDate)  $errors[] = 'End date must be after start date.';

        if ($errors) {
            return view('admin/audits/create', [
                'errors' => $errors
            ]);
        }

        try {
            $pdo->prepare("INSERT INTO audit_windows (audit_type, audit_month, audit_year, start_date, end_date, opened_by, is_open) VALUES (?, ?, ?, ?, ?, ?, 1)")
                ->execute([$type, $month, $year, $startDate, $endDate, $_SESSION['admin_id']]);

            // Send WhatsApp notification to all active clients
            $auditTypeLabel = $type === 'mid_month' ? 'Mid Month' : 'Month End';
            $auditMonthYear = date('F Y', mktime(0, 0, 0, $month, 1, $year));

            $clients = $pdo->query("SELECT name, mobile FROM users WHERE is_graduated = 0 AND mobile <> ''")->fetchAll();
            foreach ($clients as $client) {
                $firstName = explode(' ', trim($client->name))[0] ?: 'there';
                WhatsAppService::sendAuditStartReminder($client->mobile, $firstName, $auditTypeLabel, $auditMonthYear);
            }

            flash('admin', 'Audit window opened and clients notified on WhatsApp.', 'success');
            return redirect()->to(APP_URL . '/admin/audits');
        } catch (\PDOException $e) {
            return view('admin/audits/create', [
                'errors' => ['An audit window for that type/month/year already exists.']
            ]);
        }
    }

    public function action()
    {
        requirePermission('view_scores');
        verifyCsrf();
        $pdo = getDB();

        $action = $this->request->getPost('action') ?? '';

        if ($action === 'send_reminders') {
            $result = AuditReminderService::send($pdo);
            flash('admin', $result['message'], $result['success'] ? 'success' : 'danger');
            return redirect()->to(APP_URL . '/admin/audits');
        }

        if ($action === 'close') {
            $id = (int)($this->request->getPost('id') ?? 0);
            $pdo->prepare("UPDATE audit_windows SET is_open = 0, closed_at = NOW() WHERE id = ?")->execute([$id]);
            flash('admin', 'Audit window closed.', 'success');
            return redirect()->to(APP_URL . '/admin/audits');
        }

        if ($action === 'reopen') {
            $id = (int)($this->request->getPost('id') ?? 0);
            $pdo->prepare("UPDATE audit_windows SET is_open = 1, closed_at = NULL WHERE id = ?")->execute([$id]);
            flash('admin', 'Audit window reopened.', 'success');
            return redirect()->to(APP_URL . '/admin/audits');
        }

        return redirect()->to(APP_URL . '/admin/audits');
    }

    public function report($sessionId)
    {
        requirePermission('view_scores');
        $sessionId = (int)$sessionId;
        $pdo = getDB();

        $stmt = $pdo->prepare("
            SELECT aus.*, pa.area_name, aw.audit_type, aw.audit_month, aw.audit_year,
                   u.name AS client_name, u.id AS client_id,
                   au.name AS feedback_by_name
            FROM audit_sessions aus
            JOIN audit_windows aw ON aw.id = aus.audit_window_id
            LEFT JOIN problem_areas pa ON pa.id = aus.area_id
            JOIN users u ON u.id = aus.user_id
            LEFT JOIN admin_users au ON au.id = aus.admin_feedback_by
            WHERE aus.id = ? AND aus.status = 'completed'
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        if (!$session) {
            return redirect()->to(APP_URL . '/admin/clients');
        }

        $clientId = (int)$session->client_id;
        if (!adminCanAccessClient($clientId)) {
            return redirect()->to(APP_URL . '/admin/clients');
        }

        $percentage = $session->max_score > 0 ? round(($session->total_score / $session->max_score) * 100) : 0;

        // Previous session for comparison
        $prev = $pdo->prepare("
            SELECT total_score, max_score FROM audit_sessions
            WHERE user_id=? AND area_id=? AND status='completed' AND id!=?
              AND (completed_at < ? OR (completed_at = ? AND id < ?))
            ORDER BY completed_at DESC, id DESC LIMIT 1
        ");
        $prev->execute([$clientId, $session->area_id, $sessionId, $session->completed_at, $session->completed_at, $sessionId]);
        $prevSession = $prev->fetch();
        $diff = null;
        if ($prevSession && $prevSession->max_score > 0) {
            $prevPct = round(($prevSession->total_score / $prevSession->max_score) * 100);
            $diff    = $percentage - $prevPct;
        }

        // Section scores
        $secStmt = $pdo->prepare("
            SELECT qs.id AS section_id, qs.section_name, qs.display_order,
                   SUM(ar.points_earned)       AS earned,
                   SUM(ar.max_question_points) AS max_pts
            FROM audit_responses ar
            JOIN questions q ON q.sno = ar.question_id
            JOIN question_sections qs ON qs.id = q.section_id
            WHERE ar.audit_session_id = ? AND q.section_id IS NOT NULL
            GROUP BY qs.id
            ORDER BY qs.display_order, qs.section_name
        ");
        $secStmt->execute([$sessionId]);
        $sections = $secStmt->fetchAll();

        // Responses grouped by section
        $respStmt = $pdo->prepare("
            SELECT ar.*, q.question_text, q.question_type, q.section_id,
                   qs.section_name, qs.display_order AS sec_order,
                   o.option_text,
                   (SELECT GROUP_CONCAT(o2.option_text SEPARATOR ', ')
                    FROM audit_response_selections ars
                    JOIN options o2 ON o2.id = ars.option_id
                    WHERE ars.option_id IS NOT NULL AND ars.audit_response_id = ar.id) AS multi_answer
            FROM audit_responses ar
            JOIN questions q ON q.sno = ar.question_id
            LEFT JOIN question_sections qs ON qs.id = q.section_id
            LEFT JOIN options o ON o.id = ar.option_id
            WHERE ar.audit_session_id = ?
            ORDER BY qs.display_order, qs.section_name, ar.id
        ");
        $respStmt->execute([$sessionId]);
        $responsesBySection = [];
        foreach ($respStmt->fetchAll() as $r) {
            $key = $r->section_name ?? 'General';
            $responsesBySection[$key][] = $r;
        }

        // Client feedback
        $fbStmt = $pdo->prepare("SELECT cf.*, pa.area_name suggested_area FROM client_feedback cf LEFT JOIN problem_areas pa ON pa.id=cf.suggested_area_id WHERE cf.audit_session_id=?");
        $fbStmt->execute([$sessionId]);
        $feedback = $fbStmt->fetch();

        return view('admin/audits/report', [
            'session' => $session,
            'percentage' => $percentage,
            'diff' => $diff,
            'sections' => $sections,
            'responsesBySection' => $responsesBySection,
            'feedback' => $feedback,
            'clientId' => $clientId,
            'sessionId' => $sessionId
        ]);
    }

    public function saveReport($sessionId)
    {
        requirePermission('view_scores');
        verifyCsrf();
        $sessionId = (int)$sessionId;
        $pdo = getDB();

        // Fetch session first
        $stmt = $pdo->prepare("SELECT * FROM audit_sessions WHERE id = ? AND status = 'completed'");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        if (!$session) {
            return redirect()->to(APP_URL . '/admin/clients');
        }

        $clientId = (int)$session->user_id;
        if (!adminCanAccessClient($clientId)) {
            return redirect()->to(APP_URL . '/admin/clients');
        }

        $fbText = trim($this->request->getPost('admin_feedback') ?? '');
        
        $pdo->prepare("UPDATE audit_sessions SET admin_feedback=?, admin_feedback_by=?, admin_feedback_at=NOW() WHERE id=?")
            ->execute([$fbText ?: null, $_SESSION['admin_id'], $sessionId]);

        if ($fbText) {
            $clientRow = $pdo->prepare("
                SELECT u.name, u.mobile, aw.audit_type, aw.audit_month, aw.audit_year
                FROM users u
                JOIN audit_sessions aus ON aus.user_id = u.id
                JOIN audit_windows aw ON aw.id = aus.audit_window_id
                WHERE aus.id = ?
            ");
            $clientRow->execute([$sessionId]);
            $clientData = $clientRow->fetch();

            if ($clientData) {
                $firstName      = explode(' ', trim($clientData->name))[0] ?: 'there';
                $auditTypeLabel = $clientData->audit_type === 'mid_month' ? 'Mid Month' : 'Month End';
                $auditMonthYear = date('F Y', mktime(0, 0, 0, (int)$clientData->audit_month, 1, (int)$clientData->audit_year));
                WhatsAppService::sendAuditFeedback($clientData->mobile, $firstName, $auditTypeLabel, $auditMonthYear, $fbText);

                teamNotify('team_feedback_saved', ($_SESSION['admin_name'] ?? 'Team member') . " saved coach feedback for client: {$clientData->name}", $clientId, $sessionId);
            }
        }

        flash('admin', 'Feedback saved and client notified on WhatsApp.', 'success');
        return redirect()->to(APP_URL . '/admin/audits/report/' . $sessionId);
    }
}
