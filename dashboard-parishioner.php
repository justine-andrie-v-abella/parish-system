<?php
//dashboard-parishioner.php
require_once 'includes/config.php';
require_role(['parishioner']);
require_once 'includes/db.php';

$uid = (int) $_SESSION['user_id'];
$serviceNames = array_column($services, 'name', 'key');

// ---------------- Summary cards ----------------
$totalReq = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE user_id = ?');
$totalReq->execute([$uid]);
$totalRequests = (int) $totalReq->fetchColumn();

$pendingReq = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'pending'");
$pendingReq->execute([$uid]);
$pendingRequests = (int) $pendingReq->fetchColumn();

$scheduledReq = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status IN ('confirmed','approved') AND appointment_date >= CURRENT_DATE");
$scheduledReq->execute([$uid]);
$scheduledAppointments = (int) $scheduledReq->fetchColumn();

$completedReq = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'completed'");
$completedReq->execute([$uid]);
$completedAppointments = (int) $completedReq->fetchColumn();

$unpaidReq = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND payment_status = 'unpaid' AND status NOT IN ('cancelled','rejected')");
$unpaidReq->execute([$uid]);
$unpaidAppointments = (int) $unpaidReq->fetchColumn();

// ---------------- Upcoming appointment ----------------
$upcomingStmt = $pdo->prepare(
    "SELECT * FROM appointments WHERE user_id = ? AND status IN ('pending','confirmed','approved')
     AND appointment_date >= CURRENT_DATE ORDER BY appointment_date ASC, appointment_time ASC LIMIT 1"
);
$upcomingStmt->execute([$uid]);
$upcoming = $upcomingStmt->fetch();

// ---------------- Recent activity (this user's own requests) ----------------
$activityStmt = $pdo->prepare('SELECT * FROM appointments WHERE user_id = ? ORDER BY updated_at DESC LIMIT 4');
$activityStmt->execute([$uid]);
$activity = $activityStmt->fetchAll();

$activityText = [
    'pending'   => 'requested',
    'confirmed' => 'was scheduled',
    'approved'  => 'was approved',
    'completed' => 'was completed',
    'cancelled' => 'was cancelled',
    'rejected'  => 'was declined',
];

// ---------------- Notifications ----------------
$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$uid]);
$notifications = $notifStmt->fetchAll();
$unreadCount = count(array_filter($notifications, fn($n) => !is_true($n['is_read'])));

// ---------------- Calendar (current month, or ?month=&year=) ----------------
$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year'] : (int) date('Y');
$firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth  = (int) date('t', strtotime($firstOfMonth));
$startWeekday = (int) date('w', strtotime($firstOfMonth)); // 0=Sun
$today        = date('Y-m-d');

$prevMonth = $month === 1 ? 12 : $month - 1;
$prevYear  = $month === 1 ? $year - 1 : $year;
$nextMonth = $month === 12 ? 1 : $month + 1;
$nextYear  = $month === 12 ? $year + 1 : $year;

$lastOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$aggStmt = $pdo->prepare(
    "SELECT appointment_date,
            COUNT(*) AS total,
            SUM(CASE WHEN status IN ('confirmed','approved','completed') THEN 1 ELSE 0 END) AS confirmed_count
     FROM appointments
     WHERE appointment_date BETWEEN ? AND ? AND status NOT IN ('cancelled','rejected')
     GROUP BY appointment_date"
);
$aggStmt->execute([$firstOfMonth, $lastOfMonth]);
$dayAgg = [];
foreach ($aggStmt->fetchAll() as $row) {
    $dayAgg[$row['appointment_date']] = $row;
}

$holidayStmt = $pdo->prepare('SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ?');
$holidayStmt->execute([$firstOfMonth, $lastOfMonth]);
$holidays = [];
foreach ($holidayStmt->fetchAll() as $row) {
    $holidays[$row['holiday_date']] = $row['name'];
}

$CAPACITY = 3; // appointments/day before a date is considered "Fully Booked"

function calendar_status(string $date, array $dayAgg, array $holidays, int $capacity, string $today): array {
    if (isset($holidays[$date])) {
        return ['status-holiday', $holidays[$date]];
    }
    if (!isset($dayAgg[$date])) {
        return $date < $today ? ['', 'No activity'] : ['status-available', 'Available'];
    }
    $row = $dayAgg[$date];
    if ((int) $row['total'] >= $capacity) {
        return ['status-full', 'Fully booked'];
    }
    if ((int) $row['confirmed_count'] === 0) {
        return ['status-pending', 'Pending approval'];
    }
    return ['status-reserved', 'Reserved'];
}

// ---------------- Capture calendar markup for the header dropdown ----------------
ob_start();
?>
<div class="cal-head">
  <span class="cal-title"><?php echo date('F Y', strtotime($firstOfMonth)); ?></span>
  <div class="cal-nav">
    <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>"
       class="cal-nav-link"
       data-month="<?php echo $prevMonth; ?>"
       data-year="<?php echo $prevYear; ?>"
       aria-label="Previous month">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>"
       class="cal-nav-link"
       data-month="<?php echo $nextMonth; ?>"
       data-year="<?php echo $nextYear; ?>"
       aria-label="Next month">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
    </a>
  </div>
</div>

<div class="cal-legend">
  <span><i style="background:#3F9A5C;"></i> Available</span>
  <span><i style="background:#3E5AA8;"></i> Reserved</span>
  <span><i style="background:#D8A227;"></i> Pending</span>
  <span><i style="background:#9A958A;"></i> Holiday</span>
  <span><i style="background:#C0483A;"></i> Full</span>
