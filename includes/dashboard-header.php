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

// Every page already sets $page_title as "Something — {$parish['name']}"
// for the <title> tag — reuse that same string for the topbar's visible
// page heading instead of introducing a second thing every page has to set.
$topbarTitle = '';
if (!empty($page_title)) {
    $topbarTitle = trim(explode('—', $page_title)[0]);
}

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
<link rel="stylesheet" href="assets/css/dashboard.css?v=10">
<link rel="stylesheet" href="assets/css/dashboard-sidebar.css?v=10">
<link rel="stylesheet" href="assets/css/dashboard-components.css?v=1">

<style>
.dp-tabs{ position:relative; display:flex; background:var(--cream-deep,#f4ede0); border-radius:10px; padding:3px; margin-bottom:12px; }
.dp-tab{ flex:1; position:relative; z-index:2; background:none; border:none; padding:8px 4px; font-family:var(--font-mono); font-size:10.5px; letter-spacing:.5px; text-transform:uppercase; color:var(--ink-soft,#7a7264); cursor:pointer; border-radius:8px; transition:color .2s; }
.dp-tab.active{ color:var(--navy,#16223F); font-weight:600; }
.dp-tab-indicator{ position:absolute; top:3px; left:3px; width:calc(50% - 3px); height:calc(100% - 6px); background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); transition:transform .25s ease; z-index:1; }
.dp-tab-panels{ position:relative; }
.dp-tab-panel{ display:none; }
.dp-tab-panel.active{ display:block; }
.reject-modal-overlay{
  position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000;
  display:flex; align-items:center; justify-content:center; padding:20px;
  opacity:0; pointer-events:none; transition:opacity .2s;
}
.reject-modal-overlay.open{ opacity:1; pointer-events:auto; }
.reject-modal-box{ background:var(--cream,#f4ede0); border-radius:20px; border:1px solid var(--line,#ddd); width:380px; max-width:100%; padding:24px; }
.reject-modal-box h3{ font-size:18px; margin-bottom:12px; }
.reject-error{ font-size:12px; color:#A2432F; margin-top:6px; display:none; }
.reject-error.show{ display:block; }

.qmodal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
.qmodal-slot-grid{ display:grid; grid-template-columns: repeat(4,1fr); gap:8px; margin-top:10px; }
.qmodal-slot-btn{ border:1px solid var(--line,#ddd); background: var(--white,#fff); border-radius:10px; padding:9px 4px; font-size:12px; color: var(--navy,#16223F); text-align:center; }
.qmodal-slot-btn:hover:not(:disabled){ border-color: var(--gold,#C6A15B); background: var(--cream-deep,#f4ede0); }
.qmodal-slot-btn.selected{ background: var(--navy,#16223F); color:#fff; border-color: var(--navy,#16223F); }
.qmodal-slot-btn:disabled{ opacity:0.4; text-decoration: line-through; cursor:not-allowed; }
.qmodal-slot-empty{ font-size:12.5px; color:var(--ink-soft,#7a7264); grid-column: 1/-1; }

.cash-panel{ display:none; }
.cash-panel.show{ display:block; }
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
      <?php if ($topbarTitle !== ''): ?>
        <div class="dash-breadcrumb">
          <span class="crumb-sep">/</span>
          <span class="crumb-current"><?php echo htmlspecialchars($topbarTitle); ?></span>
        </div>
      <?php endif; ?>
    </div>
    <div class="dash-user">
      <?php if (isset($calendarPanelHtml) || isset($notifPanelHtml) || $hasSidebar): ?>
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

          <?php if ($hasSidebar): ?>
            <div class="dropdown-wrap">
              <button type="button" class="icon-btn profile-btn" data-dropdown-toggle="profile" aria-haspopup="true" aria-expanded="false" aria-label="Open profile menu">
                <span class="profile-avatar"><?php echo htmlspecialchars($initials); ?></span>
              </button>
              <div class="dropdown-panel dp-profile" data-dropdown="profile">
                <div class="dp-profile-head">
                  <span class="profile-avatar profile-avatar-lg"><?php echo htmlspecialchars($initials); ?></span>
                  <div>
                    <span class="dp-profile-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
                    <span class="dash-role-badge <?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? '')); ?></span>
                  </div>
                </div>
                <a href="logout.php" class="dp-profile-logout">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                  Log out
                </a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="reject-modal-overlay" id="rescheduleActionModal">
  <div class="reject-modal-box" style="width:420px;">
    <h3 id="raService">Reschedule</h3>
    <p id="raCurrent" style="font-size:13px; color:var(--ink-soft); margin-bottom:6px;"></p>
    <p id="raProposed" style="font-size:13.5px; font-weight:600; color:var(--navy); margin-bottom:14px;"></p>

    <p id="raWaiting" class="upcoming-empty" style="display:none;"></p>

    <div id="raActions" style="display:none;">
      <div class="qmodal-actions" style="justify-content:flex-start; margin-top:0;">
        <button type="button" class="btn btn-gold btn-sm" id="raAccept">Confirm This Date</button>
        <button type="button" class="btn btn-outline btn-sm" id="raCounterToggle">Suggest Different Date</button>
      </div>

      <div id="raCounterPanel" class="cash-panel" style="margin-top:14px;">
        <label style="display:block; font-family:var(--font-mono); font-size:10.5px; letter-spacing:1.5px; text-transform:uppercase; color:var(--ink-soft); margin-bottom:8px;">New Date</label>
        <input type="date" id="raCounterDate" min="<?php echo date('Y-m-d'); ?>" style="width:100%; border:1px solid var(--line); border-radius:12px; padding:10px 13px;">
        <div class="qmodal-slot-grid" id="raCounterSlots"><div class="qmodal-slot-empty">Choose a date to see open times.</div></div>
        <div class="qmodal-actions">
          <button type="button" class="btn btn-gold btn-sm" id="raCounterSubmit">Send Suggested Date</button>
        </div>
      </div>
    </div>

    <p class="reject-error" id="raError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="raClose">Close</button>
    </div>
  </div>
</div>
<script>var PMS_ROLE = '<?php echo $_SESSION['role'] ?? ''; ?>';</script>
<script src="assets/js/notifications.js"></script>

<div class="dash-shell">
  <?php if ($hasSidebar): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
  <?php endif; ?>
  <div class="dash-main">