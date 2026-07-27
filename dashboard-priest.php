<?php
require_once 'includes/config.php';
require_role(['priest']);
$page_title = 'Priest Dashboard — ' . $parish['name'];
require_once 'includes/dashboard-header.php';

$features = [
    ['icon' => '<path d="M8 7V3M16 7V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>', 'title' => 'Appointments Overview', 'desc' => 'All requests across every sacrament, in one view.'],
    ['icon' => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>', 'title' => 'Completed Services', 'desc' => 'Track sacraments delivered this month.'],
    ['icon' => '<path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>', 'title' => 'Pending Requests', 'desc' => 'Requests awaiting Secretary approval.'],
    ['icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>', 'title' => 'Total Revenue', 'desc' => 'Parish-wide income across all fees.'],
    ['icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>', 'title' => 'Secretary Activities', 'desc' => 'Approvals, reschedules, and document requests.'],
    ['icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>', 'title' => 'Treasurer Activities', 'desc' => 'Payment verifications and receipts issued.'],
    ['icon' => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>', 'title' => 'Charts &amp; Trends', 'desc' => 'Appointments by month, revenue by service, most-requested sacrament.'],
    ['icon' => '<path d="M3 6h18M3 12h18M3 18h18"/>', 'title' => 'Recent Logs', 'desc' => 'A timeline of logins, approvals, and schedule changes.'],
    ['icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>', 'title' => 'User Management', 'desc' => 'Add, deactivate, or reassign roles for staff and parishioners.'],
];
?>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Administrator</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>You're signed in as Priest / Administrator. This is a temporary landing page — the full admin panel with live statistics, calendars, and logs is built next.</p>
</div>

<div class="dash-section-label">
  <h2>What's coming to this dashboard</h2>
  <span class="dash-badge-temp">Temporary Preview</span>
</div>

<div class="dash-grid">
  <?php foreach ($features as $f): ?>
    <div class="dash-card">
      <div class="dash-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $f['icon']; ?></svg></div>
      <h3><?php echo $f['title']; ?></h3>
      <p><?php echo $f['desc']; ?></p>
      <span class="soon-tag">● Coming soon</span>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>