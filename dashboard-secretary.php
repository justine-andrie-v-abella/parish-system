<?php
require_once 'includes/config.php';
require_role(['secretary']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';

$sid = (int) $_SESSION['user_id'];
$serviceNames = array_column($services, 'name', 'key');
$today = date('Y-m-d');

// ---------------- Notifications (header dropdown) ----------------
$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$sid]);
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
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();

$approvedTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'confirmed' AND DATE(handled_at) = ?");
$approvedTodayStmt->execute([$today]);
$approvedToday = (int) $approvedTodayStmt->fetchColumn();

$todayScheduleStmt = $pdo->prepare(
    "SELECT a.*, u.full_name FROM appointments a JOIN users u ON u.id = a.user_id
     WHERE a.appointment_date = ? AND a.status IN ('confirmed','approved')
     ORDER BY a.appointment_time ASC"
);
$todayScheduleStmt->execute([$today]);
$todaySchedule = $todayScheduleStmt->fetchAll();

// ---------------- Pending queue preview ----------------
$queuePreview = $pdo->query(
    "SELECT a.*, u.full_name FROM appointments a JOIN users u ON u.id = a.user_id
     WHERE a.status = 'pending' ORDER BY a.appointment_date ASC LIMIT 6"
)->fetchAll();

$page_title = 'Secretary Dashboard — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="dash-hero">
  <span class="eyebrow" style="color:var(--gold-bright);">Scheduling &amp; Records</span>
  <h1>Peace be with you, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.</h1>
  <p>Here's what needs your attention today — pending requests, today's schedule, and what's coming up.</p>
</div>

<div class="quick-links-row">
  <a href="queue.php" class="quick-link-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>Appointment Queue</a>
  <a href="certificate-queue.php" class="quick-link-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>Certificate Requests</a>
  <a href="catalog.php" class="quick-link-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>Catalog</a>
</div>

<div class="summary-grid">
  <div class="summary-card accent-pending">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span>
    <span class="num"><?php echo $pendingCount; ?></span><span class="lbl">Pending Requests</span>
  </div>
  <div class="summary-card accent-completed">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
    <span class="num"><?php echo $approvedToday; ?></span><span class="lbl">Approved Today</span>
  </div>
  <div class="summary-card accent-scheduled">
    <span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></span>
    <span class="num"><?php echo count($todaySchedule); ?></span><span class="lbl">Today's Schedule</span>
  </div>
</div>

<div class="panel" style="margin-bottom:32px;">
  <div class="dash-section-label" style="margin-bottom:14px;">
    <h2 style="font-size:18px;">Today's Schedule</h2>
    <a href="schedule-print.php" target="_blank" class="btn btn-outline btn-sm">Generate Schedule →</a>
  </div>
  <?php if (empty($todaySchedule)): ?>
    <p class="upcoming-empty">Nothing confirmed for today yet.</p>
  <?php else: ?>
    <div class="mini-table-wrap">
      <table>
        <thead><tr><th>Time</th><th>Parishioner</th><th>Service</th></tr></thead>
        <tbody>
          <?php foreach ($todaySchedule as $s): ?>
            <tr>
              <td data-label="Time"><?php echo $s['appointment_time'] ? date('g:i A', strtotime($s['appointment_time'])) : 'TBA'; ?></td>
              <td data-label="Parishioner"><?php echo htmlspecialchars($s['full_name']); ?></td>
              <td data-label="Service"><?php echo htmlspecialchars($serviceNames[$s['service_key']] ?? ucfirst($s['service_key'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel" style="margin-bottom:40px;">
  <div class="dash-section-label" style="margin-bottom:14px;">
    <h2 style="font-size:18px;">Pending Appointment Queue</h2>
    <a href="queue.php" class="btn btn-outline btn-sm">View All →</a>
  </div>
  <?php if (empty($queuePreview)): ?>
    <p class="upcoming-empty">No pending requests right now.</p>
  <?php else: ?>
    <div class="mini-table-wrap">
      <table>
        <thead><tr><th>Parishioner</th><th>Service</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($queuePreview as $q): ?>
            <tr>
              <td data-label="Parishioner"><?php echo htmlspecialchars($q['full_name']); ?></td>
              <td data-label="Service"><?php echo htmlspecialchars($serviceNames[$q['service_key']] ?? ucfirst($q['service_key'])); ?></td>
              <td data-label="Date"><?php echo date('M j, Y', strtotime($q['appointment_date'])); ?></td>
              <td data-label="Status"><span class="status-pill pending">Pending</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>