<?php
require_once 'includes/config.php';
require_role(['parishioner', 'priest', 'secretary', 'treasurer']);
$feature = $_GET['feature'] ?? 'This feature';
$back    = $_GET['back'] ?? dashboard_for($_SESSION['role']);
$page_title = htmlspecialchars($feature) . ' — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="panel" style="max-width:560px; margin:60px auto; text-align:center;">
  <div class="dash-card-icon" style="margin:0 auto 16px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg>
  </div>
  <h3 style="font-size:22px; font-family:var(--font-display);"><?php echo htmlspecialchars($feature); ?></h3>
  <p style="color:var(--ink-soft); font-size:14px;">This part of the system hasn't been built yet — it's next in line. Check back soon.</p>
  <a href="<?php echo htmlspecialchars($back); ?>" class="btn btn-outline btn-sm" style="margin-top:12px;">← Back to dashboard</a>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>