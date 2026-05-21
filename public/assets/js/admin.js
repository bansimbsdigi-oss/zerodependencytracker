/* assets/js/admin.js - Admin Portal JS */

document.addEventListener('DOMContentLoaded', () => {

  // ── Confirm deletions ──────────────────────────────────────────
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', (e) => {
      if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        e.preventDefault();
      }
    });
  });

  // ── Mobile sidebar toggle ──────────────────────────────────────
  const toggle  = document.getElementById('menuToggle');
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar.classList.add('is-open');
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('is-open');
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  if (toggle) toggle.addEventListener('click', openSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  // Close sidebar when a nav link is clicked (mobile)
  if (sidebar) {
    sidebar.querySelectorAll('.admin-nav-item').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 900) closeSidebar();
      });
    });
  }

  // Close on resize back to desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) closeSidebar();
  });

  // ── Wrap all .table elements in responsive scroll div ─────────
  document.querySelectorAll('.table').forEach(table => {
    if (!table.closest('.table-responsive')) {
      const wrapper = document.createElement('div');
      wrapper.className = 'table-responsive';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });

});
