<?php
// register.php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/whatsapp.php';
require_once __DIR__ . '/includes/otp.php';

if (!empty($_SESSION['user_id'])) redirect(APP_URL . '/dashboard.php');

$error = '';
$flash = [];

$pdo = getDB();
// Fetch areas for dropdown
$areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $areaId = filter_var($_POST['area_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
        $error = "Name must be between 2 and 100 characters.";
    } elseif (empty($email) || empty($mobile) || !$areaId) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
        $error = "Mobile number must be 10-15 digits.";
    } else {
        // Check uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR mobile = ?");
        $stmt->execute([$email, $mobile]);
        if ($stmt->fetch()) {
            $error = "A user with this email or mobile already exists.";
        } else {
            // C3: Generate a random internal password — never transmitted to the user.
            // The user will be prompted to set their own password after OTP verification.
            $passwordHash = password_hash(generateTemporaryPassword(16), PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password, area_id, must_change_password) VALUES (?, ?, ?, ?, ?, 1)");
            if ($stmt->execute([$name, $email, $mobile, $passwordHash, $areaId])) {
                $userId = (int)$pdo->lastInsertId();
                $otp = generateOTP();
                saveOTP($userId, $otp);

                // C3: Send OTP only — never transmit passwords via third-party APIs.
                $msg = "Welcome to " . APP_NAME . ", $name!\n\nYour account has been created.\nYour verification OTP is: *$otp*\n\nThe OTP is valid for " . OTP_EXPIRY_MINUTES . " minutes.\n\nAfter verifying, you will be prompted to set your own password.";
                sendWhatsAppMessage($mobile, $msg);

                $_SESSION['pending_user_id'] = $userId;
                $_SESSION['pending_mobile'] = $mobile;
                unset($_SESSION['otp_attempts']);

                redirect(APP_URL . '/otp-verify.php');
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — <?= APP_NAME ?></title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
    body { margin: 0; padding: 0; display: flex; min-height: 100vh; font-family: 'Inter', sans-serif; background: #fff; }
    .split-layout { display: flex; width: 100%; }
    
    .split-left { 
        flex: 1; 
        background-color: #0f8574; 
        background-image: url('data:image/svg+xml;utf8,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><path d="M30 26v8M26 30h8" stroke="rgba(255,255,255,0.15)" stroke-width="1.5" stroke-linecap="round"/></svg>');
        color: white; 
        display: flex; 
        flex-direction: column; 
        justify-content: center; 
        align-items: center; 
        padding: 3rem; 
        text-align: center;
    }
    .split-left h1 { font-size: 2.5rem; margin: 1rem 0 0.5rem; font-weight: 700; letter-spacing: -0.025em; }
    .split-left p.subtitle { font-size: 1.1rem; opacity: 0.9; margin-bottom: 3rem; }
    
    .benefits-list { list-style: none; padding: 0; text-align: left; margin: 0; }
    .benefits-list li { margin-bottom: 1.5rem; display: flex; align-items: center; font-size: 1.05rem; }
    .benefits-list li .icon { display: inline-flex; justify-content: center; align-items: center; width: 32px; height: 32px; background: rgba(255,255,255,0.15); border-radius: 50%; margin-right: 1rem; font-size: 0.9rem; }
    
    .split-right { 
        flex: 1; 
        background: white; 
        display: flex; 
        flex-direction: column; 
        justify-content: center; 
        padding: 3rem; 
    }
    .auth-form-container { max-width: 480px; margin: 0 auto; width: 100%; }
    .auth-form-container h2 { font-size: 2rem; color: #111827; margin-bottom: 0.5rem; font-weight: 700; }
    .auth-form-container p.desc { color: #6b7280; margin-bottom: 2rem; font-size: 0.95rem; line-height: 1.5; }
    
    .form-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
    .form-row .form-group { flex: 1; margin-bottom: 0; }
    
    .form-label { font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block; }
    .form-label span.req { color: #dc2626; }
    .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.95rem; outline: none; transition: all 0.2s; box-sizing: border-box; font-family: inherit;}
    .form-control:focus { border-color: #0f8574; box-shadow: 0 0 0 3px rgba(15, 133, 116, 0.1); }
    .form-group { margin-bottom: 1.5rem; }
    .hint-text { font-size: 0.8rem; color: #9ca3af; margin-top: 0.5rem; display: block; }
    
    .btn-submit { background: #0f8574; color: white; border: none; width: 100%; padding: 0.875rem; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; justify-content: center; align-items: center; gap: 0.5rem;}
    .btn-submit:hover { background: #0c6b5d; }
    
    .login-link { text-align: center; margin-top: 1.5rem; font-size: 0.95rem; color: #6b7280; }
    .login-link a { color: #0f8574; font-weight: 600; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }
    
    @media (max-width: 900px) {
        .split-left { display: none; }
        .split-right { padding: 2rem 1.5rem; }
    }
    @media (max-width: 500px) {
        .form-row { flex-direction: column; gap: 1.5rem; }
    }
  </style>
</head>
<body>
  <div class="split-layout">
    
    <!-- Left Pattern Side -->
    <div class="split-left">
      <div style="font-size: 3rem;">🤍</div>
      <h1>PhysioTrack</h1>
      <p class="subtitle">Your Physiotherapy Progress Platform</p>
      
      <ul class="benefits-list">
        <li><span class="icon">📊</span> Track your progress over time</li>
        <li><span class="icon">❓</span> Answer structured health audits</li>
        <li><span class="icon">💬</span> Get results via WhatsApp</li>
        <li><span class="icon">🏆</span> Graduate at 100% performance</li>
      </ul>
    </div>
    
    <!-- Right Form Side -->
    <div class="split-right">
      <div class="auth-form-container">
        <h2>Create Your Account</h2>
        <p class="desc">Enter your details below. Your login password will be sent to your WhatsApp.</p>
        
        <?php if ($error): ?>
          <div style="background:#fee2e2;color:#b91c1c;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;font-size:0.95rem;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Full Name <span class="req">*</span></label>
              <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required placeholder="Rahul Sharma">
            </div>

            <div class="form-group">
              <label class="form-label">Email Address <span class="req">*</span></label>
              <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required placeholder="rahul@email.com">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">WhatsApp Mobile <span class="req">*</span></label>
            <input type="text" name="mobile" class="form-control" value="<?= e($_POST['mobile'] ?? '') ?>" required placeholder="9876543210">
            <span class="hint-text">Your password and OTPs will be sent to this number.</span>
          </div>

          <div class="form-group">
            <label class="form-label">Area of Problem <span class="req">*</span></label>
            <select name="area_id" class="form-control" required>
              <option value="">-- Select --</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= $area->id ?>" <?= (($_POST['area_id'] ?? '') == $area->id) ? 'selected' : '' ?>><?= e($area->area_name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn-submit">🚀 Create Account</button>
          
          <div class="login-link">
            Already have an account? <a href="<?= APP_URL ?>/login.php">Sign in</a>
          </div>
        </form>
      </div>
    </div>
    
  </div>
</body>
</html>
