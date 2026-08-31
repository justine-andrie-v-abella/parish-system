<?php
require_once 'includes/config.php';
require_role(['treasurer']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';
require_once 'includes/payments.php';

$tid = (int) $_SESSION['user_id'];
$feeMap = get_fee_map($services);
$serviceNames = array_column($services, 'name', 'key');
$today = date('Y-m-d');

// ---------------- Notifications (header dropdown) ----------------
$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$tid]);
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

// ---------------- Pull all verified (paid) payments once, aggregate in PHP ----------------
// (amounts aren't stored per-row — fees live in config.php — so raw rows get summed here)
$paidStmt = $pdo->query(
    "SELECT id, service_key, payment_method, verified_at FROM appointments WHERE payment_status = 'paid' AND verified_at IS NOT NULL"
);
$paidRows = $paidStmt->fetchAll();

$todayTotal = 0; $todayCash = 0; $todayGcash = 0; $allTimeTotal = 0;
$dailyRevenue = [];   // 'Y-m-d' => amount, last 7 days
$monthlyRevenue = []; // 'Y-m'   => amount, last 6 months
$serviceIncome = [];  // service_key => amount, all-time

$sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));
$sixMonthsAgo = date('Y-m', strtotime('-5 months'));

foreach ($paidRows as $row) {
    $amount = payment_amount($row['service_key'], $feeMap);
    $vDate = date('Y-m-d', strtotime($row['verified_at']));
    $vMonth = date('Y-m', strtotime($row['verified_at']));

    $allTimeTotal += $amount;
    $serviceIncome[$row['service_key']] = ($serviceIncome[$row['service_key']] ?? 0) + $amount;

    if ($vDate === $today) {
        $todayTotal += $amount;
        if ($row['payment_method'] === 'cash') $todayCash += $amount;
        if ($row['payment_method'] === 'gcash') $todayGcash += $amount;
    }
    if ($vDate >= $sevenDaysAgo) {
        $dailyRevenue[$vDate] = ($dailyRevenue[$vDate] ?? 0) + $amount;
    }
    if ($vMonth >= $sixMonthsAgo) {
        $monthlyRevenue[$vMonth] = ($monthlyRevenue[$vMonth] ?? 0) + $amount;
    }
}

// Fill in zero days/months so the chart never has gaps
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    if (!isset($dailyRevenue[$d])) $dailyRevenue[$d] = 0;
}
ksort($dailyRevenue);
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    if (!isset($monthlyRevenue[$m])) $monthlyRevenue[$m] = 0;
}
ksort($monthlyRevenue);
arsort($serviceIncome);

$pendingCountStmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE payment_status = 'unpaid'");
$pendingCount = (int) $pendingCountStmt->fetchColumn();

// ---------------- Verification queue preview (5 most recent pending) ----------------
$queueStmt = $pdo->query(
    "SELECT a.*, u.full_name FROM appointments a
     JOIN users u ON u.id = a.user_id
     WHERE a.payment_status = 'unpaid'
     ORDER BY a.created_at DESC LIMIT 5"
);
$queue = $queueStmt->fetchAll();

$maxDaily = max(1, max($dailyRevenue));
$maxMonthly = max(1, max($monthlyRevenue));
$maxService = max(1, empty($serviceIncome) ? 1 : max($serviceIncome));

$page_title = 'Treasurer Dashboard — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Payments &amp; Fees</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>Here's today's collections, what's still waiting on verification, and how revenue has trended.</p>
</div>

<div class="quick-links-row">
  <a href="payments.php" class="quick-link-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Payment Verification</a>
</div>

<div class="summary-grid">
  <div class="summary-card">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
    <span class="num">₱<?php echo number_format($todayTotal); ?></span><span class="lbl">Today's Payments</span>
  </div>
  <div class="summary-card accent-pending">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span>
    <span class="num"><?php echo $pendingCount; ?></span><span class="lbl">Pending Payments</span>
  </div>
  <div class="summary-card accent-completed">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg></span>
    <span class="num">₱<?php echo number_format($todayCash); ?></span><span class="lbl">Cash Today</span>
  </div>
  <div class="summary-card accent-scheduled">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg></span>
    <span class="num">₱<?php echo number_format($todayGcash); ?></span><span class="lbl">GCash Today</span>
  </div>
  <div class="summary-card">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
    <span class="num">₱<?php echo number_format($allTimeTotal); ?></span><span class="lbl">Revenue Summary</span>
  </div>
</div>

<div class="panel" style="margin-bottom:32px;">
  <div class="dash-section-label" style="margin-bottom:14px;">
    <h2 style="font-size:18px;">Payment Verification Queue</h2>
    <a href="payments.php" class="btn btn-outline btn-sm">View All →</a>
  </div>
  <?php if (empty($queue)): ?>
    <p class="upcoming-empty">Nothing waiting on verification right now.</p>
  <?php else: ?>
    <div class="mini-table-wrap">
      <table>
        <thead><tr><th>Parishioner</th><th>Service</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
        <tbody>
          <?php foreach ($queue as $q): ?>
            <tr>
              <td data-label="Parishioner"><?php echo htmlspecialchars($q['full_name']); ?></td>
              <td data-label="Service"><?php echo htmlspecialchars($serviceNames[$q['service_key']] ?? ucfirst($q['service_key'])); ?></td>
              <td data-label="Amount">₱<?php echo number_format(payment_amount($q['service_key'], $feeMap)); ?></td>
              <td data-label="Method"><?php if ($q['payment_method']): ?><span class="pm-chip <?php echo $q['payment_method']; ?>"><?php echo strtoupper($q['payment_method']); ?></span><?php else: ?>—<?php endif; ?></td>
              <td data-label="Reference"><?php echo $q['reference_number'] ? htmlspecialchars($q['reference_number']) : '—'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="dash-section-label"><h2>Revenue Charts</h2></div>
<div class="chart-grid">
  <div class="panel">
    <h3>Daily Revenue (Last 7 Days)</h3>
    <div class="chart-bars">
      <?php foreach ($dailyRevenue as $d => $amt): ?>
        <div class="chart-bar-col">
          <span class="chart-bar-value"><?php echo $amt > 0 ? number_format($amt) : ''; ?></span>
          <div class="chart-bar" style="height:<?php echo max(2, round($amt / $maxDaily * 100)); ?>%"></div>
          <span class="chart-bar-label"><?php echo date('D', strtotime($d)); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <h3>Monthly Revenue (Last 6 Months)</h3>
    <div class="chart-bars">
      <?php foreach ($monthlyRevenue as $m => $amt): ?>
        <div class="chart-bar-col">
          <span class="chart-bar-value"><?php echo $amt > 0 ? number_format($amt) : ''; ?></span>
          <div class="chart-bar" style="height:<?php echo max(2, round($amt / $maxMonthly * 100)); ?>%"></div>
          <span class="chart-bar-label"><?php echo date('M', strtotime($m . '-01')); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="panel" style="margin-bottom:40px;">
  <h3>Service Income (All-Time)</h3>
  <?php if (empty($serviceIncome)): ?>
    <p class="upcoming-empty">No verified payments yet.</p>
  <?php else: ?>
    <?php foreach ($serviceIncome as $key => $amt): ?>
      <div class="svc-bar-row">
        <span class="svc-bar-label"><?php echo htmlspecialchars($serviceNames[$key] ?? ucfirst($key)); ?></span>
        <div class="svc-bar-track"><div class="svc-bar-fill" style="width:<?php echo max(2, round($amt / $maxService * 100)); ?>%"></div></div>
        <span class="svc-bar-amount">₱<?php echo number_format($amt); ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>