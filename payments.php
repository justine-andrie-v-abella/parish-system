<?php
require_once 'includes/config.php';
require_role(['treasurer']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';
require_once 'includes/payments.php';

$tid = (int) $_SESSION['user_id'];
$feeMap = get_fee_map($services);
$serviceNames = array_column($services, 'name', 'key');

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

$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year'] : (int) date('Y');
$calendarPanelHtml = render_calendar_fragment($pdo, $month, $year);

// ---------------- Filter ----------------
$allowedFilters = ['all', 'pending', 'paid', 'rejected', 'cash', 'gcash'];
$filter = in_array($_GET['filter'] ?? 'pending', $allowedFilters, true) ? ($_GET['filter'] ?? 'pending') : 'pending';

$where = '1=1';
if ($filter === 'pending')  $where = "a.payment_status = 'unpaid'";
if ($filter === 'paid')     $where = "a.payment_status = 'paid'";
if ($filter === 'rejected') $where = "a.payment_status = 'rejected'";
if ($filter === 'cash')     $where = "a.payment_method = 'cash'";
if ($filter === 'gcash')    $where = "a.payment_method = 'gcash'";

$rows = $pdo->query(
    "SELECT a.*, u.full_name FROM appointments a
     JOIN users u ON u.id = a.user_id
     WHERE $where
     ORDER BY a.created_at DESC LIMIT 200"
)->fetchAll();

$page_title = 'Payment Verification — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<style>
.filter-row{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:22px; }
.filter-chip{
  font-family: var(--font-mono); font-size:11px; letter-spacing:0.5px; text-transform:uppercase;
  padding:8px 16px; border-radius:999px; border:1px solid var(--line); color: var(--ink-soft); background: var(--white);
}
.filter-chip.active{ background: var(--navy); border-color: var(--navy); color:#fff; font-weight:600; }

.pm-chip{ font-family: var(--font-mono); font-size:10px; letter-spacing:0.5px; text-transform:uppercase; padding:3px 9px; border-radius:999px; }
.pm-chip.cash{ background:#EAF5EC; color:#2F6B45; }
.pm-chip.gcash{ background:#E6ECFA; color:#33488A; }

.pay-status{ font-family: var(--font-mono); font-size:10px; letter-spacing:0.5px; text-transform:uppercase; padding:3px 10px; border-radius:999px; }
.pay-status.unpaid{ background:#FBF3DE; color:#9C7C1E; }
.pay-status.paid{ background:#EAF5EC; color:#2F6B45; }
.pay-status.rejected{ background:#FBEAE7; color:#A2432F; }

.row-actions{ display:flex; gap:6px; flex-wrap:wrap; }
.row-actions button, .row-actions a{
  font-family: var(--font-mono); font-size:10.5px; letter-spacing:0.5px; text-transform:uppercase;
  padding:6px 12px; border-radius:999px; border:1px solid var(--line); background:var(--white); color:var(--navy);
}
.row-actions .verify-btn{ border-color: var(--gold); color:var(--navy); }
.row-actions .verify-btn:hover{ background: var(--cream-deep); }
.row-actions .reject-btn{ border-color:#E9C8C0; color:#A2432F; }
.row-actions .reject-btn:hover{ background:#FBEAE7; }
.screenshot-link{ font-size:11.5px; color: var(--gold-dim); text-decoration:underline; }

.reject-modal-overlay{
  position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000;
  display:flex; align-items:center; justify-content:center; padding:20px;
  opacity:0; pointer-events:none; transition:opacity .2s;
}
.reject-modal-overlay.open{ opacity:1; pointer-events:auto; }
.reject-modal-box{ background:var(--cream); border-radius:20px; border:1px solid var(--line); width:380px; max-width:100%; padding:24px; }
.reject-modal-box h3{ font-size:18px; margin-bottom:12px; }
.reject-modal-box textarea{ width:100%; border:1px solid var(--line); border-radius:12px; padding:11px 14px; font-family:inherit; font-size:13.5px; min-height:80px; resize:vertical; }
.reject-modal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
.reject-error{ font-size:12px; color:#A2432F; margin-top:6px; display:none; }
.reject-error.show{ display:block; }

.verify-modal-box{ width:440px; }
.vd-rows{ display:flex; flex-direction:column; gap:8px; }
.vd-row{ display:flex; justify-content:space-between; font-size:13.5px; padding-bottom:8px; border-bottom:1px dashed var(--line); }
.vd-row span:first-child{ color:var(--ink-soft); }
.vd-row span:last-child{ font-weight:500; color:var(--navy); text-align:right; }
</style>

<div class="page-head">
  <span class="eyebrow">Treasurer</span>
  <h1>Payment Verification</h1>
  <p>Review submitted payments, verify GCash proofs against reference numbers, and confirm cash collected at the office.</p>
</div>

<div class="filter-row">
  <?php foreach (['pending' => 'Pending', 'paid' => 'Paid', 'rejected' => 'Rejected', 'cash' => 'Cash', 'gcash' => 'GCash', 'all' => 'All'] as $key => $label): ?>
    <a href="?filter=<?php echo $key; ?>" class="filter-chip<?php echo $filter === $key ? ' active' : ''; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<div class="requests-table-wrap">
  <?php if (empty($rows)): ?>
    <div class="requests-empty">No payments in this view.</div>
  <?php else: ?>
    <table class="requests-table">
      <thead>
        <tr>
          <th>Request</th><th>Parishioner</th><th>Service</th><th>Amount</th>
          <th>Method</th><th>Reference</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?php echo $r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
            <td class="svc-cell"><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td>₱<?php echo number_format(payment_amount($r['service_key'], $feeMap)); ?></td>
            <td><?php if ($r['payment_method']): ?><span class="pm-chip <?php echo $r['payment_method']; ?>"><?php echo strtoupper($r['payment_method']); ?></span><?php else: ?>—<?php endif; ?></td>
            <td>
              <?php echo $r['reference_number'] ? htmlspecialchars($r['reference_number']) : '—'; ?>
              <?php if ($r['payment_screenshot']): ?>
                <br><a href="<?php echo htmlspecialchars($r['payment_screenshot']); ?>" target="_blank" rel="noopener" class="screenshot-link">View screenshot</a>
              <?php endif; ?>
            </td>
            <td>
              <span class="pay-status <?php echo $r['payment_status']; ?>"><?php echo ucfirst($r['payment_status']); ?></span>
              <?php if ($r['payment_status'] === 'rejected' && $r['rejection_reason']): ?>
                <div style="font-size:11px; color:var(--ink-soft); margin-top:4px; max-width:180px;"><?php echo htmlspecialchars($r['rejection_reason']); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <?php if ($r['payment_status'] === 'unpaid'): ?>
                  <button type="button" class="verify-btn"
                    data-verify-id="<?php echo $r['id']; ?>"
                    data-parishioner="<?php echo htmlspecialchars($r['full_name']); ?>"
                    data-service="<?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?>"
                    data-amount="<?php echo number_format(payment_amount($r['service_key'], $feeMap)); ?>"
                    data-method="<?php echo htmlspecialchars($r['payment_method'] ?? ''); ?>"
                    data-reference="<?php echo htmlspecialchars($r['reference_number'] ?? ''); ?>"
                    data-screenshot="<?php echo htmlspecialchars($r['payment_screenshot'] ?? ''); ?>"
                    data-date="<?php echo date('F j, Y', strtotime($r['appointment_date'])); ?>">View &amp; Verify</button>
                  <button type="button" class="reject-btn" data-reject-id="<?php echo $r['id']; ?>">Reject</button>
                <?php elseif ($r['payment_status'] === 'paid'): ?>
                  <a href="receipt.php?id=<?php echo $r['id']; ?>" target="_blank">Receipt</a>
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

<!-- Verify details modal -->
<div class="reject-modal-overlay" id="verifyModal">
  <div class="reject-modal-box verify-modal-box">
    <h3>Review before verifying</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:16px;">Check the submitted proof against the amount and reference number before confirming.</p>

    <div class="vd-rows">
      <div class="vd-row"><span>Request</span><span id="vdRequest">—</span></div>
      <div class="vd-row"><span>Parishioner</span><span id="vdParishioner">—</span></div>
      <div class="vd-row"><span>Service</span><span id="vdService">—</span></div>
      <div class="vd-row"><span>Appointment Date</span><span id="vdDate">—</span></div>
      <div class="vd-row"><span>Amount Due</span><span id="vdAmount">—</span></div>
      <div class="vd-row"><span>Payment Method</span><span id="vdMethod">—</span></div>
      <div class="vd-row" id="vdReferenceRow"><span>Reference Number</span><span id="vdReference">—</span></div>
    </div>

    <div id="vdScreenshotWrap" style="display:none; margin-top:14px;">
      <p style="font-family:var(--font-mono); font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color:var(--ink-soft); margin-bottom:8px;">GCash Screenshot</p>
      <a id="vdScreenshotLink" href="#" target="_blank" rel="noopener">
        <img id="vdScreenshotImg" src="" alt="Submitted GCash payment screenshot" style="width:100%; max-height:260px; object-fit:contain; border-radius:12px; border:1px solid var(--line); background:var(--cream-deep);">
      </a>
      <p style="font-size:11px; color:var(--ink-soft); margin-top:6px;">Click the image to open full size in a new tab.</p>
    </div>

    <div id="vdNoScreenshot" style="display:none; margin-top:14px; padding:14px; background:var(--cream-deep); border-radius:12px; font-size:12.5px; color:var(--ink-soft);">
      No screenshot was submitted — this is likely a cash payment collected at the office. Verify once cash is received.
    </div>

    <p class="reject-error" id="verifyError"></p>

    <div class="reject-modal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="verifyCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="verifyConfirm">Confirm Verification</button>
    </div>
  </div>
</div>

<!-- Reject reason modal -->
<div class="reject-modal-overlay" id="rejectModal">
  <div class="reject-modal-box">
    <h3>Reject this payment</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:10px;">This will notify the parishioner and ask them to visit the office.</p>
    <textarea id="rejectReason" placeholder="e.g. Reference number doesn't match any GCash transaction, screenshot unreadable, amount doesn't match…"></textarea>
    <p class="reject-error" id="rejectError"></p>
    <div class="reject-modal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="rejectCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="rejectConfirm">Reject Payment</button>
    </div>
  </div>
</div>

<script src="assets/js/payments.js"></script>

<?php require_once 'includes/dashboard-footer.php'; ?>