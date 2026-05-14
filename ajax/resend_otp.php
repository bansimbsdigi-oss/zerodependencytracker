<?php
// ajax/resend_otp.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/otp.php';
require_once __DIR__ . '/../includes/whatsapp.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (empty($_SESSION['pending_user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

verifyCsrf();

$userId = (int)$_SESSION['pending_user_id'];
$mobile = $_SESSION['pending_mobile'] ?? '';
$pdo    = getDB();

// Server-side cooldown: at most one resend per 60 seconds.
$stmt = $pdo->prepare("SELECT created_at FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$last = $stmt->fetch();

$cooldown = 60;
if ($last) {
    $elapsed = time() - strtotime($last->created_at);
    if ($elapsed < $cooldown) {
        $remaining = $cooldown - $elapsed;
        echo json_encode([
            'status'    => 'error',
            'message'   => "Please wait {$remaining} second(s) before resending.",
            'remaining' => $remaining,
            'csrfToken' => getCsrf(),
        ]);
        exit;
    }
}

$otp  = generateOTP();
saveOTP($userId, $otp);

$msg  = "Your " . APP_NAME . " verification OTP is: *{$otp}*\n\nValid for " . OTP_EXPIRY_MINUTES . " minutes.";
$sent = sendWhatsAppMessage($mobile, $msg);

if ($sent) {
    echo json_encode([
        'status'    => 'success',
        'message'   => 'OTP resent. Check WhatsApp.',
        'sentAt'    => time(),
        'csrfToken' => getCsrf(),
    ]);
} else {
    echo json_encode([
        'status'    => 'error',
        'message'   => 'Could not send OTP. Please try again.',
        'csrfToken' => getCsrf(),
    ]);
}
