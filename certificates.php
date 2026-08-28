<?php
// certificates.php
require_once 'includes/config.php';
require_role(['parishioner']);
require_once 'includes/db.php';

$uid = (int) $_SESSION['user_id'];

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

// ---------------- Check the certificate-requests migration has been applied ----------------
$certReady = $pdo->query("SELECT to_regclass('public.certificate_requests')")->fetchColumn() !== null;

$myRequests = [];
if ($certReady) {
    $reqStmt = $pdo->prepare('SELECT * FROM certificate_requests WHERE user_id = ? ORDER BY created_at DESC');
    $reqStmt->execute([$uid]);
    $myRequests = $reqStmt->fetchAll();
}

$page_title = 'Certificates — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<style>
/* Reuses the same booking-modal payment-step styles as intentions.php. */
.modal-steps{ display:flex; align-items:center; gap:8px; margin-bottom:18px; }
.modal-step-dot{
  width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center;
  font-family: var(--font-mono); font-size:11px; font-weight:600;
  background: var(--cream-deep); color: var(--ink-soft); border:1px solid var(--line);
}
.modal-step-dot.active{ background: var(--navy); color:#fff; border-color: var(--navy); }
.modal-step-dot.done{ background: var(--gold); color: var(--navy-deep); border-color: var(--gold); }
.modal-step-line{ flex:1; height:1px; background: var(--line); }
.modal-step-label{ font-family: var(--font-mono); font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color: var(--ink-soft); }
.modal-substep{ display:none; }
.modal-substep.active{ display:block; }
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
.row-highlight{ animation: row-flash 1.4s ease-out; }
@keyframes row-flash{ from{ background: var(--cream-deep); } to{ background: transparent; } }
</style>

<div class="dash-hero page-hero">
  <span class="eyebrow">Certificates</span>
  <h1>Request a certificate</h1>
  <p>Certified copies of baptismal, confirmation, marriage, or death records — no appointment date needed.</p>
</div>

<?php if (!$certReady): ?>
  <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
    <h3>Certificate requests aren't set up yet</h3>
    <p style="font-size:13.5px; color:var(--ink-soft);">Please contact the parish office.</p>
  </div>
<?php else: ?>

<div class="intentions-grid">
  <?php
  $icons = [
    'dove'   => '<path d="M3 12c3-4 7-6 9-2 2-4 6-2 9 2-3 6-7 4-9 2-2 2-6 4-9-2Z"/><circle cx="12" cy="9" r="1" fill="currentColor" stroke="none"/>',
    'flame'  => '<path d="M12 2c1 4-3 5-3 9a3 3 0 0 0 6 0c0-1.5-1-2-1-2s2 1 2 4a5 5 0 0 1-10 0C6 8 10 7 12 2Z"/>',
    'rings'  => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/>',
    'cross'  => '<path d="M12 3v18M6 9h12"/>',
    'candle' => '<path d="M12 2c1 2-1 2.5-1 4a1 1 0 0 0 2 0c0-1.5-2-2-1-4Z"/><rect x="9" y="8" width="6" height="13" rx="1"/><path d="M9 12h6"/>',
    'vessel' => '<path d="M8 3h8M12 3v4"/><path d="M6 9c0-1.1 2.7-2 6-2s6 .9 6 2-2.7 8-6 10c-3.3-2-6-8.9-6-10Z"/>',
  ];
  foreach ($services as $svc):
    if (($svc['category'] ?? 'sacrament') !== 'certificate') continue;
  ?>
    <div class="intention-card">
      <div class="service-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $icons[$svc['icon']]; ?></svg>
      </div>
      <h3><?php echo htmlspecialchars($svc['name']); ?></h3>
      <p class="desc"><?php echo htmlspecialchars($svc['desc']); ?></p>
      <div class="service-fee">Fee: <b><?php echo $svc['fee'] > 0 ? '₱' . number_format($svc['fee']) : 'Free'; ?></b></div>

      <?php if (!empty($requirements[$svc['key']])): ?>
        <button type="button" class="req-toggle" data-req-toggle>
          <span class="plus">+</span> Requirements
        </button>
        <div class="intention-reqs">
          <ul>
            <?php foreach ($requirements[$svc['key']] as $doc): ?>
              <li><?php echo htmlspecialchars($doc); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <button type="button" class="btn btn-gold btn-book"
        data-cert-book-btn
        data-service-key="<?php echo htmlspecialchars($svc['key']); ?>"
        data-service-name="<?php echo htmlspecialchars($svc['name']); ?>"
        data-service-fee="<?php echo (int) $svc['fee']; ?>"
        data-fields="<?php echo htmlspecialchars(json_encode($certFields[$svc['key']] ?? [])); ?>">
        Request This
      </button>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Request modal ===== -->
<div class="modal-overlay" id="certModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="certModalServiceName">Request Certificate</h3>
      <button type="button" class="modal-close" id="certModalClose" aria-label="Close">&times;</button>
    </div>

    <div class="modal-steps">
      <span class="modal-step-dot active" id="certStepDot1">1</span>
      <span class="modal-step-label">Details</span>
      <span class="modal-step-line"></span>
      <span class="modal-step-dot" id="certStepDot2">2</span>
      <span class="modal-step-label">Payment</span>
    </div>

    <form id="certForm">
      <input type="hidden" name="service_key" id="certServiceKeyInput">

      <!-- Step 1: request details -->
      <div class="modal-substep active" id="certStepDetails">

        <div class="form-group">
          <label for="certRequestorName">Requestor's Full Name</label>
          <input type="text" name="requestor_name" id="certRequestorName" maxlength="150" required>
        </div>

        <!-- Populated by certificates.js from this service's staff-defined
             fields (Catalog → Certificate Form Fields), one text input per
             field label, in order. -->
        <div id="certDynamicFields"></div>

        <div class="form-group">
          <label for="certNotes">Notes (optional)</label>
          <textarea name="notes" id="certNotes" maxlength="255" placeholder="Anything the parish office should know."></textarea>
        </div>

        <p class="form-error" id="certFormErrorStep1"></p>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline btn-sm" id="certModalCancel">Cancel</button>
          <button type="button" class="btn btn-gold btn-sm" id="certGoToPayment">Next: Payment →</button>
        </div>
      </div>

      <!-- Step 2: payment -->
      <div class="modal-substep" id="certStepPayment">
        <div class="form-group">
          <label>Fee: <b id="certFeeDisplay">₱0</b></label>
        </div>

        <div class="form-group">
          <label>Payment Method</label>
          <div class="pm-grid">
            <div class="pm-card">
              <input type="radio" name="payment_method" id="certPmCash" value="cash" checked>
              <label for="certPmCash">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                Cash
              </label>
            </div>
            <div class="pm-card">
              <input type="radio" name="payment_method" id="certPmGcash" value="gcash">
              <label for="certPmGcash">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg>
                GCash
              </label>
            </div>
          </div>
        </div>

        <div class="cash-panel show" id="certCashPanel">
          <div class="cash-reminder">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            <span>Please settle payment in cash at the parish office. Bring this confirmation with you.</span>
          </div>
        </div>

        <div class="gcash-panel" id="certGcashPanel">
          <div class="gcash-redirect-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/>
            </svg>
            <span>You'll be redirected to GCash to complete your payment securely. Once approved, your request is automatically confirmed.</span>
          </div>

          <div class="gcash-redirect-status" id="certGcashRedirectStatus">
            <div class="redirect-spinner"></div>
            <span id="certGcashRedirectText">Connecting to GCash&hellip;</span>
          </div>
        </div>

        <p class="form-error" id="certFormErrorStep2"></p>
        <p class="form-success" id="certFormSuccess"></p>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline btn-sm" id="certBackToDetails">← Back</button>
          <button type="submit" class="btn btn-gold btn-sm" id="certModalSubmit">Submit Request</button>
        </div>
      </div>
    </form>
  </div>
</div>

<h2 style="margin-top:40px;">My Certificate Requests</h2>
<div class="requests-table-wrap">
  <?php if (empty($myRequests)): ?>
    <div class="requests-empty">
      <p>You haven't requested any certificates yet.</p>
    </div>
  <?php else: ?>
    <?php $serviceNames = array_column($services, 'name', 'key'); ?>
    <table class="requests-table">
      <thead>
        <tr>
          <th>Type</th>
          <th>Requestor</th>
          <th>Details</th>
          <th>Status</th>
          <th>Payment</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($myRequests as $r):
          $fieldValues = json_decode($r['field_values'] ?? '{}', true) ?: [];
          $detailParts = [];
          foreach ($fieldValues as $label => $value) {
              if ($value !== '' && $value !== null) $detailParts[] = $label . ': ' . $value;
          }
        ?>
          <tr data-cert-id="<?php echo (int) $r['id']; ?>">
            <td class="svc-cell"><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td><?php echo htmlspecialchars($r['requestor_name']); ?></td>
            <td><?php echo $detailParts ? htmlspecialchars(implode(' · ', $detailParts)) : '<span style="color:var(--ink-soft);">&mdash;</span>'; ?></td>
            <td><span class="status-pill <?php echo htmlspecialchars($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span></td>
            <td><span class="status-pill <?php echo $r['payment_status'] === 'paid' ? 'completed' : 'pending'; ?>"><?php echo ucfirst($r['payment_status']); ?></span></td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <button type="button" class="btn-cancel-req" data-cert-cancel-btn data-id="<?php echo (int) $r['id']; ?>">Cancel</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script src="assets/js/certificates.js?v=<?php echo time(); ?>"></script>

<?php endif; ?>

<?php require_once 'includes/dashboard-footer.php'; ?>
