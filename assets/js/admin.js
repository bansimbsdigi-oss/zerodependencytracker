/* assets/js/admin.js - Admin Portal JS */

document.addEventListener('DOMContentLoaded', () => {
  // Confirm deletions
  const deleteBtns = document.querySelectorAll('.btn-delete');
  deleteBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        e.preventDefault();
      }
    });
  });
});
