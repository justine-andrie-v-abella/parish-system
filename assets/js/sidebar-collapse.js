// assets/js/sidebar-collapse.js
// Desktop-only sidebar collapse/expand toggle. The initial (pre-paint)
// state is applied by a small inline script in includes/sidebar.php —
// this file only handles the click-to-toggle + persisting the choice.
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('dashSidebar');
  var btn = document.getElementById('sidebarCollapseBtn');
  if (!sidebar || !btn) return;

  function setCollapsed(collapsed) {
    sidebar.classList.toggle('collapsed', collapsed);
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    btn.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
  }

  btn.addEventListener('click', function () {
    setCollapsed(!sidebar.classList.contains('collapsed'));
  });

  // Expandable sub-menus (e.g. My Intentions > Mass Intentions / Sacraments).
  // When the rail is collapsed to icons there's no room to show a sub-menu,
  // so the button just falls through to being a normal link to the parent
  // page instead of toggling.
  document.querySelectorAll('[data-submenu-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (sidebar.classList.contains('collapsed')) {
        window.location.href = btn.closest('.sidebar-link-group').querySelector('.sidebar-sublink').href.split('?')[0];
        return;
      }
      btn.closest('.sidebar-link-group').classList.toggle('expanded');
    });
  });
});