</div>

<div class="cal-grid">
  <?php foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $dow): ?>
    <div class="cal-dow"><?php echo $dow; ?></div>
  <?php endforeach; ?>

  <?php for ($i = 0; $i < $startWeekday; $i++): ?>
    <div class="cal-cell empty"></div>
  <?php endfor; ?>

  <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
      [$statusClass, $statusLabel] = calendar_status($date, $dayAgg, $holidays, $CAPACITY, $today);
      $isToday = $date === $today;
  ?>
    <div class="cal-cell <?php echo $statusClass; ?><?php echo $isToday ? ' today' : ''; ?>" title="<?php echo htmlspecialchars($statusLabel); ?>">
      <span><?php echo $d; ?></span>
      <?php if ($statusClass): ?><span class="dot"></span><?php endif; ?>
    </div>
  <?php endfor; ?>
</div>
<?php
$calendarPanelHtml = ob_get_clean();

// ---------------- Recent activity panel for header dropdown ----------------
ob_start();
?>
<h4>Recent Activity</h4>
<?php if (empty($activity)): ?>
  <p class="upcoming-empty">No activity yet.</p>
<?php else: ?>
  <?php foreach ($activity as $a):
      $svcName = $serviceNames[$a['service_key']] ?? ucfirst($a['service_key']);
      $verb = $activityText[$a['status']] ?? 'updated';
  ?>
    <div class="notif-item">
      <span class="notif-dot"></span>
      <div>
        <p>Your <strong><?php echo htmlspecialchars($svcName); ?></strong> request <?php echo $verb; ?>.</p>
        <span class="time"><?php echo date('M j, g:i A', strtotime($a['updated_at'])); ?></span>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php
$activityPanelHtml = ob_get_clean();

// ---------------- Capture notifications markup for the header dropdown ----------------
ob_start();
?>
<h4>Notifications</h4>
<?php if (empty($notifications)): ?>
  <p class="upcoming-empty">You're all caught up.</p>
<?php else: ?>
  <?php foreach ($notifications as $n): ?>
    <div class="notif-item<?php echo is_true($n['is_read']) ? '' : ' unread'; ?>" data-notif-id="<?php echo $n['id']; ?>" data-appointment-id="<?php echo $n['appointment_id'] ?? ''; ?>">
      <span class="notif-dot"></span>
      <div>
        <p><?php echo htmlspecialchars(preg_replace('/^DEMO:\s*/', '', $n['message'])); ?></p>
        <span class="time"><?php echo date('M j, g:i A', strtotime($n['created_at'])); ?></span>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php
$notifPanelHtml = ob_get_clean();

$page_title = 'Dashboard — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="dash-hero">
  <div class="dash-hero-grid">
    <div>
      <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
      <p>Here's where your requests stand, what's coming up on the parish calendar, and what needs your attention.</p>
    </div>

    <div class="hero-upcoming">
      <span class="hu-label">Upcoming Appointment</span>
      <?php if ($upcoming): ?>
        <div class="upcoming-card">
          <div class="upcoming-date">
            <span class="day"><?php echo date('d', strtotime($upcoming['appointment_date'])); ?></span>
            <span class="mon"><?php echo date('M', strtotime($upcoming['appointment_date'])); ?></span>
          </div>
          <div>
            <strong style="display:block; font-size:15px;"><?php echo htmlspecialchars($serviceNames[$upcoming['service_key']] ?? ucfirst($upcoming['service_key'])); ?></strong>
            <span style="font-size:12.5px;">
              <?php echo $upcoming['appointment_time'] ? date('g:i A', strtotime($upcoming['appointment_time'])) : 'Time TBA'; ?>
            </span>
            <div style="margin-top:8px;"><span class="status-pill <?php echo $upcoming['status']; ?>"><?php echo ucfirst($upcoming['status']); ?></span></div>
          </div>
        </div>
      <?php else: ?>
        <p class="upcoming-empty">No upcoming appointments yet.</p>
        <a href="intentions.php" class="btn btn-gold btn-sm" style="margin-top:10px;">Book one now</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Summary cards -->
<div class="summary-grid">
  <div class="summary-card"><span class="num"><?php echo $totalRequests; ?></span><span class="lbl">Total Requests</span></div>
  <div class="summary-card accent-pending"><span class="num"><?php echo $pendingRequests; ?></span><span class="lbl">Pending Requests</span></div>
  <div class="summary-card accent-scheduled"><span class="num"><?php echo $scheduledAppointments; ?></span><span class="lbl">Scheduled</span></div>
  <div class="summary-card accent-completed"><span class="num"><?php echo $completedAppointments; ?></span><span class="lbl">Completed</span></div>
  <div class="summary-card accent-unpaid"><span class="num"><?php echo $unpaidAppointments; ?></span><span class="lbl">Unpaid</span></div>
</div>


<!-- Quick actions -->
<div class="dash-section-label"><h2>Quick Actions</h2></div>
<div class="quick-actions">
  <a href="intentions.php" class="quick-action">
    <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 5v14M5 12h14"/></svg></div>
    <span>New Appointment</span>
  </a>
  <a href="requests.php" class="quick-action">
    <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18M3 12h18M3 18h18"/></svg></div>
    <span>View Requests</span>
  </a>
  <a href="index.php#services" class="quick-action">
    <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    <span>View Fees</span>
  </a>
  <a href="coming-soon.php?feature=Chat+with+Secretary" class="quick-action">
    <div class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg></div>
    <span>Chat with Secretary</span>
  </a>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>