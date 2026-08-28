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

$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year'] : (int) date('Y');
$calendarPanelHtml = render_calendar_fragment($pdo, $month, $year);

$requestsStmt = $pdo->prepare('SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC, created_at DESC');
$requestsStmt->execute([$uid]);
$requests = $requestsStmt->fetchAll();

$page_title = 'View Requests — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<style>
.pm-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:18px; }
.pm-card{ position:relative; }
.pm-card input{ position:absolute; opacity:0; inset:0; margin:0; cursor:pointer; }
.pm-card label{
  display:flex; flex-direction:column; align-items:center; gap:8px;
  border:1px solid var(--line); border-radius:14px; padding:18px 10px; text-align:center; cursor:pointer;
  transition: all .18s; font-size:13px; color: var(--ink-soft);
}
.pm-card label svg{ width:24px; height:24px; color: var(--gold-dim); }
.pm-card input:checked + label{ border-color: var(--gold); background: var(--cream-deep); color:var(--navy); font-weight:600; }
.gcash-panel, .cash-panel{ display:none; }
.gcash-panel.show, .cash-panel.show{ display:block; }
.cash-reminder{
  display:flex; gap:10px; align-items:flex-start; background:#FBF1DD; border:1px solid var(--gold);
  border-radius:12px; padding:14px; font-size:13px; color: var(--brown);
}
.cash-reminder svg{ width:18px; height:18px; flex-shrink:0; color: var(--gold-dim); }
.gcash-redirect-status{
  display:none; align-items:center; gap:10px; margin-top:14px;
  padding:12px 14px; background: var(--cream-deep); border-radius:10px;
  font-size:12.5px; color: var(--ink-soft);
}
.gcash-redirect-status.show{ display:flex; }
.redirect-spinner{
  width:16px; height:16px; border-radius:50%;
  border:2px solid var(--line); border-top-color: var(--gold);
  animation: spin .7s linear infinite; flex-shrink:0;
}
@keyframes spin{ to{ transform: rotate(360deg); } }
.qmodal-overlay{ position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; pointer-events:none; transition:opacity .2s; }
.qmodal-overlay.open{ opacity:1; pointer-events:auto; }
.qmodal-box{ background:var(--cream); border-radius:20px; border:1px solid var(--line); width:420px; max-width:100%; padding:24px; max-height:88vh; overflow-y:auto; }
.qmodal-box h3{ font-size:18px; margin-bottom:10px; }
.qmodal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
.qmodal-error{ font-size:12px; color:#A2432F; margin-top:6px; display:none; }
.qmodal-error.show{ display:block; }
.row-highlight{ animation: row-flash 1.4s ease-out; }
@keyframes row-flash{ from{ background: var(--cream-deep); } to{ background: transparent; } }
</style>

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
          <th>Documents</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Notes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r):
          $docsStatus = $r['documents_status'] ?? 'verified';
          $canPay = $docsStatus === 'verified' && $r['payment_status'] === 'unpaid' && $r['payment_method'] === null;
          $svcFee = 0;
          foreach ($services as $s) { if ($s['key'] === $r['service_key']) { $svcFee = (int) $s['fee']; break; } }
        ?>
          <tr data-request-id="<?php echo (int) $r['id']; ?>">
            <td class="svc-cell"><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td>
              <?php echo date('M j, Y', strtotime($r['appointment_date'])); ?>
              <?php if ($r['appointment_time']): ?>
                &middot; <?php echo date('g:i A', strtotime($r['appointment_time'])); ?>
              <?php endif; ?>
            </td>
            <td>
              <span class="status-pill <?php echo $docsStatus === 'verified' ? 'completed' : ($docsStatus === 'resubmit_requested' ? 'rejected' : 'pending'); ?>">
                <?php echo $docsStatus === 'resubmit_requested' ? 'Resubmit Requested' : ucfirst($docsStatus); ?>
              </span>
              <?php if ($docsStatus === 'resubmit_requested' && $r['documents_reason']): ?>
                <div style="font-size:11px; color:var(--ink-soft); margin-top:4px; max-width:180px;"><?php echo htmlspecialchars($r['documents_reason']); ?></div>
              <?php endif; ?>
            </td>
            <td><span class="status-pill <?php echo htmlspecialchars($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span></td>
            <td>
              <?php if ($r['payment_status'] === 'unpaid' && $r['payment_method'] === null): ?>
                <span class="status-pill pending">Awaiting Payment</span>
              <?php elseif ($r['payment_status'] === 'unpaid'): ?>
                <span class="status-pill pending">Pending Verification</span>
              <?php else: ?>
                <span class="status-pill <?php echo $r['payment_status'] === 'paid' ? 'completed' : 'pending'; ?>"><?php echo ucfirst($r['payment_status']); ?></span>
              <?php endif; ?>
            </td>
            <td><?php echo $r['notes'] ? htmlspecialchars($r['notes']) : '<span style="color:var(--ink-soft);">&mdash;</span>'; ?></td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <button type="button" class="btn-cancel-req" data-cancel-btn data-id="<?php echo (int) $r['id']; ?>">Cancel</button>
              <?php endif; ?>
              <?php if ($canPay): ?>
                <button type="button" class="btn btn-gold btn-sm" data-pay-btn data-id="<?php echo (int) $r['id']; ?>" data-fee="<?php echo $svcFee; ?>">Proceed to Payment</button>
              <?php endif; ?>
              <?php if ($docsStatus === 'resubmit_requested'): ?>
                <button type="button" class="btn btn-outline btn-sm" data-reupload-btn data-id="<?php echo (int) $r['id']; ?>" data-requirements="<?php echo htmlspecialchars(json_encode($requirements[$r['service_key']] ?? [])); ?>">Re-upload Documents</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Payment modal -->
