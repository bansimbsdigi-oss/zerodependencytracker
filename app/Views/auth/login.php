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
    .split-left { flex: 1; background-color: #0f8574; background-image: url('data:image/svg+xml;utf8,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><path d="M30 26v8M26 30h8" stroke="rgba(255,255,255,0.15)" stroke-width="1.5" stroke-linecap="round"/></svg>'); color: white; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 3rem; text-align: center; }
    .split-left h1 { font-size: 2.5rem; margin: 1rem 0 0.5rem; font-weight: 700; letter-spacing: -0.025em; }
    .split-left p.subtitle { font-size: 1.1rem; opacity: 0.9; margin-bottom: 3rem; }
    .benefits-list { list-style: none; padding: 0; text-align: left; margin: 0; }
    .benefits-list li { margin-bottom: 1.5rem; display: flex; align-items: center; font-size: 1.05rem; }
    .benefits-list li .icon { display: inline-flex; justify-content: center; align-items: center; width: 32px; height: 32px; background: rgba(255,255,255,0.15); border-radius: 50%; margin-right: 1rem; font-size: 0.9rem; }
    .split-right { flex: 1; background: white; display: flex; flex-direction: column; justify-content: center; padding: 3rem; }
    .auth-form-container { max-width: 480px; margin: 0 auto; width: 100%; }
    .auth-form-container h2 { font-size: 2rem; color: #111827; margin-bottom: 0.5rem; font-weight: 700; }
    .auth-form-container p.desc { color: #6b7280; margin-bottom: 2rem; font-size: 0.95rem; line-height: 1.5; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block; }
    .form-group { margin-bottom: 1.5rem; }
    .mobile-input-wrap { display: flex; border: 1px solid #d1d5db; border-radius: 0.5rem; overflow: hidden; transition: box-shadow 0.2s, border-color 0.2s; }
    .mobile-input-wrap:focus-within { border-color: #0f8574; box-shadow: 0 0 0 3px rgba(15, 133, 116, 0.1); }
    .mobile-prefix { background: #f9fafb; border-right: 1px solid #d1d5db; padding: 0.75rem 1rem; font-size: 0.95rem; font-weight: 600; color: #374151; white-space: nowrap; display: flex; align-items: center; gap: 0.4rem; }
    .mobile-field { flex: 1; border: none; outline: none; padding: 0.75rem 1rem; font-size: 0.95rem; font-family: inherit; background: transparent; }
    .btn-submit { background: #0f8574; color: white; border: none; width: 100%; padding: 0.875rem; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
    .btn-submit:hover { background: #0c6b5d; }
    .login-link { text-align: center; margin-top: 1.5rem; font-size: 0.95rem; color: #6b7280; }
    .login-link a { color: #0f8574; font-weight: 600; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }
    @media (max-width: 900px) {
      body { background: #0f8574; }
      .split-layout { flex-direction: column; min-height: 100vh; background: #0f8574; overflow-x: hidden; }
      .split-left { flex: none; width: 100%; box-sizing: border-box; padding: 2rem 1.25rem 1.5rem; align-items: flex-start; text-align: left; justify-content: flex-start; }
      .split-left h1 { font-size: 1.65rem; margin: 0.6rem 0 0.35rem; }
      .split-left p.subtitle { font-size: 0.92rem; margin-bottom: 1.1rem; }
      .split-right { flex: none; width: 100%; box-sizing: border-box; background: #0f8574; padding: 0 1.25rem 2.5rem; display: block; }
      .auth-form-container { width: 100%; box-sizing: border-box; background: #fff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 8px 40px rgba(0,0,0,0.3); margin: 0; }
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
        <li><span class="icon">📱</span> Enter your WhatsApp number</li>
        <li><span class="icon">🔒</span> Receive a secure OTP on WhatsApp</li>
        <li><span class="icon">⚡</span> No password needed</li>
      </ul>
    </div>
    <div class="split-right">
      <div class="auth-form-container">
        <h2>Welcome Back</h2>
        <p class="desc">Enter your registered WhatsApp number and we'll send you a one-time code to sign in.</p>

        <?php if (!empty($error)): ?>
          <div style="background:#fee2e2;color:#b91c1c;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;font-size:0.95rem;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/login">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">
          <input type="hidden" name="country_code" value="91">

          <div class="form-group">
            <label class="form-label">WhatsApp Mobile Number</label>
            <div class="mobile-input-wrap">
              <span class="mobile-prefix">🇮🇳 +91</span>
              <input type="tel" name="mobile_local" class="mobile-field"
                     placeholder="98765 43210" maxlength="10"
                     autofocus autocomplete="tel" inputmode="numeric"
                     pattern="[0-9]{10}" required>
            </div>
          </div>

          <button type="submit" class="btn-submit">Send OTP on WhatsApp</button>

          <div class="login-link">
            New here? <a href="<?= APP_URL ?>/register">Create an account</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
