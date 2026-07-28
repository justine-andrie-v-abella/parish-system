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
$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

ob_start();
?>
<div class="tab-panel-wrap">
  <?php if (empty($notifications)): ?>
    <p class="upcoming-empty">You're all caught up.</p>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
      <div class="notif-item<?php echo $n['is_read'] ? '' : ' unread'; ?>">
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

<style>
/* Scoped to the treasurer dashboard — safe to move into dashboard.css later. */
.chart-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:32px; }
@media (max-width:900px){ .chart-grid{ grid-template-columns:1fr; } }
.chart-bars{ display:flex; align-items:flex-end; gap:8px; height:140px; margin-top:8px; }
.chart-bar-col{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; gap:6px; }
.chart-bar{ width:100%; max-width:34px; background: linear-gradient(180deg, var(--gold-bright), var(--gold)); border-radius:6px 6px 2px 2px; min-height:2px; }
.chart-bar-label{ font-family: var(--font-mono); font-size:9.5px; color: var(--ink-soft); text-align:center; }
.chart-bar-value{ font-family: var(--font-mono); font-size:9px; color: var(--navy); }

.svc-bar-row{ display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.svc-bar-label{ width:120px; flex-shrink:0; font-size:12.5px; color: var(--ink-soft); }
.svc-bar-track{ flex:1; background: var(--cream-deep); border-radius:999px; height:10px; overflow:hidden; }
.svc-bar-fill{ height:100%; background: linear-gradient(90deg, var(--gold), var(--gold-bright)); border-radius:999px; }
.svc-bar-amount{ width:70px; text-align:right; font-family: var(--font-mono); font-size:11.5px; color:var(--navy); }

.queue-table{ width:100%; border-collapse:collapse; }
.queue-table th{ text-align:left; font-family: var(--font-mono); font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--ink-soft); padding:10px 12px; border-bottom:1px solid var(--line); }
.queue-table td{ padding:12px; border-bottom:1px dashed var(--line); font-size:13px; }
.queue-table tr:last-child td{ border-bottom:none; }
.pm-chip{ font-family: var(--font-mono); font-size:10px; letter-spacing:0.5px; text-transform:uppercase; padding:3px 9px; border-radius:999px; }
.pm-chip.cash{ background:#EAF5EC; color:#2F6B45; }
.pm-chip.gcash{ background:#E6ECFA; color:#33488A; }
</style>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Payments &amp; Fees</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>Here's today's collections, what's still waiting on verification, and how revenue has trended.</p>
</div>

<div class="summary-grid">
  <div class="summary-card"><span class="num">₱<?php echo number_format($todayTotal); ?></span><span class="lbl">Today's Payments</span></div>
  <div class="summary-card accent-pending"><span class="num"><?php echo $pendingCount; ?></span><span class="lbl">Pending Payments</span></div>
  <div class="summary-card accent-completed"><span class="num">₱<?php echo number_format($todayCash); ?></span><span class="lbl">Cash Today</span></div>
  <div class="summary-card accent-scheduled"><span class="num">₱<?php echo number_format($todayGcash); ?></span><span class="lbl">GCash Today</span></div>
  <div class="summary-card"><span class="num">₱<?php echo number_format($allTimeTotal); ?></span><span class="lbl">Revenue Summary</span></div>
</div>

<div class="panel" style="margin-bottom:32px;">
  <div class="dash-section-label" style="margin-bottom:14px;">
    <h2 style="font-size:18px;">Payment Verification Queue</h2>
    <a href="payments.php" class="btn btn-outline btn-sm">View All →</a>
  </div>
  <?php if (empty($queue)): ?>
    <p class="upcoming-empty">Nothing waiting on verification right now.</p>
  <?php else: ?>
    <table class="queue-table">
      <thead><tr><th>Parishioner</th><th>Service</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
      <tbody>
        <?php foreach ($queue as $q): ?>
          <tr>
            <td><?php echo htmlspecialchars($q['full_name']); ?></td>
            <td><?php echo htmlspecialchars($serviceNames[$q['service_key']] ?? ucfirst($q['service_key'])); ?></td>
            <td>₱<?php echo number_format(payment_amount($q['service_key'], $feeMap)); ?></td>
            <td><?php if ($q['payment_method']): ?><span class="pm-chip <?php echo $q['payment_method']; ?>"><?php echo strtoupper($q['payment_method']); ?></span><?php else: ?>—<?php endif; ?></td>
            <td><?php echo $q['reference_number'] ? htmlspecialchars($q['reference_number']) : '—'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
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