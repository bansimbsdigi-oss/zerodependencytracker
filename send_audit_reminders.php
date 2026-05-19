#!/usr/bin/env php
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/audit_reminders.php';

$pdo = getDB();
$result = sendAuditReminders($pdo);
if ($result['success']) {
    echo $result['message'] . PHP_EOL;
    exit(0);
}

fwrite(STDERR, $result['message'] . PHP_EOL);
exit(1);
