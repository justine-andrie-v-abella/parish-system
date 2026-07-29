<?php
require_once 'includes/config.php';
require_role(['secretary']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';

$sid = (int) $_SESSION['user_id'];
$serviceNames = array_column($services, 'name', 'key');

// ---------------- Notifications (header dropdown) ----------------
$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notifStmt->execute([$sid]);
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

// ---------------- Filter + search ----------------
$allowedFilters = ['all', 'pending', 'confirmed', 'completed', 'cancelled', 'rejected'];
$filter = in_array($_GET['status'] ?? 'pending', $allowedFilters, true) ? ($_GET['status'] ?? 'pending') : 'pending';
$q = trim($_GET['q'] ?? '');

$where = '1=1';
$params = [];
if ($filter !== 'all') {
    $where .= ' AND a.status = ?';
    $params[] = $filter;
}
if ($q !== '') {
    $where .= ' AND (u.full_name LIKE ? OR a.service_key LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$stmt = $pdo->prepare(
    "SELECT a.*, u.full_name, u.email FROM appointments a
     JOIN users u ON u.id = a.user_id
     WHERE $where
     ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 300"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = 'Appointment Queue — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<style>
.filter-row{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; align-items:center; }
.filter-chip{ font-family: var(--font-mono); font-size:11px; letter-spacing:0.5px; text-transform:uppercase; padding:8px 16px; border-radius:999px; border:1px solid var(--line); color: var(--ink-soft); background: var(--white); }
.filter-chip.active{ background: var(--navy); border-color: var(--navy); color:#fff; font-weight:600; }

.queue-toolbar{ display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
.queue-search{ display:flex; gap:8px; }
.queue-search input{ border:1px solid var(--line); border-radius:999px; padding:9px 16px; font-size:13px; font-family:inherit; width:220px; background:var(--white); }
.queue-search input:focus{ outline:none; border-color:var(--gold); }
.export-row{ display:flex; gap:8px; }
.export-row a{ font-family: var(--font-mono); font-size:11px; letter-spacing:0.5px; text-transform:uppercase; padding:9px 16px; border-radius:999px; border:1px solid var(--line); color:var(--navy); background:var(--white); }
.export-row a:hover{ border-color:var(--gold); background:var(--cream-deep); }

.status-pill{ font-family: var(--font-mono); font-size:10px; letter-spacing:0.5px; text-transform:uppercase; padding:3px 10px; border-radius:999px; }
.status-pill.pending{ background:#FBF3DE; color:#9C7C1E; }
.status-pill.confirmed, .status-pill.approved{ background:#EAEEF9; color:#33488A; }
.status-pill.completed{ background:#EAF5EC; color:#2F6B45; }
.status-pill.cancelled, .status-pill.rejected{ background:#FBEAE7; color:#A2432F; }

.pm-chip{ font-family: var(--font-mono); font-size:10px; letter-spacing:0.5px; text-transform:uppercase; padding:3px 9px; border-radius:999px; }
.pm-chip.unpaid{ background:#FBF3DE; color:#9C7C1E; }
.pm-chip.paid{ background:#EAF5EC; color:#2F6B45; }
.pm-chip.rejected{ background:#FBEAE7; color:#A2432F; }

.row-actions{ display:flex; gap:6px; flex-wrap:wrap; }
.row-actions button{ font-family: var(--font-mono); font-size:10.5px; letter-spacing:0.5px; text-transform:uppercase; padding:6px 11px; border-radius:999px; border:1px solid var(--line); background:var(--white); color:var(--navy); }
.row-actions .approve-btn{ border-color: var(--gold); }
.row-actions .approve-btn:hover{ background: var(--cream-deep); }
.row-actions .reject-btn{ border-color:#E9C8C0; color:#A2432F; }
.row-actions .reject-btn:hover{ background:#FBEAE7; }
.row-actions .docs-btn:hover, .row-actions .reschedule-btn:hover{ background: var(--cream-deep); }

.qmodal-overlay{ position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; pointer-events:none; transition:opacity .2s; }
.qmodal-overlay.open{ opacity:1; pointer-events:auto; }
.qmodal-box{ background:var(--cream); border-radius:20px; border:1px solid var(--line); width:420px; max-width:100%; padding:24px; max-height:88vh; overflow-y:auto; }
.qmodal-box h3{ font-size:18px; margin-bottom:10px; }
.qmodal-box textarea, .qmodal-box input[type=date]{ width:100%; border:1px solid var(--line); border-radius:12px; padding:11px 14px; font-family:inherit; font-size:13.5px; }
.qmodal-box textarea{ min-height:80px; resize:vertical; }
.qmodal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
.qmodal-error{ font-size:12px; color:#A2432F; margin-top:6px; display:none; }
.qmodal-error.show{ display:block; }
.qmodal-slot-grid{ display:grid; grid-template-columns: repeat(4,1fr); gap:8px; margin-top:10px; }
.qmodal-slot-btn{ border:1px solid var(--line); background: var(--white); border-radius:10px; padding:9px 4px; font-size:12px; color: var(--navy); text-align:center; }
.qmodal-slot-btn:hover:not(:disabled){ border-color: var(--gold); background: var(--cream-deep); }
.qmodal-slot-btn.selected{ background: var(--navy); color:#fff; border-color: var(--navy); }
.qmodal-slot-btn:disabled{ opacity:0.4; text-decoration: line-through; cursor:not-allowed; }
.qmodal-slot-empty{ font-size:12.5px; color:var(--ink-soft); grid-column: 1/-1; }
</style>

<div class="page-head">
  <span class="eyebrow">Secretary</span>
  <h1>Appointment Queue</h1>
  <p>Review incoming requests, approve or reject them, ask for documents, or reschedule.</p>
</div>

<div class="queue-toolbar">
  <form class="queue-search" method="get">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search parishioner or service…">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
  </form>
  <div class="export-row">
    <a href="export-queue.php?format=pdf&status=<?php echo urlencode($filter); ?>&q=<?php echo urlencode($q); ?>" target="_blank">Export PDF</a>
    <a href="export-queue.php?format=doc&status=<?php echo urlencode($filter); ?>&q=<?php echo urlencode($q); ?>">Export DOCX</a>
  </div>
</div>

<div class="filter-row">
  <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label): ?>
    <a href="?status=<?php echo $key; ?>&q=<?php echo urlencode($q); ?>" class="filter-chip<?php echo $filter === $key ? ' active' : ''; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<div class="requests-table-wrap">
  <?php if (empty($rows)): ?>
    <div class="requests-empty">No requests in this view.</div>
  <?php else: ?>
    <table class="requests-table">
      <thead>
        <tr><th>Request</th><th>Parishioner</th><th>Service</th><th>Date</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?php echo $r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
            <td class="svc-cell"><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td><?php echo date('M j, Y', strtotime($r['appointment_date'])); ?><?php echo $r['appointment_time'] ? ' · ' . date('g:i A', strtotime($r['appointment_time'])) : ''; ?></td>
            <td><span class="pm-chip <?php echo $r['payment_status']; ?>"><?php echo ucfirst($r['payment_status']); ?></span></td>
            <td>
              <span class="status-pill <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span>
              <?php if ($r['status'] === 'rejected' && $r['status_reason']): ?>
                <div style="font-size:11px; color:var(--ink-soft); margin-top:4px; max-width:160px;"><?php echo htmlspecialchars($r['status_reason']); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <?php if ($r['status'] === 'pending'): ?>
                  <button type="button" class="approve-btn" data-approve-id="<?php echo $r['id']; ?>">Approve</button>
                  <button type="button" class="reject-btn" data-reject-id="<?php echo $r['id']; ?>" data-reject-scope="request">Reject</button>
                  <button type="button" class="docs-btn" data-docs-id="<?php echo $r['id']; ?>">Request Docs</button>
                  <button type="button" class="reschedule-btn"
                    data-reschedule-id="<?php echo $r['id']; ?>"
                    data-current-date="<?php echo htmlspecialchars($r['appointment_date']); ?>">Reschedule</button>
                <?php elseif (in_array($r['status'], ['confirmed', 'approved'], true)): ?>
                  <button type="button" class="reject-btn" data-reject-id="<?php echo $r['id']; ?>" data-reject-scope="request">Cancel</button>
                  <button type="button" class="docs-btn" data-docs-id="<?php echo $r['id']; ?>">Request Docs</button>
                  <button type="button" class="reschedule-btn"
                    data-reschedule-id="<?php echo $r['id']; ?>"
                    data-current-date="<?php echo htmlspecialchars($r['appointment_date']); ?>">Reschedule</button>
                <?php else: ?>
                  <span style="font-size:11px; color:var(--ink-soft);">—</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Reject modal -->
<div class="qmodal-overlay" id="rejectModal">
  <div class="qmodal-box">
    <h3>Reject this request</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:10px;">This notifies the parishioner with your reason.</p>
    <textarea id="rejectReason" placeholder="e.g. Date conflicts with a diocesan event, incomplete requirements…"></textarea>
    <p class="qmodal-error" id="rejectError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="rejectCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="rejectConfirm">Reject</button>
    </div>
  </div>
</div>

<!-- Request documents modal -->
<div class="qmodal-overlay" id="docsModal">
  <div class="qmodal-box">
    <h3>Request more documents</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:10px;">Documents are brought in person — this just sends a reminder of what's needed.</p>
    <textarea id="docsMessage" placeholder="e.g. Baptismal certificate of the groom, valid ID of both sponsors…"></textarea>
    <p class="qmodal-error" id="docsError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="docsCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="docsConfirm">Send Reminder</button>
    </div>
  </div>
</div>

<!-- Reschedule modal -->
<div class="qmodal-overlay" id="rescheduleModal">
  <div class="qmodal-box">
    <h3>Reschedule this request</h3>
    <label style="display:block; font-family:var(--font-mono); font-size:10.5px; letter-spacing:1.5px; text-transform:uppercase; color:var(--ink-soft); margin-bottom:8px;">New Date</label>
    <input type="date" id="rescheduleDate" min="<?php echo date('Y-m-d'); ?>">
    <div class="qmodal-slot-grid" id="rescheduleSlots"><div class="qmodal-slot-empty">Choose a date to see open times.</div></div>
    <p class="qmodal-error" id="rescheduleError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="rescheduleCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="rescheduleConfirm">Confirm New Date</button>
    </div>
  </div>
</div>

<script src="assets/js/queue.js"></script>

<?php require_once 'includes/dashboard-footer.php'; ?>