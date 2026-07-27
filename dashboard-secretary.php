<?php
require_once 'includes/config.php';
require_role(['secretary']);
$page_title = 'Secretary Dashboard — ' . $parish['name'];
require_once 'includes/dashboard-header.php';

$features = [
    ['icon' => '<path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>', 'title' => 'Pending Requests', 'desc' => 'New appointment requests awaiting review.'],
    ['icon' => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>', 'title' => 'Approved Today', 'desc' => 'Requests you\'ve confirmed so far today.'],
    ['icon' => '<path d="M8 7V3M16 7V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>', 'title' => "Today's Schedule", 'desc' => 'Every Mass, baptism, or wedding happening today.'],
    ['icon' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>', 'title' => 'Notifications', 'desc' => 'Alerts for new submissions and payment updates.'],
    ['icon' => '<path d="M8 7V3M16 7V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>', 'title' => 'Priest Calendar', 'desc' => 'Monthly view of availability and bookings.'],
    ['icon' => '<path d="M3 6h18M3 12h18M3 18h18"/>', 'title' => 'Appointment Queue', 'desc' => 'Approve, reject, reschedule, or request more documents.'],
];
?>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Scheduling &amp; Records</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>You're signed in as Secretary. This is a temporary landing page — the full workspace for reviewing requests and managing the parish calendar is built next.</p>
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