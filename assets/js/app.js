/* ═══════════════════════════════════════════════════════════
   EduManage Pro — app.js
   ═══════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  /* ── Sidebar: persistent state ──────────────────────────── */
  const sidebar     = document.getElementById('sidebar');
  const mainWrapper = document.getElementById('mainWrapper');
  const toggleBtn   = document.getElementById('sidebarToggle');

  function applySidebarState(collapsed) {
    if (!sidebar) return;
    if (collapsed) {
      sidebar.classList.add('collapsed');
      document.documentElement.classList.add('sidebar-pre-collapsed');
    } else {
      sidebar.classList.remove('collapsed');
      document.documentElement.classList.remove('sidebar-pre-collapsed');
    }
  }

  // Read saved state (default = open)
  const savedState = localStorage.getItem('sidebar_collapsed') === '1';
  applySidebarState(savedState);

  // Toggle on button click — save new state
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isNowCollapsed = !sidebar.classList.contains('collapsed');
      applySidebarState(isNowCollapsed);
      localStorage.setItem('sidebar_collapsed', isNowCollapsed ? '1' : '0');
    });
  }

  /* ── Notification dropdown ──────────────────────────────── */
  const notifBell     = document.getElementById('notifBell');
  const notifDropdown = document.getElementById('notifDropdown');

  if (notifBell && notifDropdown) {
    notifBell.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = notifDropdown.style.display === 'block';
      notifDropdown.style.display = isOpen ? 'none' : 'block';
    });
  }

  // Close dropdown when clicking outside
  document.addEventListener('click', function () {
    if (notifDropdown) notifDropdown.style.display = 'none';
  });

  /* ── Auto-dismiss alerts ────────────────────────────────── */
  setTimeout(function () {
    document.querySelectorAll('.alert').forEach(function (el) {
      el.style.transition = 'opacity .5s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 500);
    });
  }, 5000);

  /* ── Confirm delete ─────────────────────────────────────── */
  document.querySelectorAll('.confirm-delete').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (!confirm('Are you sure you want to delete this record?')) {
        e.preventDefault();
      }
    });
  });

})();
