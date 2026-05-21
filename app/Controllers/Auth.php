<?php

namespace App\Controllers;

use App\Libraries\OtpService;
use App\Libraries\WhatsAppService;
use CodeIgniter\Controller;

class Auth extends Controller
{
    public function index()
    {
        if (!empty($_SESSION['user_id'])) {
            return redirect()->to(APP_URL . '/dashboard');
        }
        return view('auth/login', ['error' => '']);
    }

    public function login()
    {
        if (!empty($_SESSION['user_id'])) {
            return redirect()->to(APP_URL . '/dashboard');
        }

        verifyCsrf();

        $countryCode = preg_replace('/\D/', '', trim($this->request->getPost('country_code') ?? '91'));
        $mobileLocal = preg_replace('/\D/', '', trim($this->request->getPost('mobile_local') ?? ''));
        $mobile      = $countryCode . $mobileLocal;

        if (empty($mobileLocal) || strlen($mobileLocal) < 7) {
            return view('auth/login', ['error' => 'Please enter a valid mobile number.']);
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, mobile FROM users WHERE mobile = ? LIMIT 1');
        $stmt->execute([$mobile]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = OtpService::generate();
            OtpService::save($user->id, $otp);
            WhatsAppService::sendOtp($user->mobile, $otp);

            $_SESSION['pending_user_id'] = $user->id;
            $_SESSION['pending_mobile']  = $user->mobile;
            unset($_SESSION['otp_attempts']);

            return redirect()->to(APP_URL . '/otp-verify');
        }

        return view('auth/login', ['error' => 'This mobile number is not registered. Please check and try again.']);
    }

    public function registerForm()
    {
        if (!empty($_SESSION['user_id'])) {
            return redirect()->to(APP_URL . '/dashboard');
        }

        $pdo   = getDB();
        $areas = $pdo->query('SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order ASC')->fetchAll();

        return view('auth/register', ['error' => '', 'areas' => $areas]);
    }

    public function register()
    {
        if (!empty($_SESSION['user_id'])) {
            return redirect()->to(APP_URL . '/dashboard');
        }

        verifyCsrf();

        $pdo   = getDB();
        $areas = $pdo->query('SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order ASC')->fetchAll();

        $name        = trim($this->request->getPost('name') ?? '');
        $email       = trim($this->request->getPost('email') ?? '');
        $countryCode = preg_replace('/\D/', '', trim($this->request->getPost('country_code') ?? '91'));
        $mobileLocal = preg_replace('/\D/', '', trim($this->request->getPost('mobile_local') ?? ''));
        $mobile      = $countryCode . $mobileLocal;
        $areaId      = filter_var($this->request->getPost('area_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $data = ['areas' => $areas];

        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            return view('auth/register', $data + ['error' => 'Name must be between 2 and 100 characters.']);
        }
        if (empty($email) || empty($mobileLocal) || !$areaId) {
            return view('auth/register', $data + ['error' => 'All fields are required.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return view('auth/register', $data + ['error' => 'Invalid email format.']);
        }
        if (!preg_match('/^[0-9]{10,17}$/', $mobile)) {
            return view('auth/register', $data + ['error' => 'Invalid mobile number for the selected country code.']);
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR mobile = ?');
        $stmt->execute([$email, $mobile]);
        if ($stmt->fetch()) {
            return view('auth/register', $data + ['error' => 'A user with this email or mobile already exists.']);
        }

        $passwordHash = password_hash(generateTemporaryPassword(16), PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, mobile, password, area_id, must_change_password) VALUES (?, ?, ?, ?, ?, 1)');

        if ($stmt->execute([$name, $email, $mobile, $passwordHash, $areaId])) {
            $userId = (int)$pdo->lastInsertId();
            $otp    = OtpService::generate();
            OtpService::save($userId, $otp);
            WhatsAppService::sendOtp($mobile, $otp);

            $pdo->prepare("INSERT INTO admin_notifications (type, message, related_user_id) VALUES ('new_registration', ?, ?)")
                ->execute(["New client registered: $name ($email)", $userId]);

            $_SESSION['pending_user_id'] = $userId;
            $_SESSION['pending_mobile']  = $mobile;
            unset($_SESSION['otp_attempts']);

            return redirect()->to(APP_URL . '/otp-verify');
        }

        return view('auth/register', $data + ['error' => 'Registration failed. Please try again.']);
    }

    public function otpForm()
    {
        if (!empty($_SESSION['user_id'])) {
            return redirect()->to(APP_URL . '/dashboard');
        }
        if (empty($_SESSION['pending_user_id'])) {
            return redirect()->to(APP_URL . '/login');
        }

        $userId = $_SESSION['pending_user_id'];
        $mobile = $_SESSION['pending_mobile'] ?? '';

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT created_at FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        $lastOtpRow = $stmt->fetch();
        $otpSentAt  = $lastOtpRow ? strtotime($lastOtpRow->created_at) : (time() - 60);

        return view('auth/otp_verify', ['error' => '', 'mobile' => $mobile, 'otpSentAt' => $otpSentAt]);
    }

    public function otpVerify()
    {
        if (empty($_SESSION['pending_user_id'])) {
            return redirect()->to(APP_URL . '/login');
        }

        $maxAttempts = 5;
        if (!isset($_SESSION['otp_attempts'])) {
            $_SESSION['otp_attempts'] = 0;
        }
        if ($_SESSION['otp_attempts'] >= $maxAttempts) {
            unset($_SESSION['pending_user_id'], $_SESSION['pending_mobile'], $_SESSION['otp_attempts']);
            return redirect()->to(APP_URL . '/login');
        }

        verifyCsrf();

        $userId = $_SESSION['pending_user_id'];
        $mobile = $_SESSION['pending_mobile'] ?? '';

        $otp = '';
        for ($i = 1; $i <= 6; $i++) {
            $otp .= $this->request->getPost('d' . $i) ?? '';
        }

        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT created_at FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        $lastOtpRow = $stmt->fetch();
        $otpSentAt  = $lastOtpRow ? strtotime($lastOtpRow->created_at) : (time() - 60);

        $viewData = ['mobile' => $mobile, 'otpSentAt' => $otpSentAt];

        if (strlen($otp) !== 6 || !ctype_digit($otp)) {
            return view('auth/otp_verify', $viewData + ['error' => 'Please enter all 6 digits.']);
        }

        $hashedOtp = hash('sha256', $otp);
        $nowStr    = date('Y-m-d H:i:s');
        $stmt      = $pdo->prepare('SELECT id FROM otps WHERE user_id = ? AND otp_code = ? AND is_used = 0 AND expires_at > ? LIMIT 1');
        $stmt->execute([$userId, $hashedOtp, $nowStr]);
        $validOtp  = $stmt->fetch();

        if ($validOtp) {
            $pdo->prepare('UPDATE otps SET is_used = 1 WHERE id = ?')->execute([$validOtp->id]);
            unset($_SESSION['otp_attempts']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            unset($_SESSION['pending_user_id'], $_SESSION['pending_mobile']);
            return redirect()->to(APP_URL . '/dashboard');
        }

        $_SESSION['otp_attempts']++;
        $remaining = $maxAttempts - $_SESSION['otp_attempts'];
        if ($remaining <= 0) {
            unset($_SESSION['pending_user_id'], $_SESSION['pending_mobile'], $_SESSION['otp_attempts']);
            return redirect()->to(APP_URL . '/login');
        }

        return view('auth/otp_verify', $viewData + ['error' => "Invalid or expired OTP. $remaining attempt(s) remaining."]);
    }

    public function logout()
    {
        // CI4 4.4+ getMethod() returns uppercase ('POST', 'GET')
        if (strtolower($this->request->getMethod()) !== 'post') {
            return redirect()->to(APP_URL . '/login');
        }
        // 1. Wipe session data so even if the file is re-written it has no auth keys
        $_SESSION = [];
        // 2. Delete the CI4 session file and expire the browser cookie
        session()->destroy();
        // 3. Explicitly expire the cookie in the browser (belt-and-suspenders)
        setcookie('ci_session', '', time() - 3600, '/');
        // 4. Use raw header + exit so CI4's end-of-request pipeline
        //    (which can call session_write_close and re-persist the session)
        //    never runs.
        header('Location: ' . APP_URL . '/login');
        exit;
    }
}