<div class="qmodal-overlay" id="payModal">
  <div class="qmodal-box">
    <h3>Proceed to Payment</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:14px;">Fee: <b id="payFeeDisplay">₱0</b></p>

    <div class="pm-grid">
      <div class="pm-card">
        <input type="radio" name="pay_payment_method" id="payPmCash" value="cash" checked>
        <label for="payPmCash">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
          Cash
        </label>
      </div>
      <div class="pm-card">
        <input type="radio" name="pay_payment_method" id="payPmGcash" value="gcash">
        <label for="payPmGcash">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg>
          GCash
        </label>
      </div>
    </div>

    <div class="cash-panel show" id="payCashPanel">
      <div class="cash-reminder">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <span>Please settle payment in cash at the parish office.</span>
      </div>
    </div>

    <div class="gcash-panel" id="payGcashPanel">
      <div class="gcash-redirect-note" style="font-size:13px; color:var(--ink-soft); display:flex; gap:10px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg>
        <span>You'll be redirected to GCash to complete your payment securely.</span>
      </div>
      <div class="gcash-redirect-status" id="payGcashRedirectStatus">
        <div class="redirect-spinner"></div>
        <span>Connecting to GCash&hellip;</span>
      </div>
    </div>

    <p class="qmodal-error" id="payError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="payCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="payConfirm">Submit Payment</button>
    </div>
  </div>
</div>

<!-- Re-upload documents modal -->
<div class="qmodal-overlay" id="reuploadModal">
  <div class="qmodal-box">
    <h3>Re-upload Documents</h3>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:10px;">Upload corrected scans — this replaces your previous files and sends them back for review.</p>
    <div id="reuploadFieldsContainer"></div>
    <p class="qmodal-error" id="reuploadError"></p>
    <div class="qmodal-actions">
      <button type="button" class="btn btn-outline btn-sm" id="reuploadCancel">Cancel</button>
      <button type="button" class="btn btn-gold btn-sm" id="reuploadConfirm">Submit</button>
    </div>
  </div>
</div>

<script src="assets/js/requests.js"></script>

<?php require_once 'includes/dashboard-footer.php'; ?>
