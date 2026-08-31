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
});
