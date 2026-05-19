/* assets/js/main.js - Client Portal JS */

document.addEventListener('DOMContentLoaded', () => {
  // Setup rating button selection logic
  const ratingGroups = document.querySelectorAll('.rating-grid');
  
  ratingGroups.forEach(group => {
    const btns = group.querySelectorAll('.rating-btn');
    const hiddenRadios = group.querySelectorAll('input[type="radio"]');
    
    btns.forEach(btn => {
      btn.addEventListener('click', () => {
        // Deselect all
        btns.forEach(b => b.classList.remove('selected'));
        // Select clicked
        btn.classList.add('selected');
        
        // Find corresponding radio and check it
        const val = btn.dataset.value;
        const radio = group.querySelector(`input[type="radio"][value="${val}"]`);
        if (radio) radio.checked = true;
      });
    });
  });
  
  // Nav avatar dropdown
  const navAvatarBtn = document.getElementById('navAvatarBtn');
  const navDropdown  = document.getElementById('navDropdown');
  if (navAvatarBtn && navDropdown) {
    navAvatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = navDropdown.classList.toggle('is-open');
      navAvatarBtn.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', () => {
      navDropdown.classList.remove('is-open');
      navAvatarBtn.setAttribute('aria-expanded', 'false');
    });
    navDropdown.addEventListener('click', (e) => e.stopPropagation());
  }

  // Setup tutorial completion
  const tutorialOverlay = document.getElementById('tutorial-overlay');
  if (tutorialOverlay) {
    const btnDone = document.getElementById('btn-tutorial-done');
    if (btnDone) {
      btnDone.addEventListener('click', () => {
        tutorialOverlay.style.display = 'none';
        
        // Fire AJAX to mark as done
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        fetch((window.APP_URL || '') + '/ajax/set_tutorial_done.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'action=done&csrf_token=' + encodeURIComponent(csrfToken)
        }).catch(() => {
          // Silent failure — tutorial UI is already hidden; will retry on next login
        });
      });
    }
  }
});
