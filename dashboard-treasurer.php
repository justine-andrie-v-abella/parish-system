<?php
require_once 'includes/config.php';
require_role(['treasurer']);
$page_title = 'Treasurer Dashboard — ' . $parish['name'];
require_once 'includes/dashboard-header.php';

$features = [
    ['icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>', 'title' => "Today's Payments", 'desc' => 'Total collected across cash and GCash today.'],
    ['icon' => '<path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>', 'title' => 'Pending Payments', 'desc' => 'Payments awaiting verification.'],
    ['icon' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>', 'title' => 'Cash Payments', 'desc' => 'Over-the-counter payments logged by the office.'],
    ['icon' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/>', 'title' => 'GCash Payments', 'desc' => 'Digital payments with reference numbers.'],
    ['icon' => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>', 'title' => 'Revenue Summary', 'desc' => 'Daily, monthly, and per-service income charts.'],
    ['icon' => '<path d="M3 6h18M3 12h18M3 18h18"/>', 'title' => 'Payment Verification', 'desc' => 'Verify, generate receipts, or reject a submitted payment.'],
];
?>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Payments &amp; Fees</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>You're signed in as Treasurer. This is a temporary landing page — the full payment verification workspace and revenue charts are built next.</p>
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