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
$areas = $pdo->query("SELECT id, area_name FROM problem_areas WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $countryCode = preg_replace('/\D/', '', trim($_POST['country_code'] ?? '91'));
    $mobileLocal = preg_replace('/\D/', '', trim($_POST['mobile_local'] ?? ''));
    $mobile      = $countryCode . $mobileLocal;   // full international number, no +
    $areaId      = filter_var($_POST['area_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
        $error = "Name must be between 2 and 100 characters.";
    } elseif (empty($email) || empty($mobileLocal) || !$areaId) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^[0-9]{10,17}$/', $mobile)) {
        $error = "Invalid mobile number for the selected country code.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR mobile = ?");
        $stmt->execute([$email, $mobile]);
        if ($stmt->fetch()) {
            $error = "A user with this email or mobile already exists.";
        } else {
            $passwordHash = password_hash(generateTemporaryPassword(16), PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password, area_id, must_change_password) VALUES (?, ?, ?, ?, ?, 1)");
            if ($stmt->execute([$name, $email, $mobile, $passwordHash, $areaId])) {
                $userId = (int)$pdo->lastInsertId();
                $otp    = generateOTP();
                saveOTP($userId, $otp);

                sendWhatsAppOTP($mobile, $otp);

                // Notify admin: new client registered
                $pdo->prepare("INSERT INTO admin_notifications (type, message, related_user_id) VALUES ('new_registration', ?, ?)")
                    ->execute(["New client registered: $name ($email)", $userId]);

                $_SESSION['pending_user_id'] = $userId;
                $_SESSION['pending_mobile']  = $mobile;
                unset($_SESSION['otp_attempts']);

                redirect(APP_URL . '/otp-verify.php');
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

$postedCode  = htmlspecialchars($_POST['country_code']  ?? '91',          ENT_QUOTES);
$postedLocal = htmlspecialchars($_POST['mobile_local']  ?? '',             ENT_QUOTES);
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

.form-label { font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block; }
    .form-label span.req { color: #dc2626; }
    .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.95rem; outline: none; transition: all 0.2s; box-sizing: border-box; font-family: inherit; }
    .form-control:focus { border-color: #0f8574; box-shadow: 0 0 0 3px rgba(15, 133, 116, 0.1); }
    .form-control.input-error { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
    .form-control.input-ok   { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1); }
    .form-group { margin-bottom: 1.5rem; }

    /* Phone input row */
    .phone-input-wrap { display: flex; gap: 0; }
    .phone-input-wrap .country-select {
        flex: 0 0 auto;
        padding: 0.75rem 0.5rem 0.75rem 0.75rem;
        border: 1px solid #d1d5db;
        border-right: none;
        border-radius: 0.5rem 0 0 0.5rem;
        font-size: 0.9rem;
        outline: none;
        background: #f9fafb;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
        max-width: 130px;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        padding-right: 24px;
    }
    .phone-input-wrap .country-select:focus { border-color: #0f8574; box-shadow: 0 0 0 3px rgba(15, 133, 116, 0.1); z-index: 1; position: relative; }
    .phone-input-wrap .mobile-input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0 0.5rem 0.5rem 0;
        font-size: 0.95rem;
        outline: none;
        transition: all 0.2s;
        font-family: inherit;
        width: 100%;
        box-sizing: border-box;
    }
    .phone-input-wrap .mobile-input:focus { border-color: #0f8574; box-shadow: 0 0 0 3px rgba(15, 133, 116, 0.1); z-index: 1; position: relative; }
    .phone-input-wrap .mobile-input.input-error { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
    .phone-input-wrap .mobile-input.input-ok   { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1); }

    .hint-text { font-size: 0.8rem; color: #9ca3af; margin-top: 0.5rem; display: block; }
    .hint-text.error { color: #dc2626; }
    .hint-text.ok    { color: #16a34a; }

    .btn-submit { background: #0f8574; color: white; border: none; width: 100%; padding: 0.875rem; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; justify-content: center; align-items: center; gap: 0.5rem; }
    .btn-submit:hover { background: #0c6b5d; }

    .login-link { text-align: center; margin-top: 1.5rem; font-size: 0.95rem; color: #6b7280; }
    .login-link a { color: #0f8574; font-weight: 600; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }

    @media (max-width: 900px) {
        body { background: #0f8574; }
        .split-layout {
            flex-direction: column;
            min-height: 100vh;
            background: #0f8574;
            overflow-x: hidden;
        }
        .split-left {
            flex: none;
            width: 100%;
            box-sizing: border-box;
            padding: 2rem 1.25rem 1.5rem;
            align-items: flex-start;
            text-align: left;
            justify-content: flex-start;
            background-color: #0f8574;
        }
        .split-left h1 { font-size: 1.65rem; margin: 0.6rem 0 0.35rem; }
        .split-left p.subtitle { font-size: 0.92rem; margin-bottom: 1.1rem; opacity: 0.9; }
        .benefits-list { display: block; }
        .benefits-list li { margin-bottom: 0.65rem; font-size: 0.92rem; }
        .split-right {
            flex: none;
            width: 100%;
            box-sizing: border-box;
            background: #0f8574;
            padding: 0 1.25rem 2.5rem;
            display: block;
        }
        .auth-form-container {
            width: 100%;
            box-sizing: border-box;
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 40px rgba(0,0,0,0.3);
            margin: 0;
        }
        .auth-form-container h2 { font-size: 1.5rem; }
    }

    @media (max-width: 400px) {
        .split-left { padding: 1.5rem 1rem 1.25rem; }
        .split-right { padding: 0 1rem 2rem; }
    }
  </style>
</head>
<body>
  <div class="split-layout">

    <div class="split-left">
      <div style="font-size: 3rem;">🤍</div>
      <h1>Zero Dependency Tracker</h1>
      <p class="subtitle">Your Physiotherapy Progress Platform</p>
      <ul class="benefits-list">
        <li><span class="icon">📊</span> Track your progress over time</li>
        <li><span class="icon">❓</span> Answer structured health audits</li>
        <li><span class="icon">💬</span> Get results via WhatsApp</li>
        <li><span class="icon">🏆</span> Graduate at 100% performance</li>
      </ul>
    </div>

    <div class="split-right">
      <div class="auth-form-container">
        <h2>Create Your Account</h2>
        <p class="desc">Enter your details below. A verification OTP will be sent to your WhatsApp.</p>

        <?php if ($error): ?>
          <div style="background:#fee2e2;color:#b91c1c;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;font-size:0.95rem;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="regForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

          <div class="form-group">
            <label class="form-label">Full Name <span class="req">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required placeholder="Rahul Sharma" minlength="2" maxlength="100">
          </div>

          <div class="form-group">
            <label class="form-label">Email Address <span class="req">*</span></label>
            <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required placeholder="rahul@email.com" autocomplete="email">
          </div>

          <div class="form-group">
            <label class="form-label">WhatsApp Mobile <span class="req">*</span></label>
            <div class="phone-input-wrap">
              <select name="country_code" id="countryCode" class="country-select" aria-label="Country code">
                <option value="91"  data-flag="🇮🇳" data-len="10" data-pattern="^[6-9]\d{9}$"   data-hint="10 digits starting with 6-9" <?= $postedCode === '91'  ? 'selected' : '' ?>>🇮🇳 +91</option>
                <option value="1"   data-flag="🇺🇸" data-len="10" data-pattern="^\d{10}$"        data-hint="10 digits"                   <?= $postedCode === '1'   ? 'selected' : '' ?>>🇺🇸 +1</option>
                <option value="44"  data-flag="🇬🇧" data-len="10" data-pattern="^\d{10,11}$"     data-hint="10-11 digits"                <?= $postedCode === '44'  ? 'selected' : '' ?>>🇬🇧 +44</option>
                <option value="971" data-flag="🇦🇪" data-len="9"  data-pattern="^[0-9]\d{8}$"   data-hint="9 digits"                    <?= $postedCode === '971' ? 'selected' : '' ?>>🇦🇪 +971</option>
                <option value="966" data-flag="🇸🇦" data-len="9"  data-pattern="^[0-9]\d{8}$"   data-hint="9 digits"                    <?= $postedCode === '966' ? 'selected' : '' ?>>🇸🇦 +966</option>
                <option value="974" data-flag="🇶🇦" data-len="8"  data-pattern="^\d{8}$"         data-hint="8 digits"                    <?= $postedCode === '974' ? 'selected' : '' ?>>🇶🇦 +974</option>
                <option value="973" data-flag="🇧🇭" data-len="8"  data-pattern="^\d{8}$"         data-hint="8 digits"                    <?= $postedCode === '973' ? 'selected' : '' ?>>🇧🇭 +973</option>
                <option value="965" data-flag="🇰🇼" data-len="8"  data-pattern="^\d{8}$"         data-hint="8 digits"                    <?= $postedCode === '965' ? 'selected' : '' ?>>🇰🇼 +965</option>
                <option value="968" data-flag="🇴🇲" data-len="8"  data-pattern="^\d{8}$"         data-hint="8 digits"                    <?= $postedCode === '968' ? 'selected' : '' ?>>🇴🇲 +968</option>
                <option value="61"  data-flag="🇦🇺" data-len="9"  data-pattern="^\d{9}$"         data-hint="9 digits"                    <?= $postedCode === '61'  ? 'selected' : '' ?>>🇦🇺 +61</option>
                <option value="65"  data-flag="🇸🇬" data-len="8"  data-pattern="^\d{8}$"         data-hint="8 digits"                    <?= $postedCode === '65'  ? 'selected' : '' ?>>🇸🇬 +65</option>
                <option value="60"  data-flag="🇲🇾" data-len="10" data-pattern="^\d{9,10}$"      data-hint="9-10 digits"                 <?= $postedCode === '60'  ? 'selected' : '' ?>>🇲🇾 +60</option>
                <option value="27"  data-flag="🇿🇦" data-len="9"  data-pattern="^\d{9}$"         data-hint="9 digits"                    <?= $postedCode === '27'  ? 'selected' : '' ?>>🇿🇦 +27</option>
                <option value="49"  data-flag="🇩🇪" data-len="11" data-pattern="^\d{10,12}$"     data-hint="10-12 digits"                <?= $postedCode === '49'  ? 'selected' : '' ?>>🇩🇪 +49</option>
                <option value="33"  data-flag="🇫🇷" data-len="9"  data-pattern="^\d{9}$"         data-hint="9 digits"                    <?= $postedCode === '33'  ? 'selected' : '' ?>>🇫🇷 +33</option>
              </select>
              <input type="text" name="mobile_local" id="mobileLocal" class="mobile-input" inputmode="numeric"
                     value="<?= $postedLocal ?>" required autocomplete="tel-national"
                     placeholder="Enter mobile number">
            </div>
            <span class="hint-text" id="mobileHint">Enter your WhatsApp number without country code.</span>
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

  <script>
    (function () {
      const codeSelect  = document.getElementById('countryCode');
      const mobileInput = document.getElementById('mobileLocal');
      const hintEl      = document.getElementById('mobileHint');

      function getRule() {
        const opt = codeSelect.options[codeSelect.selectedIndex];
        return {
          pattern : new RegExp(opt.dataset.pattern),
          hint    : opt.dataset.hint,
          maxLen  : parseInt(opt.dataset.len, 10),
        };
      }

      function applyRule() {
        const rule = getRule();
        mobileInput.maxLength  = rule.maxLen + 2;  // small buffer
        mobileInput.placeholder = rule.hint;
        hintEl.textContent      = 'Enter ' + rule.hint + ' (without country code).';
        hintEl.className        = 'hint-text';
        mobileInput.classList.remove('input-ok', 'input-error');
      }

      function validateMobile() {
        const val  = mobileInput.value.replace(/\D/g, '');
        const rule = getRule();

        if (val === '') {
          hintEl.textContent = 'Enter ' + rule.hint + ' (without country code).';
          hintEl.className   = 'hint-text';
          mobileInput.classList.remove('input-ok', 'input-error');
          return;
        }

        if (rule.pattern.test(val)) {
          hintEl.textContent = '✓ Looks good';
          hintEl.className   = 'hint-text ok';
          mobileInput.classList.add('input-ok');
          mobileInput.classList.remove('input-error');
        } else {
          hintEl.textContent = '✗ Expected ' + rule.hint + ' for this country code.';
          hintEl.className   = 'hint-text error';
          mobileInput.classList.add('input-error');
          mobileInput.classList.remove('input-ok');
        }
      }

      // Strip non-digits as the user types
      mobileInput.addEventListener('input', function () {
        const pos = this.selectionStart;
        const cleaned = this.value.replace(/\D/g, '');
        if (this.value !== cleaned) {
          this.value = cleaned;
          try { this.setSelectionRange(pos - 1, pos - 1); } catch (_) {}
        }
        validateMobile();
      });

      codeSelect.addEventListener('change', function () {
        applyRule();
        if (mobileInput.value) validateMobile();
      });

      // Block form submit if mobile fails validation
      document.getElementById('regForm').addEventListener('submit', function (e) {
        const val  = mobileInput.value.replace(/\D/g, '');
        const rule = getRule();
        if (!rule.pattern.test(val)) {
          e.preventDefault();
          mobileInput.classList.add('input-error');
          mobileInput.classList.remove('input-ok');
          hintEl.textContent = '✗ Expected ' + rule.hint + ' for the selected country code.';
          hintEl.className   = 'hint-text error';
          mobileInput.focus();
        }
      });

      // Init on page load
      applyRule();
      if (mobileInput.value) validateMobile();
    })();
  </script>
</body>
</html>
