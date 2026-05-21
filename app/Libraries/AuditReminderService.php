<?php

namespace App\Libraries;

class AuditReminderService
{
    public static function getReminderParams(object $user, object $window): array
    {
        $userName       = trim($user->name ?: '');
        $firstName      = $userName !== '' ? explode(' ', $userName)[0] : 'there';
        $auditLabel     = ucwords(str_replace('_', ' ', $window->audit_type));
        $auditMonthYear = date('F Y', mktime(0, 0, 0, (int)$window->audit_month, 1, (int)$window->audit_year));
        return [$firstName, $auditLabel, $auditMonthYear];
    }

    public static function send(\PDO $pdo): array
    {
        $openWindows = $pdo->query('SELECT * FROM audit_windows WHERE is_open = 1')->fetchAll();
        if (empty($openWindows)) {
            return ['success' => true, 'message' => 'No open audit windows found.'];
        }

        $notStartedStmt = $pdo->prepare(
            'SELECT u.id, u.name, u.mobile FROM users u
             WHERE u.is_graduated = 0 AND u.mobile <> \'\'
               AND NOT EXISTS (SELECT 1 FROM audit_sessions aus WHERE aus.user_id = u.id AND aus.audit_window_id = ?)'
        );

        $inProgressStmt = $pdo->prepare(
            'SELECT u.id, u.name, u.mobile FROM users u
             JOIN audit_sessions aus ON aus.user_id = u.id
             WHERE aus.audit_window_id = ? AND aus.status = \'in_progress\'
               AND u.is_graduated = 0 AND u.mobile <> \'\''
        );

        $existingLogStmt = $pdo->prepare(
            'SELECT 1 FROM audit_reminder_logs WHERE user_id = ? AND audit_window_id = ? AND reminder_type = ? LIMIT 1'
        );

        $insertLogStmt = $pdo->prepare(
            'INSERT INTO audit_reminder_logs (user_id, audit_window_id, reminder_type) VALUES (?, ?, ?)'
        );

        $sentCount   = 0;
        $failedCount = 0;

        foreach ($openWindows as $window) {
            foreach (['not_started', 'in_progress'] as $reminderType) {
                $stmt = $reminderType === 'not_started' ? $notStartedStmt : $inProgressStmt;
                $stmt->execute([$window->id]);
                $users = $stmt->fetchAll();

                foreach ($users as $user) {
                    $existingLogStmt->execute([$user->id, $window->id, $reminderType]);
                    if ($existingLogStmt->fetchColumn()) continue;

                    [$firstName, $auditLabel, $auditMonthYear] = self::getReminderParams($user, $window);

                    if (!WhatsAppService::sendAuditStartReminder($user->mobile, $firstName, $auditLabel, $auditMonthYear)) {
                        $failedCount++;
                        continue;
                    }

                    try {
                        $insertLogStmt->execute([$user->id, $window->id, $reminderType]);
                        $sentCount++;
                    } catch (\Exception $e) {
                        error_log('Failed to log audit reminder: ' . $e->getMessage());
                    }
                }
            }
        }

        if ($sentCount === 0 && $failedCount === 0) {
            return ['success' => true, 'message' => 'No new audit reminders were needed.'];
        }

        $message = $sentCount . ' WhatsApp reminder' . ($sentCount === 1 ? '' : 's') . ' sent.';
        if ($failedCount > 0) {
            $message .= ' ' . $failedCount . ' failed to send.';
        }

        return ['success' => $failedCount === 0, 'message' => $message];
    }
}
