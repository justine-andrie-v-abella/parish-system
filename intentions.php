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

$page_title = 'My Intentions — ' . $parish['name'];
require_once 'includes/dashboard-header.php';

// Real QR code, if the parish has uploaded one. Otherwise show a clearly
// labeled placeholder instead of anything that looks scannable/real.
?>

<style>
/* Scoped styles for the 2-step booking modal (details, then documents).
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

/* ---- Booking calendar (per-service day picker) ---- */
/* Looks like a normal input field; clicking it pops the calendar open
   below it, the same interaction as the header's calendar/notification
   dropdowns — it doesn't sit permanently expanded in the form. */
.book-cal-trigger{
  display:flex; align-items:center; gap:9px; width:100%; text-align:left;
  border:1px solid var(--line); border-radius: var(--arch-sm); background:#fff;
  padding:10px 12px; font-family:inherit; font-size:13.5px; color: var(--ink);
  cursor:pointer; transition: border-color .15s;
}
.book-cal-trigger:hover, .book-cal-trigger.open{ border-color: var(--gold); }
.book-cal-trigger svg{ flex-shrink:0; color: var(--ink-soft); }
.book-cal-trigger .bct-placeholder{ color: var(--ink-soft); }
.book-cal-trigger .bct-chevron{ margin-left:auto; transition: transform .15s; }
.book-cal-trigger.open .bct-chevron{ transform: rotate(180deg); }

/* Moved to <body> by JS and positioned with fixed coordinates (see
   .actions-menu elsewhere in the app for the same technique) so it isn't
   clipped by .modal-box's overflow-y:auto. */
