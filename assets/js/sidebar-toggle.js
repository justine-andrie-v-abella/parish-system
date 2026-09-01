
//assets\js\sidebar-toggle.js
document.addEventListener('DOMContentLoaded', function () {
  var toggle  = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('dashSidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var closeBtn = document.getElementById('sidebarClose');

  if (!toggle || !sidebar || !overlay) return;

  function openSidebar() {
    // The desktop "collapsed to icons" state is unrelated to the mobile
    // drawer, but if it was set before the window got resized down to
    // mobile width, its higher-specificity CSS could otherwise still
    // apply here — drop it so the drawer always opens at full width.
    sidebar.classList.remove('collapsed');
    sidebar.classList.add('open');
    overlay.classList.add('open');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  toggle.addEventListener('click', function () {
    if (sidebar.classList.contains('open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  overlay.addEventListener('click', closeSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

  // Tapping a nav link closes the drawer behind it
  sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });

  // If the window is resized back up to desktop width, make sure the
  // drawer state doesn't linger (e.g. rotating a tablet). Going the other
  // way, drop any leftover "collapsed to icons" state too — that's a
  // desktop-only affordance and shouldn't affect the mobile drawer.
  window.addEventListener('resize', function () {
    if (window.innerWidth > 860) {
      closeSidebar();
    } else {
      sidebar.classList.remove('collapsed');
    }
  });

  // Escape key closes it too
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });
});