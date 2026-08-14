<?php
/**
 * includes/dashboard-header.php
 * Expects $page_title to be set, and require_role() already called
 * by the including page before this file loads.
 */
$initials = '';
foreach (explode(' ', $_SESSION['full_name'] ?? '') as $part) {
    if ($part !== '') $initials .= strtoupper($part[0]);
}
$initials = substr($initials, 0, 2);

// Roles that get the left sidebar. Extend this list as each role's
// section (Priest, Secretary) gets its own pages built out.
$sidebarRoles = ['parishioner', 'treasurer', 'secretary', 'priest'];
$hasSidebar = in_array($_SESSION['role'] ?? '', $sidebarRoles, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title ?? $parish['name']); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Jost:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/dashboard.css?v=9">
<link rel="stylesheet" href="assets/css/dashboard-sidebar.css?v=9">

<style>
.dp-tabs{ position:relative; display:flex; background:var(--cream-deep,#f4ede0); border-radius:10px; padding:3px; margin-bottom:12px; }
.dp-tab{ flex:1; position:relative; z-index:2; background:none; border:none; padding:8px 4px; font-family:var(--font-mono); font-size:10.5px; letter-spacing:.5px; text-transform:uppercase; color:var(--ink-soft,#7a7264); cursor:pointer; border-radius:8px; transition:color .2s; }
.dp-tab.active{ color:var(--navy,#16223F); font-weight:600; }
.dp-tab-indicator{ position:absolute; top:3px; left:3px; width:calc(50% - 3px); height:calc(100% - 6px); background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); transition:transform .25s ease; z-index:1; }
.dp-tab-panels{ position:relative; }
.dp-tab-panel{ display:none; }
.dp-tab-panel.active{ display:block; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.dp-tabs').forEach(function (tabs) {
    var indicator = tabs.querySelector('.dp-tab-indicator');
    var buttons = tabs.querySelectorAll('.dp-tab');
    var panelsWrap = tabs.nextElementSibling;

    buttons.forEach(function (btn, i) {
      btn.addEventListener('click', function () {
        buttons.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        indicator.style.transform = 'translateX(' + (i * 100) + '%)';

        panelsWrap.querySelectorAll('.dp-tab-panel').forEach(function (p) { p.classList.remove('active'); });
        document.getElementById(btn.dataset.dpTab).classList.add('active');
      });
    });
  });
});
</script>

</head>
<body class="dash-body">

<div class="dash-topbar">
  <div class="dash-topbar-inner">
    <div class="dash-topbar-left">
      <?php if ($hasSidebar): ?>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu" aria-expanded="false" aria-controls="dashSidebar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
      <?php endif; ?>
      <div class="dash-brand">
        <svg width="32" height="32" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="23" fill="#16223F"/><circle cx="24" cy="24" r="23" stroke="#C6A15B" stroke-width="1"/><path d="M24 10V38M14 18H34M24 10C20 14 20 18 24 21C28 18 28 14 24 10Z" stroke="#E7C883" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span><strong><?php echo htmlspecialchars($parish['name']); ?></strong><span>Management System</span></span>
      </div>
    </div>
    <div class="dash-user">
      <?php if (isset($calendarPanelHtml) || isset($notifPanelHtml)): ?>
        <div class="header-tools">
          <?php if (isset($calendarPanelHtml)): ?>
            <div class="dropdown-wrap">
              <button type="button" class="icon-btn" data-dropdown-toggle="cal" aria-haspopup="true" aria-expanded="false" aria-label="Open calendar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
              </button>
              <div class="dropdown-panel dp-calendar" data-dropdown="cal">
                <?php echo $calendarPanelHtml; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (isset($notifPanelHtml)): ?>
            <div class="dropdown-wrap">
              <button type="button" class="icon-btn" data-dropdown-toggle="notif" aria-haspopup="true" aria-expanded="false" aria-label="Open notifications">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                <?php if (!empty($unreadCount)): ?><span class="icon-badge"><?php echo $unreadCount; ?></span><?php endif; ?>
              </button>
              <div class="dropdown-panel dp-notif" data-dropdown="notif">
                <?php if (isset($activityPanelHtml)): ?>
                  <div class="dp-tabs">
                    <button type="button" class="dp-tab active" data-dp-tab="dpTabNotifs">Notifications</button>
                    <button type="button" class="dp-tab" data-dp-tab="dpTabActivity">Recent Activity</button>
                    <span class="dp-tab-indicator"></span>
                  </div>
                  <div class="dp-tab-panels">
                    <div class="dp-tab-panel active" id="dpTabNotifs"><?php echo $notifPanelHtml; ?></div>
                    <div class="dp-tab-panel" id="dpTabActivity"><?php echo $activityPanelHtml; ?></div>
                  </div>
                <?php else: ?>
                  <?php echo $notifPanelHtml; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="dash-shell">
  <?php if ($hasSidebar): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
  <?php endif; ?>
  <div class="dash-main">