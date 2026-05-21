<?php

namespace App\Controllers;

use App\Libraries\OtpService;
use App\Libraries\WhatsAppService;
use CodeIgniter\Controller;

class Ajax extends Controller
{
    public function resendOtp()
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['pending_user_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
            exit;
        }

        verifyCsrf();

        $userId  = (int)$_SESSION['pending_user_id'];
        $mobile  = $_SESSION['pending_mobile'] ?? '';
        $pdo     = getDB();

        $stmt = $pdo->prepare('SELECT created_at FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
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

        $otp  = OtpService::generate();
        OtpService::save($userId, $otp);
        $sent = WhatsAppService::sendOtp($mobile, $otp);

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
        exit;
    }

    public function setTutorialDone()
    {
        // Session is guaranteed started via clientauth filter (session()->get).
        $userId = (int)(session()->get('user_id') ?? 0);
        if (!$userId) {
            return $this->response->setStatusCode(403)
                ->setContentType('application/json')
                ->setBody(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
        }

        if ($this->request->getPost('action') !== 'done') {
            return $this->response->setContentType('application/json')
                ->setBody(json_encode(['status' => 'error', 'message' => 'Invalid request']));
        }

        $pdo  = getDB();
        $pdo->prepare('UPDATE users SET tutorial_done = 1 WHERE id = ?')->execute([$userId]);

        return $this->response->setContentType('application/json')
            ->setBody(json_encode(['status' => 'success']));
    }
}
