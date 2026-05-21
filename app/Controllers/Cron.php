<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Libraries\AuditReminderService;

class Cron extends Controller
{
    public function sendAuditReminders()
    {
        // Ensure this is only called from CLI
        if (!is_cli()) {
            return $this->response->setStatusCode(403)->setBody('CLI access only');
        }

        $pdo = getDB();
        $result = AuditReminderService::send($pdo);

        if ($result['success']) {
            echo $result['message'] . PHP_EOL;
            return;
        }

        fwrite(STDERR, $result['message'] . PHP_EOL);
        exit(1);
    }
}
