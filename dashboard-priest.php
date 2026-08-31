<?php
//dashboard-priest.php
require_once 'includes/config.php';
require_role(['priest']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';
require_once 'includes/payments.php';

$pid = (int) $_SESSION['user_id'];
$feeMap = get_fee_map($services);
$serviceNames = array_column($services, 'name', 'key');

// ---------------- Notifications (header dropdown) ----------------
$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$pid]);
$notifications = $notifStmt->fetchAll();
$unreadCount = count(array_filter($notifications, fn($n) => !is_true($n['is_read'])));

ob_start();
?>
<div class="tab-panel-wrap">
  <?php if (empty($notifications)): ?>
    <p class="upcoming-empty">You're all caught up.</p>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
      <div class="notif-item<?php echo is_true($n['is_read']) ? '' : ' unread'; ?>" data-notif-id="<?php echo $n['id']; ?>" data-appointment-id="<?php echo $n['appointment_id'] ?? ''; ?>" data-certificate-id="<?php echo $n['certificate_id'] ?? ''; ?>" data-notif-type="<?php echo htmlspecialchars($n['type'] ?? ''); ?>">
        <span class="notif-dot"></span>
        <div>
          <p><?php echo htmlspecialchars(preg_replace('/^DEMO:\s*/', '', $n['message'])); ?></p>
          <span class="time"><?php echo date('M j, g:i A', strtotime($n['created_at'])); ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php
$notifPanelHtml = ob_get_clean();

// ---------------- Calendar (header dropdown) ----------------
$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year'] : (int) date('Y');
$calendarPanelHtml = render_calendar_fragment($pdo, $month, $year);

// ---------------- Summary cards ----------------
$totalAppointments = (int) $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$completedServices = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn();
$pendingRequests    = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();

$paidRows = $pdo->query("SELECT service_key FROM appointments WHERE payment_status = 'paid'")->fetchAll();
$totalRevenue = 0;
$serviceRevenue = [];
$serviceCounts = [];
foreach ($paidRows as $row) {
    $totalRevenue += payment_amount($row['service_key'], $feeMap);
    $serviceRevenue[$row['service_key']] = ($serviceRevenue[$row['service_key']] ?? 0) + payment_amount($row['service_key'], $feeMap);
}
arsort($serviceRevenue);

// Most requested service (all non-cancelled/rejected appointments)
$allRows = $pdo->query("SELECT service_key, created_at FROM appointments WHERE status NOT IN ('cancelled','rejected')")->fetchAll();
foreach ($allRows as $row) {
    $serviceCounts[$row['service_key']] = ($serviceCounts[$row['service_key']] ?? 0) + 1;
}
arsort($serviceCounts);

// Appointments by month (last 6 months, all appointments regardless of status)
$monthlyCounts = [];
for ($i = 5; $i >= 0; $i--) {
    $monthlyCounts[date('Y-m', strtotime("-$i months"))] = 0;
}
foreach ($allRows as $row) {
    $m = date('Y-m', strtotime($row['created_at']));
    if (isset($monthlyCounts[$m])) $monthlyCounts[$m]++;
}

$maxMonthly = max(1, max($monthlyCounts));
$maxServiceCount = max(1, empty($serviceCounts) ? 1 : max($serviceCounts));
$maxServiceRevenue = max(1, empty($serviceRevenue) ? 1 : max($serviceRevenue));

// ---------------- Secretary / Treasurer activity feeds ----------------
$secretaryActivity = $pdo->query(
    "SELECT l.*, u.full_name FROM activity_logs l
     JOIN users u ON u.id = l.user_id
     WHERE u.role = 'secretary'
     ORDER BY l.created_at DESC LIMIT 6"
)->fetchAll();

$treasurerActivity = $pdo->query(
    "SELECT l.*, u.full_name FROM activity_logs l
     JOIN users u ON u.id = l.user_id
     WHERE u.role = 'treasurer'
     ORDER BY l.created_at DESC LIMIT 6"
)->fetchAll();

// ---------------- Recent requests preview ----------------
$recentRequests = $pdo->query(
    "SELECT a.*, u.full_name FROM appointments a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC LIMIT 6"
)->fetchAll();

// ---------------- Recent logs (full timeline) ----------------
$recentLogs = $pdo->query(
    "SELECT l.*, u.full_name, u.role FROM activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC LIMIT 15"
)->fetchAll();

$page_title = 'Priest Dashboard — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Administrator</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>An overview of the whole parish office — appointments, revenue, and what your staff have been doing.</p>
</div>

<div class="quick-links-row">
  <a href="queue.php" class="quick-link-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>Intentions</a>
  <a href="certificate-queue.php" class="quick-link-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>Certificate Requests</a>
  <a href="catalog.php" class="quick-link-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>Catalog</a>
</div>

