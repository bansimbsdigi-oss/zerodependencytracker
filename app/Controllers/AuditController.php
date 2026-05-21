<?php

namespace App\Controllers;

use App\Libraries\WhatsAppService;
use CodeIgniter\Controller;

class AuditController extends Controller
{
    public function index()
    {
        requireLogin();
        $userId   = currentUserId();
        $pdo      = getDB();
        $windowId = $this->request->getGet('window_id');

        if (!$windowId) return redirect()->to(APP_URL . '/dashboard');

        $stmt = $pdo->prepare('SELECT * FROM audit_windows WHERE id = ? AND is_open = 1');
        $stmt->execute([$windowId]);
        $window = $stmt->fetch();
        if (!$window) return redirect()->to(APP_URL . '/dashboard');

        $today = date('Y-m-d');
        if ($window->start_date && $today < $window->start_date) return redirect()->to(APP_URL . '/dashboard');
        if ($window->end_date   && $today > $window->end_date)   return redirect()->to(APP_URL . '/dashboard');

        $user = getCurrentUser();
        if ($user && (int)$user->is_graduated === 1) return redirect()->to(APP_URL . '/dashboard');

        $stmt = $pdo->prepare('SELECT id, status FROM audit_sessions WHERE user_id = ? AND audit_window_id = ?');
        $stmt->execute([$userId, $windowId]);
        $session = $stmt->fetch();

        if ($session && $session->status === 'completed') {
            return redirect()->to(APP_URL . '/audit-report/' . $session->id);
        }

        $stmt = $pdo->prepare('SELECT area_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userAreaId = $stmt->fetchColumn();

        if (!$userAreaId) return redirect()->to(APP_URL . '/dashboard');

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions q JOIN question_area_map qam ON qam.question_id = q.sno WHERE qam.area_id = ? AND q.flag = 1');
        $stmt->execute([$userAreaId]);
        if ((int)$stmt->fetchColumn() === 0) return redirect()->to(APP_URL . '/dashboard');

        if (!$session) {
            $stmt = $pdo->prepare("INSERT INTO audit_sessions (user_id, audit_window_id, area_id, status) VALUES (?, ?, ?, 'in_progress')");
            $stmt->execute([$userId, $windowId, $userAreaId]);
            $sessionId = $pdo->lastInsertId();
        } else {
            $sessionId = $session->id;
        }

        $stmt = $pdo->prepare("
            SELECT q.*, qs.section_name, qs.display_order AS section_order
            FROM questions q
            JOIN question_area_map qam ON qam.question_id = q.sno
            LEFT JOIN question_sections qs ON qs.id = q.section_id
            WHERE qam.area_id = ? AND q.flag = 1
            ORDER BY qs.display_order ASC, qs.id ASC, q.sno ASC
        ");
        $stmt->execute([$userAreaId]);
        $questions = $stmt->fetchAll();

        $sectionCounter  = 0;
        $lastSectionName = null;
        foreach ($questions as $q) {
            $sName = $q->section_name ?? null;
            if ($sName !== $lastSectionName) {
                $sectionCounter++;
                $lastSectionName = $sName;
            }
            $q->_sectionNum = $sectionCounter;
        }

        $auditLabel    = ucwords(str_replace('_', ' ', $window->audit_type)) . ' Audit - ' . date('F Y', mktime(0, 0, 0, (int)$window->audit_month, 1, (int)$window->audit_year));
        $userInitial   = strtoupper(substr($user->name ?? 'U', 0, 1));
        $totalQuestions = count($questions);

        return view('client/audit', compact('window', 'windowId', 'sessionId', 'questions', 'totalQuestions', 'auditLabel', 'userInitial', 'user', 'pdo'));
    }

    public function submit()
    {
        requireLogin();
        verifyCsrf();

        $userId   = currentUserId();
        $pdo      = getDB();
        $windowId = $this->request->getPost('window_id');
        $sessionId = $this->request->getPost('session_id');

        // Re-fetch questions from DB to ensure no tampering
        $stmt = $pdo->prepare('SELECT area_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userAreaId = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT q.*, qs.section_name
            FROM questions q
            JOIN question_area_map qam ON qam.question_id = q.sno
            LEFT JOIN question_sections qs ON qs.id = q.section_id
            WHERE qam.area_id = ? AND q.flag = 1
            ORDER BY qs.display_order ASC, qs.id ASC, q.sno ASC
        ");
        $stmt->execute([$userAreaId]);
        $questions = $stmt->fetchAll();

        $user = getCurrentUser();

        $totalScore = 0;
        $maxScore   = 0;

        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM audit_responses WHERE audit_session_id = ?')->execute([$sessionId]);

            foreach ($questions as $q) {
                $qId   = $q->sno;
                $type  = $q->question_type;
                $pointsEarned    = 0;
                $qMaxPoints      = 0;
                $textResponse    = null;
                $numericResponse = null;

                if ($type === 'mcq' || $type === 'multi_select') {
                    $opts = $pdo->prepare('SELECT id, points FROM options WHERE question_id = ?');
                    $opts->execute([$qId]);
                    $optionsData = $opts->fetchAll();

                    if ($type === 'mcq') {
                        $max = 0;
                        foreach ($optionsData as $opt) if ($opt->points > $max) $max = $opt->points;
                        $qMaxPoints = $max;
                    } else {
                        $max = 0;
                        foreach ($optionsData as $opt) if ($opt->points > 0) $max += $opt->points;
                        $qMaxPoints = $max;
                    }
                } elseif ($type === 'rating') {
                    $qMaxPoints = $q->rating_max;
                }

                $maxScore += $qMaxPoints;

                if ($type === 'text') {
                    $textResponse = mb_substr(trim($this->request->getPost("q_$qId") ?? ''), 0, 2000);
                    $pdo->prepare('INSERT INTO audit_responses (audit_session_id, question_id, text_response, max_question_points) VALUES (?, ?, ?, ?)')
                        ->execute([$sessionId, $qId, $textResponse, $qMaxPoints]);
                } elseif ($type === 'rating') {
                    $numericResponse = (int)($this->request->getPost("q_$qId") ?? 0);
                    $numericResponse = max((int)$q->rating_min, min((int)$q->rating_max, $numericResponse));
                    $pointsEarned    = $numericResponse;
                    $pdo->prepare('INSERT INTO audit_responses (audit_session_id, question_id, numeric_response, points_earned, max_question_points) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$sessionId, $qId, $numericResponse, $pointsEarned, $qMaxPoints]);
                } elseif ($type === 'mcq') {
                    $optionId = (int)($this->request->getPost("q_$qId") ?? 0);
                    $optStmt  = $pdo->prepare('SELECT points FROM options WHERE id = ? AND question_id = ?');
                    $optStmt->execute([$optionId, $qId]);
                    $pointsEarned = (int)($optStmt->fetchColumn() ?: 0);
                    $pdo->prepare('INSERT INTO audit_responses (audit_session_id, question_id, option_id, points_earned, max_question_points) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$sessionId, $qId, $optionId, $pointsEarned, $qMaxPoints]);
                } elseif ($type === 'multi_select') {
                    $pdo->prepare('INSERT INTO audit_responses (audit_session_id, question_id, max_question_points) VALUES (?, ?, ?)')
                        ->execute([$sessionId, $qId, $qMaxPoints]);
                    $responseId   = $pdo->lastInsertId();
                    $selectedOpts = $this->request->getPost("q_$qId") ?? [];
                    if (is_array($selectedOpts)) {
                        foreach ($selectedOpts as $sOptId) {
                            $sOptId  = (int)$sOptId;
                            $optStmt = $pdo->prepare('SELECT points FROM options WHERE id = ? AND question_id = ?');
                            $optStmt->execute([$sOptId, $qId]);
                            $pt           = (int)($optStmt->fetchColumn() ?: 0);
                            $pointsEarned += $pt;
                            $pdo->prepare('INSERT INTO audit_response_selections (audit_response_id, option_id, points_earned) VALUES (?, ?, ?)')
                                ->execute([$responseId, $sOptId, $pt]);
                        }
                    }
                    $pdo->prepare('UPDATE audit_responses SET points_earned = ? WHERE id = ?')->execute([$pointsEarned, $responseId]);
                }

                $totalScore += $pointsEarned;
            }

            $isPerfect = ($maxScore > 0 && $totalScore === $maxScore) ? 1 : 0;
            $pct       = $maxScore > 0 ? round($totalScore / $maxScore * 100) : 0;

            $pdo->prepare("UPDATE audit_sessions SET total_score = ?, max_score = ?, is_perfect = ?, status = 'completed', completed_at = NOW() WHERE id = ?")
                ->execute([$totalScore, $maxScore, $isPerfect, $sessionId]);

            $pdo->prepare("INSERT INTO admin_notifications (type, message, related_user_id, related_audit_session_id) VALUES ('audit_completed', ?, ?, ?)")
                ->execute([$user->name . ' completed an audit — score: ' . $totalScore . '/' . $maxScore . ' (' . $pct . '%)', $userId, $sessionId]);

            if ($isPerfect) {
                $stmt = $pdo->prepare('SELECT is_graduated FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn() == 0) {
                    $pdo->prepare('UPDATE users SET is_graduated = 1 WHERE id = ?')->execute([$userId]);
                    $message = $user->name . ' achieved 100% and graduated.';
                    $pdo->prepare("INSERT INTO admin_notifications (type, message, related_user_id, related_audit_session_id) VALUES ('perfect_score', ?, ?, ?)")
                        ->execute([$message, $userId, $sessionId]);
                    $pdo->prepare('UPDATE audit_sessions SET notification_sent = 1 WHERE id = ?')->execute([$sessionId]);
                    if (defined('ADMIN_WHATSAPP') && ADMIN_WHATSAPP) {
                        WhatsAppService::sendMessage(ADMIN_WHATSAPP, APP_NAME . ': ' . $message);
                    }
                }
            }

            $pdo->commit();
            return redirect()->to(APP_URL . '/audit-report/' . $sessionId);
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Audit save error (user $userId, window $windowId): " . $e->getMessage());
            die('Something went wrong while saving your audit. Please go back and try again.');
        }
    }

    public function report(int $sessionId)
    {
        requireLogin();
        $userId = currentUserId();
        $pdo    = getDB();

        $stmt = $pdo->prepare("
            SELECT aus.*, pa.area_name, aw.audit_type, aw.audit_month, aw.audit_year
            FROM audit_sessions aus
            JOIN audit_windows aw ON aus.audit_window_id = aw.id
            LEFT JOIN problem_areas pa ON aus.area_id = pa.id
            WHERE aus.id = ? AND aus.user_id = ? AND aus.status = 'completed'
        ");
        $stmt->execute([$sessionId, $userId]);
        $session = $stmt->fetch();
        if (!$session) return redirect()->to(APP_URL . '/dashboard');

        $percentage = $session->max_score > 0 ? round(($session->total_score / $session->max_score) * 100) : 0;

        $stmt = $pdo->prepare("
            SELECT total_score, max_score FROM audit_sessions
            WHERE user_id = ? AND area_id = ? AND status = 'completed' AND id != ?
              AND (completed_at < ? OR (completed_at = ? AND id < ?))
            ORDER BY completed_at DESC, id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $session->area_id, $sessionId, $session->completed_at, $session->completed_at, $sessionId]);
        $prevSession = $stmt->fetch();

        $diffStr = '';
        if ($prevSession) {
            $prevPct = $prevSession->max_score > 0 ? round(($prevSession->total_score / $prevSession->max_score) * 100) : 0;
            $diff    = $percentage - $prevPct;
            if ($diff > 0)      $diffStr = "<span style='color:var(--success);font-weight:bold;'>&uarr; $diff% improvement</span> since last audit.";
            elseif ($diff < 0)  $diffStr = "<span style='color:var(--danger);font-weight:bold;'>&darr; " . abs($diff) . "% decrease</span> since last audit.";
            else                $diffStr = "<span style='color:var(--gray-500);font-weight:bold;'>No change</span> since last audit.";
        }

        $areas = $pdo->query('SELECT id, area_name FROM problem_areas WHERE is_active = 1')->fetchAll();

        return view('client/audit_report', compact('session', 'sessionId', 'percentage', 'diffStr', 'areas'));
    }

    public function submitFeedback(int $sessionId)
    {
        requireLogin();
        verifyCsrf();

        $userId = currentUserId();
        $pdo    = getDB();

        $stmt = $pdo->prepare("SELECT id FROM audit_sessions WHERE id = ? AND user_id = ? AND status = 'completed'");
        $stmt->execute([$sessionId, $userId]);
        if (!$stmt->fetch()) return redirect()->to(APP_URL . '/dashboard');

        $suggestedAreaId = empty($this->request->getPost('suggested_area_id')) ? null : $this->request->getPost('suggested_area_id');
        $feedbackText    = trim($this->request->getPost('feedback_text') ?? '');

        if (!empty($suggestedAreaId) || !empty($feedbackText)) {
            $exists = $pdo->prepare('SELECT id FROM client_feedback WHERE user_id = ? AND audit_session_id = ?');
            $exists->execute([$userId, $sessionId]);
            if (!$exists->fetch()) {
                if ($suggestedAreaId) {
                    $areaCheck = $pdo->prepare('SELECT id FROM problem_areas WHERE id = ? AND is_active = 1');
                    $areaCheck->execute([$suggestedAreaId]);
                    if (!$areaCheck->fetch()) $suggestedAreaId = null;
                }
                $pdo->prepare('INSERT INTO client_feedback (user_id, audit_session_id, suggested_area_id, feedback_text) VALUES (?, ?, ?, ?)')
                    ->execute([$userId, $sessionId, $suggestedAreaId, $feedbackText]);
                $feedbackId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO admin_notifications (type, message, related_user_id, related_audit_session_id, related_feedback_id) VALUES ('area_feedback', 'New client feedback submitted', ?, ?, ?)")
                    ->execute([$userId, $sessionId, $feedbackId]);
            }
        }

        flash('report', 'Feedback submitted successfully. Thank you!');
        return redirect()->to(APP_URL . '/audit-report/' . $sessionId);
    }
}
