<?php
// login.php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/whatsapp.php';

if (!empty($_SESSION['user_id'])) redirect(APP_URL . '/dashboard.php');

$error = '';
$flash = '';

if (isset($_GET['registered'])) {
    $flash = 'Registration successful! You can now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        $error = "Please enter both your email/WhatsApp and password.";
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, mobile, password FROM users WHERE email = ? OR mobile = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user->password)) {
            $otp = generateOTP();
            saveOTP($user->id, $otp);
            
            $msg = APP_NAME . ": Your login code is *$otp*. Valid for 10 minutes.";
            sendWhatsAppMessage($user->mobile, $msg);
            
            $_SESSION['pending_user_id'] = $user->id;
            $_SESSION['pending_mobile'] = $user->mobile;
            redirect(APP_URL . '/otp-verify.php');
        } else {
            $error = "Invalid credentials. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
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
    
    .form-label { font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block; }
    .form-label span.req { color: #dc2626; }
    .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.95rem; outline: none; transition: all 0.2s; box-sizing: border-box; font-family: inherit;}
    .form-control:focus { border-color: #0f8574; box-shadow: 0 0 0 3px rgba(15, 133, 116, 0.1); }
    .form-group { margin-bottom: 1.5rem; }
    
    .btn-submit { background: #0f8574; color: white; border: none; width: 100%; padding: 0.875rem; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; justify-content: center; align-items: center; gap: 0.5rem;}
    .btn-submit:hover { background: #0c6b5d; }
    
    .login-link { text-align: center; margin-top: 1.5rem; font-size: 0.95rem; color: #6b7280; }
    .login-link a { color: #0f8574; font-weight: 600; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }
    
    @media (max-width: 900px) {
        .split-left { display: none; }
        .split-right { padding: 2rem 1.5rem; }
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
        <li><span class="icon">🔒</span> Secure OTP-based login</li>
        <li><span class="icon">📱</span> Use email or WhatsApp number</li>
        <li><span class="icon">⏱️</span> OTP valid for 10 minutes</li>
      </ul>
    </div>
    
    <!-- Right Form Side -->
    <div class="split-right">
      <div class="auth-form-container">
        <h2>Welcome Back</h2>
        <p class="desc">Sign in with your email or WhatsApp number. An OTP will be sent to verify you.</p>
        
        <?php if ($flash): ?>
          <div style="background:#dcfce7;color:#166534;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;font-size:0.95rem;"><?= e($flash) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div style="background:#fee2e2;color:#b91c1c;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;font-size:0.95rem;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          
          <div class="form-group">
            <label class="form-label">Email or WhatsApp Number <span class="req">*</span></label>
            <input type="text" name="identifier" class="form-control" value="<?= e($_POST['identifier'] ?? '') ?>" placeholder="rahul@email.com" required autofocus>
          </div>
          
          <div class="form-group">
            <label class="form-label">Password <span class="req">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>

          <button type="submit" class="btn-submit">Login</button>
          
          <div class="login-link">
            New here? <a href="<?= APP_URL ?>/register.php">Create an account</a>
          </div>
        </form>
      </div>
    </div>
    
  </div>
</body>
</html>
