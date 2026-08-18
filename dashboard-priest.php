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
      <div class="notif-item<?php echo is_true($n['is_read']) ? '' : ' unread'; ?>">
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

<style>
.chart-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:32px; }
@media (max-width:900px){ .chart-grid{ grid-template-columns:1fr; } }
.chart-bars{ display:flex; align-items:flex-end; gap:8px; height:130px; margin-top:8px; }
.chart-bar-col{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; gap:6px; }
.chart-bar{ width:100%; max-width:34px; background: linear-gradient(180deg, var(--gold-bright), var(--gold)); border-radius:6px 6px 2px 2px; min-height:2px; }
.chart-bar-label{ font-family: var(--font-mono); font-size:9.5px; color: var(--ink-soft); text-align:center; }
.chart-bar-value{ font-family: var(--font-mono); font-size:9px; color: var(--navy); }

.svc-bar-row{ display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.svc-bar-label{ width:120px; flex-shrink:0; font-size:12.5px; color: var(--ink-soft); }
.svc-bar-track{ flex:1; background: var(--cream-deep); border-radius:999px; height:10px; overflow:hidden; }
.svc-bar-fill{ height:100%; background: linear-gradient(90deg, var(--gold), var(--gold-bright)); border-radius:999px; }
.svc-bar-amount{ width:80px; text-align:right; font-family: var(--font-mono); font-size:11.5px; color:var(--navy); }

.activity-cols{ display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:32px; }
@media (max-width:900px){ .activity-cols{ grid-template-columns:1fr; } }
.mini-log-item{ padding:10px 0; border-bottom:1px dashed var(--line); font-size:12.5px; }
.mini-log-item:last-child{ border-bottom:none; }
.mini-log-item .who{ font-weight:600; color:var(--navy); }
.mini-log-item .when{ font-family: var(--font-mono); font-size:10px; color:var(--ink-soft); display:block; margin-top:2px; }

.mini-table{ width:100%; border-collapse:collapse; }
.mini-table th{ text-align:left; font-family: var(--font-mono); font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--ink-soft); padding:10px 12px; border-bottom:1px solid var(--line); }
.mini-table td{ padding:12px; border-bottom:1px dashed var(--line); font-size:13px; }
.mini-table tr:last-child td{ border-bottom:none; }
.status-pill{ font-family: var(--font-mono); font-size:10px; letter-spacing:0.5px; text-transform:uppercase; padding:3px 10px; border-radius:999px; }
.status-pill.pending{ background:#FBF3DE; color:#9C7C1E; }
.status-pill.confirmed, .status-pill.approved{ background:#EAEEF9; color:#33488A; }
.status-pill.completed{ background:#EAF5EC; color:#2F6B45; }
.status-pill.cancelled, .status-pill.rejected{ background:#FBEAE7; color:#A2432F; }

.timeline{ position:relative; padding-left:20px; }
.timeline::before{ content:''; position:absolute; left:4px; top:6px; bottom:6px; width:1px; background:var(--line); }
.timeline-item{ position:relative; padding-bottom:18px; }
.timeline-item:last-child{ padding-bottom:0; }
.timeline-item::before{ content:''; position:absolute; left:-20px; top:4px; width:8px; height:8px; border-radius:50%; background:var(--gold); }
.timeline-item .tl-desc{ font-size:13px; color:var(--ink); margin:0 0 3px; }
.timeline-item .tl-meta{ font-family: var(--font-mono); font-size:10.5px; color:var(--ink-soft); }
.timeline-item .tl-role{ text-transform:uppercase; letter-spacing:0.5px; color:var(--gold-dim); }
</style>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Administrator</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>An overview of the whole parish office — appointments, revenue, and what your staff have been doing.</p>
</div>

<div class="summary-grid">
  <div class="summary-card"><span class="num"><?php echo $totalAppointments; ?></span><span class="lbl">Appointments Overview</span></div>
  <div class="summary-card accent-completed"><span class="num"><?php echo $completedServices; ?></span><span class="lbl">Completed Services</span></div>
  <div class="summary-card accent-pending"><span class="num"><?php echo $pendingRequests; ?></span><span class="lbl">Pending Requests</span></div>
  <div class="summary-card"><span class="num">₱<?php echo number_format($totalRevenue); ?></span><span class="lbl">Total Revenue</span></div>
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
    <table class="mini-table">
      <thead><tr><th>Parishioner</th><th>Service</th><th>Date</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentRequests as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
            <td><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td><?php echo date('M j, Y', strtotime($r['appointment_date'])); ?></td>
            <td><span class="status-pill <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
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