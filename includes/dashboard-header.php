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
$isParishioner = ($_SESSION['role'] ?? '') === 'parishioner';
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
<link rel="stylesheet" href="assets/css/dashboard.css?v=2">
<link rel="stylesheet" href="assets/css/dashboard-sidebar.css?v=2">
</head>
<body class="dash-body">

<div class="dash-topbar">
  <div class="dash-topbar-inner">
    <div class="dash-topbar-left">
      <?php if ($isParishioner): ?>
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
                <?php echo $notifPanelHtml; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <span class="dash-role-badge <?php echo htmlspecialchars($_SESSION['role']); ?>"><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></span>
      <span class="dash-username"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      <div class="dash-avatar"><?php echo htmlspecialchars($initials ?: '·'); ?></div>
      <a href="logout.php" class="btn btn-outline btn-sm">Log out</a>
    </div>
  </div>
</div>

<div class="dash-shell">
  <?php if ($isParishioner): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
  <?php endif; ?>
  <div class="dash-main">