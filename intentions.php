<?php
 //intentions.php
require_once 'includes/config.php';
require_role(['parishioner']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';

$uid = (int) $_SESSION['user_id'];

// Header dropdowns reuse the same notification query pattern as dashboard.php
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

$page_title = 'My Intentions — ' . $parish['name'];
require_once 'includes/dashboard-header.php';

// Real QR code, if the parish has uploaded one. Otherwise show a clearly
// labeled placeholder instead of anything that looks scannable/real.
?>

<style>
/* Scoped styles for the 2-step booking modal's payment step.
   Safe to move into assets/css/dashboard.css later if you'd rather
   keep all dashboard styling in one file — kept inline here so it
   doesn't touch/override anything already in your stylesheet. */
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

.qr-box img{ width:100%; height:100%; object-fit:contain; }
.qr-placeholder svg{ width:32px; height:32px; margin: 0 auto 8px; color: var(--gold-dim); }

.upload-drop:hover{ border-color: var(--gold); }
.upload-drop input[type=file]{ position:absolute; inset:0; opacity:0; cursor:pointer; }
.upload-drop .up-icon{ width:26px; height:26px; margin:0 auto 8px; color: var(--gold-dim); }
.upload-drop p{ font-size:12.5px; color: var(--ink-soft); margin:0; }
.upload-preview.show{ display:block; }
.upload-preview img{ max-width:140px; max-height:140px; border-radius:10px; border:1px solid var(--line); }
.upload-preview .up-filename{ font-size:11.5px; color: var(--ink-soft); margin-top:6px; word-break:break-all; }

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
</style>

<div class="dash-hero page-hero">
  <span class="eyebrow">My Intentions</span>
  <h1>Request a sacrament or Mass intention</h1>
  <p>Pick a service below to see its requirements and fee, then choose an open date and time.</p>
</div>

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
  foreach ($services as $svc): ?>
    <div class="intention-card">
      <div class="service-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $icons[$svc['icon']]; ?></svg>
      </div>
      <h3><?php echo htmlspecialchars($svc['name']); ?></h3>
      <p class="desc"><?php echo htmlspecialchars($svc['desc']); ?></p>
      <div class="service-fee">Estimated fee: <b><?php echo $svc['fee'] > 0 ? '₱' . number_format($svc['fee']) : 'Free'; ?></b></div>

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

      <button type="button" class="btn btn-gold btn-book"
        data-book-btn
        data-service-key="<?php echo htmlspecialchars($svc['key']); ?>"
        data-service-name="<?php echo htmlspecialchars($svc['name']); ?>"
        data-service-fee="<?php echo (int) $svc['fee']; ?>">
        Request This
      </button>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Booking modal ===== -->
<div class="modal-overlay" id="bookingModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="modalServiceName">Request Appointment</h3>
      <button type="button" class="modal-close" id="modalClose" aria-label="Close">&times;</button>
    </div>

    <div class="modal-steps">
      <span class="modal-step-dot active" id="stepDot1">1</span>
      <span class="modal-step-label">Details</span>
      <span class="modal-step-line"></span>
      <span class="modal-step-dot" id="stepDot2">2</span>
      <span class="modal-step-label">Payment</span>
    </div>

    <form id="bookingForm" enctype="multipart/form-data">
      <input type="hidden" name="service_key" id="serviceKeyInput">

      <!-- Step 1: appointment details -->
      <div class="modal-substep active" id="stepDetails">
        <div class="form-group">
          <label for="apptDate">Preferred Date</label>
          <input type="date" name="appointment_date" id="apptDate" min="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
          <label>Available Time Slots</label>
          <div id="slotGrid" class="slot-grid"><p class="slot-empty">Choose a date to see open times.</p></div>
          <input type="hidden" name="appointment_time" id="apptTimeInput">
          <p class="slot-hint">Times already taken are greyed out and can't be selected. First come, first served — the office still needs to confirm your request.</p>
        </div>

        <div class="form-group">
          <label for="apptNotes">Notes (optional)</label>
          <textarea name="notes" id="apptNotes" maxlength="255" placeholder="Anything the parish office should know (names, occasion, special requests)."></textarea>
        </div>

        <p class="form-error" id="formErrorStep1"></p>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline btn-sm" id="modalCancel">Cancel</button>
          <button type="button" class="btn btn-gold btn-sm" id="goToPayment">Next: Payment →</button>
        </div>
      </div>

      <!-- Step 2: payment -->
      <div class="modal-substep" id="stepPayment">
        <div class="form-group">
          <label>Estimated Fee: <b id="feeDisplay">₱0</b></label>
        </div>

        <div class="form-group">
          <label>Payment Method</label>
          <div class="pm-grid">
            <div class="pm-card">
              <input type="radio" name="payment_method" id="pmCash" value="cash" checked>
              <label for="pmCash">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                Cash
              </label>
            </div>
            <div class="pm-card">
              <input type="radio" name="payment_method" id="pmGcash" value="gcash">
              <label for="pmGcash">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg>
                GCash
              </label>
            </div>
          </div>
        </div>

        <div class="cash-panel show" id="cashPanel">
          <div class="cash-reminder">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            <span>Please settle payment in cash at the parish office. Bring this confirmation with you.</span>
          </div>
        </div>

        <div class="gcash-panel" id="gcashPanel">
          <div class="gcash-redirect-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/>
            </svg>
            <span>You'll be redirected to GCash to complete your payment securely. Once approved, your request is automatically confirmed — no screenshots or reference numbers needed.</span>
          </div>

          <div class="gcash-redirect-status" id="gcashRedirectStatus">
            <div class="redirect-spinner"></div>
            <span id="gcashRedirectText">Connecting to GCash&hellip;</span>
          </div>
        </div>

        <p class="form-error" id="formErrorStep2"></p>
        <p class="form-success" id="formSuccess"></p>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline btn-sm" id="backToDetails">← Back</button>
          <button type="submit" class="btn btn-gold btn-sm" id="modalSubmit">Submit Request</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/intentions.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/dashboard-footer.php'; ?>