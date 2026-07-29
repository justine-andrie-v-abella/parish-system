<?php
/**
 * includes/sidebar.php
 * Left-hand nav for the dashboard shell. Included by dashboard-header.php.
 * Highlights the active item based on the current script filename.
 * On small screens this renders as an off-canvas drawer (see
 * assets/css/dashboard-sidebar.css and assets/js/sidebar-toggle.js).
 */
$current = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';

$sidebarLinksByRole = [
    'parishioner' => [
        ['href' => 'dashboard-parishioner.php', 'label' => 'Dashboard', 'match' => ['dashboard-parishioner.php'],
            'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        ['href' => 'intentions.php', 'label' => 'My Intentions', 'match' => ['intentions.php'],
            'icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
        ['href' => 'requests.php', 'label' => 'View Requests', 'match' => ['requests.php'],
            'icon' => '<path d="M3 6h18M3 12h18M3 18h18"/>'],
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
    ],
    'priest' => [
        ['href' => 'dashboard-priest.php', 'label' => 'Dashboard', 'match' => ['dashboard-priest.php'],
            'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
    ],
];

$sidebarLinks = $sidebarLinksByRole[$role] ?? [];
?>
<aside class="dash-sidebar" id="dashSidebar">
  <div class="sidebar-mobile-head">
    <span>Menu</span>
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu">&times;</button>
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
</aside>