.book-cal{
  position:fixed; z-index:1200; width:300px; max-width: calc(100vw - 32px);
  border:1px solid var(--line); border-radius: var(--arch-sm); padding:14px;
  background: var(--cream); box-shadow: var(--shadow-lift);
  opacity:0; transform: translateY(-6px); pointer-events:none; transition: opacity .16s var(--ease), transform .16s var(--ease);
}
.book-cal.open{ opacity:1; transform:none; pointer-events:auto; }
.book-cal-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.book-cal-title{ font-family: var(--font-mono); font-size:12.5px; font-weight:700; letter-spacing:0.5px; color: var(--navy); }
.book-cal-nav{
  width:26px; height:26px; border-radius:50%; border:1px solid var(--line); background:#fff; color: var(--ink-soft);
  display:flex; align-items:center; justify-content:center; cursor:pointer; transition: all .15s;
}
.book-cal-nav:hover{ border-color: var(--gold); color: var(--navy); }
.book-cal-nav:disabled{ opacity:0.35; cursor:not-allowed; }
.book-cal-legend{ display:flex; gap:14px; font-size:10.5px; color: var(--ink-soft); margin-bottom:10px; }
.book-cal-legend .bc-dot{ display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:5px; vertical-align:middle; }
.bc-dot-avail{ background:#3F9A5C; }
.bc-dot-full{ background:#C0483A; }
.book-cal-grid{ display:grid; grid-template-columns: repeat(7, 1fr); gap:4px; }
.book-cal-dow{ text-align:center; font-family: var(--font-mono); font-size:9.5px; color: var(--ink-soft); padding-bottom:4px; }
.book-cal-cell{
  aspect-ratio:1; display:flex; align-items:center; justify-content:center; border-radius:8px; font-size:12px;
  color: var(--ink-soft); background:transparent; border:1px solid transparent; cursor:default; position:relative;
}
.book-cal-cell.empty{ visibility:hidden; }
.book-cal-cell.bc-avail{ background:#EAF6EE; color:#2A7A44; border-color:#CDEBD6; cursor:pointer; font-weight:600; }
.book-cal-cell.bc-avail:hover{ background:#3F9A5C; color:#fff; }
.book-cal-cell.bc-full{ background:#FBEAE7; color:#B4453A; cursor:not-allowed; text-decoration:line-through; opacity:0.75; }
.book-cal-cell.bc-selected{ background: var(--navy) !important; color:#fff !important; border-color: var(--navy) !important; }
.book-cal-cell.bc-today{ box-shadow: inset 0 0 0 1.5px var(--gold); }
.book-cal-empty-note{ grid-column: 1 / -1; text-align:center; font-size:11.5px; color: var(--ink-soft); padding:8px 0; }
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
  foreach ($services as $svc):
    if (($svc['category'] ?? 'sacrament') === 'certificate') continue; // certificates are requested on certificates.php, not booked here
  ?>
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
        <?php if (!empty($fees[$svc['key']])): ?>
          <p style="font-family:var(--font-mono, monospace); font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color:var(--ink-soft); margin:14px 0 6px;">Fees</p>
          <ul>
            <?php foreach ($fees[$svc['key']] as $f): ?>
              <li>₱<?php echo number_format($f['amount']); ?> — <?php echo htmlspecialchars($f['label']); ?><?php echo $f['note'] ? ' (' . htmlspecialchars($f['note']) . ')' : ''; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <button type="button" class="btn btn-gold btn-book"
        data-book-btn
        data-service-key="<?php echo htmlspecialchars($svc['key']); ?>"
        data-service-name="<?php echo htmlspecialchars($svc['name']); ?>"
        data-service-fee="<?php echo (int) $svc['fee']; ?>"
        data-requirements="<?php echo htmlspecialchars(json_encode($requirements[$svc['key']] ?? [])); ?>">
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
      <span class="modal-step-label">Documents</span>
    </div>

    <form id="bookingForm" enctype="multipart/form-data">
      <input type="hidden" name="service_key" id="serviceKeyInput">

      <!-- Step 1: appointment details -->
      <div class="modal-substep active" id="stepDetails">

        <!-- Only shown for services whose schedule rule is 'conditional'
             (e.g. Burial Mass = date of death + N days). Toggled by
             intentions.js based on CONDITIONAL_SERVICE_KEYS. -->
        <div class="form-group" id="dateOfDeathGroup" style="display:none;">
          <label for="dateOfDeath">Date of Death</label>
          <input type="date" id="dateOfDeath" max="<?php echo date('Y-m-d'); ?>">
          <p class="slot-hint">The Mass date is computed automatically from this.</p>
        </div>

        <div class="form-group" id="apptDateGroup">
          <label>Preferred Date</label>

          <!-- Shown only for services with a fixed weekly/1st-3rd-week-style
               schedule. Clicking it pops out a calendar (like the header's
               calendar/notification dropdowns) so a parishioner can see at a
               glance which days the service is offered, instead of guessing
               dates in a plain date picker. Populated by intentions.js from
               ajax/get-service-calendar.php. -->
          <!-- Shown only while ajax/get-service-calendar.php is resolving,
               so the plain date field never flashes first for a service
               that turns out to have a calendar. -->
          <p class="slot-hint" id="apptDateLoading">Checking availability&hellip;</p>

          <button type="button" class="book-cal-trigger" id="bookCalTrigger" style="display:none;" aria-expanded="false">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
            <span id="bookCalTriggerText" class="bct-placeholder">Choose a date</span>
            <svg class="bct-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
          </button>

          <div id="bookCal" class="book-cal">
            <div class="book-cal-head">
              <button type="button" class="book-cal-nav" id="bookCalPrev" aria-label="Previous month">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
              </button>
              <span class="book-cal-title" id="bookCalTitle"></span>
              <button type="button" class="book-cal-nav" id="bookCalNext" aria-label="Next month">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
              </button>
            </div>
            <div class="book-cal-legend">
              <span><i class="bc-dot bc-dot-avail"></i> Open</span>
              <span><i class="bc-dot bc-dot-full"></i> Fully booked</span>
            </div>
            <div class="book-cal-grid" id="bookCalGrid"></div>
          </div>

          <!-- Fallback for by_arrangement / always_available services, and
               for services with no schedule configured yet — kept exactly
               as before. -->
          <input type="date" name="appointment_date" id="apptDate" min="<?php echo date('Y-m-d'); ?>" style="display:none;" required>
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
          <button type="button" class="btn btn-gold btn-sm" id="goToPayment">Next: Documents →</button>
        </div>
      </div>

      <!-- Step 2: document upload — reviewed by the parish office before
           payment is unlocked (see requests.php). Skipped entirely (no
           files required) for services with no listed requirements. -->
      <div class="modal-substep" id="stepPayment">
        <!-- Populated by intentions.js with one file input per requirement
             line (JPG, PNG, or PDF, 5MB each) — data-requirements on the
             "Request This" button. -->
        <div id="docsFieldsContainer"></div>

        <div class="form-group" id="docsNoneNote" style="display:none;">
          <p class="slot-hint">This service has no document requirements — you can submit and proceed straight to payment.</p>
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