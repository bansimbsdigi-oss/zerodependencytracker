<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <style>
    .otp-inputs { display: flex; gap: 8px; justify-content: center; margin-bottom: 1.5rem; }
    .otp-digit { width: 50px; height: 60px; text-align: center; font-size: 1.5rem; font-weight: 700; border-radius: var(--radius); border: 2px solid var(--gray-300); }
    .otp-digit:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(13,148,136,0.15); }
    .btn-resend { background: transparent; border: 2px solid var(--gray-300); color: var(--gray-600, #4b5563); width: 100%; padding: 0.65rem; border-radius: var(--radius); font: inherit; font-size: 0.95rem; cursor: pointer; transition: border-color 0.2s, color 0.2s; }
    .btn-resend:not(:disabled):hover { border-color: var(--primary); color: var(--primary); }
    .btn-resend:disabled { opacity: 0.55; cursor: not-allowed; }
    #resend-feedback { margin-top: 0.5rem; font-size: 0.88rem; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <div class="auth-box">
      <div class="auth-header">
        <div class="auth-logo" style="background:var(--gray-900);">&#x1F4AC;</div>
        <h2>Check WhatsApp</h2>
        <p class="text-muted">A 6-digit OTP was sent to your registered WhatsApp number <strong><?= e(maskMobile($mobile)) ?></strong></p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/otp-verify" id="otp-form">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrf()) ?>">

        <div class="otp-inputs">
          <input type="text" name="d1" class="otp-digit" maxlength="1" required autofocus>
          <input type="text" name="d2" class="otp-digit" maxlength="1" required>
          <input type="text" name="d3" class="otp-digit" maxlength="1" required>
          <input type="text" name="d4" class="otp-digit" maxlength="1" required>
          <input type="text" name="d5" class="otp-digit" maxlength="1" required>
          <input type="text" name="d6" class="otp-digit" maxlength="1" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Verify &amp; Sign In</button>

        <div style="margin-top: 1rem;">
          <button type="button" id="resend-btn" class="btn-resend" disabled>
            Resend OTP <span id="resend-timer"></span>
          </button>
          <div id="resend-feedback"></div>
        </div>

        <div class="text-center" style="margin-top: 1.5rem;">
          <p class="text-muted"><a href="<?= APP_URL ?>/login">Use a different number</a></p>
        </div>
      </form>
    </div>
  </div>

  <script>
    const inputs = document.querySelectorAll('.otp-digit');
    inputs.forEach((input, index) => {
      input.addEventListener('keyup', (e) => {
        if (e.key >= 0 && e.key <= 9) {
          if (index < inputs.length - 1) inputs[index + 1].focus();
        } else if (e.key === 'Backspace') {
          if (index > 0) inputs[index - 1].focus();
        }
      });
    });

    (function () {
      const COOLDOWN   = 60;
      let   sentAt     = <?= (int)$otpSentAt ?>;
      const RESEND_URL = <?= json_encode(APP_URL . '/ajax/resend-otp') ?>;
      const btn      = document.getElementById('resend-btn');
      const timer    = document.getElementById('resend-timer');
      const feedback = document.getElementById('resend-feedback');
      let   tickId;

      function tick() {
        const remaining = COOLDOWN - (Math.floor(Date.now() / 1000) - sentAt);
        if (remaining > 0) {
          btn.disabled      = true;
          timer.textContent = '(' + remaining + 's)';
          tickId = setTimeout(tick, 1000);
        } else {
          btn.disabled      = false;
          timer.textContent = '';
        }
      }

      tick();

      btn.addEventListener('click', async () => {
        clearTimeout(tickId);
        btn.disabled      = true;
        timer.textContent = '';
        btn.textContent   = 'Sending…';
        const csrfInput = document.querySelector('#otp-form [name="csrf_token"]');
        try {
          const res  = await fetch(RESEND_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'csrf_token=' + encodeURIComponent(csrfInput ? csrfInput.value : ''),
          });
          const data = await res.json();
          if (data.csrfToken && csrfInput) csrfInput.value = data.csrfToken;
          if (data.status === 'success') {
            sentAt = data.sentAt;
            showFeedback(data.message, true);
          } else {
            if (data.remaining) sentAt = Math.floor(Date.now() / 1000) - (COOLDOWN - data.remaining);
            showFeedback(data.message || 'Could not resend. Try again.', false);
          }
        } catch (_) {
          showFeedback('Network error. Please try again.', false);
        }
        btn.textContent = 'Resend OTP ';
        btn.appendChild(timer);
        tick();
      });

      function showFeedback(msg, ok) {
        feedback.textContent = msg;
        feedback.style.color = ok ? '#059669' : '#dc2626';
        clearTimeout(feedback._hide);
        feedback._hide = setTimeout(() => { feedback.textContent = ''; }, 5000);
      }
    }());
  </script>
</body>
</html>