<div class="summary-grid">
  <div class="summary-card">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></span>
    <span class="num"><?php echo $totalAppointments; ?></span><span class="lbl">Appointments Overview</span>
  </div>
  <div class="summary-card accent-completed">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
    <span class="num"><?php echo $completedServices; ?></span><span class="lbl">Completed Services</span>
  </div>
  <div class="summary-card accent-pending">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span>
    <span class="num"><?php echo $pendingRequests; ?></span><span class="lbl">Pending Requests</span>
  </div>
  <div class="summary-card">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
    <span class="num">₱<?php echo number_format($totalRevenue); ?></span><span class="lbl">Total Revenue</span>
  </div>
</div>

<div class="activity-cols">
  <div class="panel">
    <h3>Secretary Activities</h3>
    <?php if (empty($secretaryActivity)): ?>
      <p class="upcoming-empty">No secretary activity yet.</p>
    <?php else: foreach ($secretaryActivity as $a): ?>
      <div class="mini-log-item">
        <span class="who"><?php echo htmlspecialchars($a['full_name']); ?></span> — <?php echo htmlspecialchars($a['description']); ?>
        <span class="when"><?php echo date('M j, g:i A', strtotime($a['created_at'])); ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <div class="panel">
    <h3>Treasurer Activities</h3>
    <?php if (empty($treasurerActivity)): ?>
      <p class="upcoming-empty">No treasurer activity yet.</p>
    <?php else: foreach ($treasurerActivity as $a): ?>
      <div class="mini-log-item">
        <span class="who"><?php echo htmlspecialchars($a['full_name']); ?></span> — <?php echo htmlspecialchars($a['description']); ?>
        <span class="when"><?php echo date('M j, g:i A', strtotime($a['created_at'])); ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="dash-section-label"><h2>Charts &amp; Trends</h2></div>
<div class="chart-grid">
  <div class="panel">
    <h3>Appointments by Month</h3>
    <div class="chart-bars">
      <?php foreach ($monthlyCounts as $m => $c): ?>
        <div class="chart-bar-col">
          <span class="chart-bar-value"><?php echo $c > 0 ? $c : ''; ?></span>
          <div class="chart-bar" style="height:<?php echo max(2, round($c / $maxMonthly * 100)); ?>%"></div>
          <span class="chart-bar-label"><?php echo date('M', strtotime($m . '-01')); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <h3>Most Requested Service</h3>
    <?php if (empty($serviceCounts)): ?>
      <p class="upcoming-empty">No requests yet.</p>
    <?php else: foreach ($serviceCounts as $key => $count): ?>
      <div class="svc-bar-row">
        <span class="svc-bar-label"><?php echo htmlspecialchars($serviceNames[$key] ?? ucfirst($key)); ?></span>
        <div class="svc-bar-track"><div class="svc-bar-fill" style="width:<?php echo max(2, round($count / $maxServiceCount * 100)); ?>%"></div></div>
        <span class="svc-bar-amount"><?php echo $count; ?> reqs</span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="panel" style="margin-bottom:32px;">
  <h3>Revenue by Service</h3>
  <?php if (empty($serviceRevenue)): ?>
    <p class="upcoming-empty">No verified payments yet.</p>
  <?php else: foreach ($serviceRevenue as $key => $amt): ?>
    <div class="svc-bar-row">
      <span class="svc-bar-label"><?php echo htmlspecialchars($serviceNames[$key] ?? ucfirst($key)); ?></span>
      <div class="svc-bar-track"><div class="svc-bar-fill" style="width:<?php echo max(2, round($amt / $maxServiceRevenue * 100)); ?>%"></div></div>
      <span class="svc-bar-amount">₱<?php echo number_format($amt); ?></span>
    </div>
  <?php endforeach; endif; ?>
</div>

<div class="panel" style="margin-bottom:32px;">
  <h3>Recent Requests</h3>
  <?php if (empty($recentRequests)): ?>
    <p class="upcoming-empty">No requests yet.</p>
  <?php else: ?>
    <div class="mini-table-wrap">
      <table>
        <thead><tr><th>Parishioner</th><th>Service</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentRequests as $r): ?>
            <tr>
              <td data-label="Parishioner"><?php echo htmlspecialchars($r['full_name']); ?></td>
              <td data-label="Service"><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
              <td data-label="Date"><?php echo date('M j, Y', strtotime($r['appointment_date'])); ?></td>
              <td data-label="Status"><span class="status-pill <?php echo $r['status']; ?>"><?php echo $r['status'] === 'no_show' ? 'No-show' : ucfirst($r['status']); ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel" style="margin-bottom:40px;">
  <h3>Recent Logs</h3>
  <?php if (empty($recentLogs)): ?>
    <p class="upcoming-empty">No activity logged yet.</p>
  <?php else: ?>
    <div class="timeline">
      <?php foreach ($recentLogs as $log): ?>
        <div class="timeline-item">
          <p class="tl-desc"><?php echo htmlspecialchars($log['description']); ?></p>
          <span class="tl-meta">
            <?php if ($log['full_name']): ?><span class="tl-role"><?php echo htmlspecialchars(ucfirst($log['role'])); ?></span> · <?php endif; ?>
            <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>