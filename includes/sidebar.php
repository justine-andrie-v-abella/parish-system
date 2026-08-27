<?php
//includes\sidebar.php
$current = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$fullName = $_SESSION['full_name'] ?? '';
$initials = '';
if ($fullName) {
    $parts = explode(' ', trim($fullName));
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}

$sidebarLinksByRole = [
    'parishioner' => [
        ['href' => 'dashboard-parishioner.php', 'label' => 'Dashboard', 'match' => ['dashboard-parishioner.php'],
            'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        ['href' => 'intentions.php', 'label' => 'My Intentions', 'match' => ['intentions.php'],
            'icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
        ['href' => 'requests.php', 'label' => 'View Requests', 'match' => ['requests.php'],
            'icon' => '<path d="M3 6h18M3 12h18M3 18h18"/>'],
        ['href' => 'certificates.php', 'label' => 'Certificates', 'match' => ['certificates.php'],
            'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>'],
        ['href' => 'coming-soon.php?feature=Chat+with+Secretary', 'label' => 'Chat with Secretary', 'match' => [],
            'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>'],
    ],
    'treasurer' => [
        ['href' => 'dashboard-treasurer.php', 'label' => 'Dashboard', 'match' => ['dashboard-treasurer.php'],
            'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        ['href' => 'payments.php', 'label' => 'Payment Verification', 'match' => ['payments.php'],
            'icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
    ],
    'secretary' => [
        ['href' => 'dashboard-secretary.php', 'label' => 'Dashboard', 'match' => ['dashboard-secretary.php'],
            'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        ['href' => 'queue.php', 'label' => 'Appointment Queue', 'match' => ['queue.php'],
            'icon' => '<path d="M3 6h18M3 12h18M3 18h18"/>'],
        ['href' => 'certificate-queue.php', 'label' => 'Certificate Requests', 'match' => ['certificate-queue.php'],
            'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
        ['href' => 'catalog.php', 'label' => 'Catalog', 'match' => ['catalog.php'],
            'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>'],
    ],
    'priest' => [
        ['href' => 'dashboard-priest.php', 'label' => 'Dashboard', 'match' => ['dashboard-priest.php'],
            'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        ['href' => 'queue.php', 'label' => 'Intentions', 'match' => ['queue.php'],
            'icon' => '<path d="M3 6h18M3 12h18M3 18h18"/>'],
        ['href' => 'certificate-queue.php', 'label' => 'Certificate Requests', 'match' => ['certificate-queue.php'],
            'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
        ['href' => 'catalog.php', 'label' => 'Catalog', 'match' => ['catalog.php'],
            'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>'],
    ],
];

$sidebarLinks = $sidebarLinksByRole[$role] ?? [];
?>
<aside class="dash-sidebar" id="dashSidebar">
  <div class="sidebar-mobile-head">
    <span>Menu</span>
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu">&times;</button>
  </div>

  <div class="sidebar-user">
    <div class="dash-avatar"><?php echo htmlspecialchars($initials); ?></div>
    <div class="sidebar-user-info">
      <span class="dash-username"><?php echo htmlspecialchars($fullName); ?></span>
      <span class="dash-role-badge <?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars(ucfirst($role)); ?></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($sidebarLinks as $link):
        $isActive = in_array($current, $link['match'], true);
    ?>
      <a href="<?php echo htmlspecialchars($link['href']); ?>" class="sidebar-link<?php echo $isActive ? ' active' : ''; ?>">
        <span class="sidebar-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $link['icon']; ?></svg>
        </span>
        <span><?php echo htmlspecialchars($link['label']); ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="btn btn-outline btn-sm sidebar-logout">Log out</a>
  </div>
</aside>