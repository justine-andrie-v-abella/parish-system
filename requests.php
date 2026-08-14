<?php
//requests.php
require_once 'includes/config.php';
require_role(['parishioner']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';

$uid = (int) $_SESSION['user_id'];
$serviceNames = array_column($services, 'name', 'key');

$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$uid]);
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

$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year'] : (int) date('Y');
$calendarPanelHtml = render_calendar_fragment($pdo, $month, $year);

$requestsStmt = $pdo->prepare('SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC, created_at DESC');
$requestsStmt->execute([$uid]);
$requests = $requestsStmt->fetchAll();

$page_title = 'View Requests — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="dash-hero page-hero">
  <span class="eyebrow">View Requests</span>
  <h1>Your appointment requests</h1>
  <p>Every intention you've requested, with its current status and payment standing. You can cancel a request while it's still pending.</p>
</div>

<div class="requests-table-wrap">
  <?php if (empty($requests)): ?>
    <div class="requests-empty">
      <p>You haven't requested any appointments yet.</p>
      <a href="intentions.php">Book your first intention &rarr;</a>
    </div>
  <?php else: ?>
    <table class="requests-table">
      <thead>
        <tr>
          <th>Service</th>
          <th>Date &amp; Time</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Notes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
          <tr data-request-id="<?php echo (int) $r['id']; ?>">
            <td class="svc-cell"><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td>
              <?php echo date('M j, Y', strtotime($r['appointment_date'])); ?>
              <?php if ($r['appointment_time']): ?>
                &middot; <?php echo date('g:i A', strtotime($r['appointment_time'])); ?>
              <?php endif; ?>
            </td>
            <td><span class="status-pill <?php echo htmlspecialchars($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span></td>
            <td><span class="status-pill <?php echo $r['payment_status'] === 'paid' ? 'completed' : 'pending'; ?>"><?php echo ucfirst($r['payment_status']); ?></span></td>
            <td><?php echo $r['notes'] ? htmlspecialchars($r['notes']) : '<span style="color:var(--ink-soft);">&mdash;</span>'; ?></td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <button type="button" class="btn-cancel-req" data-cancel-btn data-id="<?php echo (int) $r['id']; ?>">Cancel</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script src="assets/js/requests.js"></script>

<?php require_once 'includes/dashboard-footer.php'; ?>