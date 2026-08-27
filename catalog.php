<?php
//parish-system\catalog.php
require_once 'includes/config.php';
require_role(['priest', 'secretary']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';

$pid = (int) $_SESSION['user_id'];

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
      <div class="notif-item<?php echo is_true($n['is_read']) ? '' : ' unread'; ?>" data-notif-id="<?php echo $n['id']; ?>" data-appointment-id="<?php echo $n['appointment_id'] ?? ''; ?>">
        <span class="notif-dot"></span>
        <div><p><?php echo htmlspecialchars(preg_replace('/^DEMO:\s*/', '', $n['message'])); ?></p>
        <span class="time"><?php echo date('M j, g:i A', strtotime($n['created_at'])); ?></span></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php
$notifPanelHtml = ob_get_clean();

$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year'] : (int) date('Y');
$calendarPanelHtml = render_calendar_fragment($pdo, $month, $year);

// ---------------- Check the catalog migration has been applied ----------------
$catalogReady = $pdo->query("SELECT to_regclass('public.services')")->fetchColumn() !== null;

// ---------------- Check the schedules migration has been applied ----------------
$schedulesReady = $pdo->query("SELECT to_regclass('public.service_schedules')")->fetchColumn() !== null;

// ---------------- Check the itemized-fees migration has been applied ----------------
$feesReady = $pdo->query("SELECT to_regclass('public.service_fees')")->fetchColumn() !== null;

// ---------------- Check the certificate-requests migration has been applied ----------------
$categoryReady = $catalogReady && $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_name = 'services' AND column_name = 'category'"
)->fetchColumn() !== false;

// ---------------- Check the certificate form-fields migration has been applied ----------------
$certFieldsReady = $pdo->query("SELECT to_regclass('public.service_form_fields')")->fetchColumn() !== null;

$catalogRows = [];
$catalogRequirements = [];
$catalogSchedules = [];
$catalogFees = [];
$catalogCertFields = [];
if ($catalogReady) {
    $catalogRows = $pdo->query("SELECT * FROM services ORDER BY sort_order ASC, id ASC")->fetchAll();
    $reqRows = $pdo->query("SELECT * FROM service_requirements ORDER BY service_key, sort_order ASC, id ASC")->fetchAll();
    foreach ($reqRows as $r) {
        $catalogRequirements[$r['service_key']][] = $r['requirement_text'];
    }
    // Count appointments per service (to warn before delete)
    $countRows = $pdo->query("SELECT service_key, COUNT(*) c FROM appointments GROUP BY service_key")->fetchAll();
    $usageCounts = array_column($countRows, 'c', 'service_key');

    if ($schedulesReady) {
        $schedRows = $pdo->query("SELECT * FROM service_schedules WHERE is_active = true ORDER BY service_key, sort_order ASC, id ASC")->fetchAll();
        foreach ($schedRows as $r) {
            $catalogSchedules[$r['service_key']][] = $r;
        }
    }

    if ($feesReady) {
        $feeRows = $pdo->query("SELECT * FROM service_fees ORDER BY service_key, sort_order ASC, id ASC")->fetchAll();
        foreach ($feeRows as $r) {
            $catalogFees[$r['service_key']][] = $r;
        }
    }

    if ($certFieldsReady) {
        $fieldRows = $pdo->query("SELECT * FROM service_form_fields ORDER BY service_key, sort_order ASC, id ASC")->fetchAll();
        foreach ($fieldRows as $r) {
            $catalogCertFields[$r['service_key']][] = $r['field_label'];
        }
    }
}

$page_title = 'Service Catalog — ' . $parish['name'];
require_once 'includes/dashboard-header.php';

$icons = [
    'dove'   => '<path d="M3 12c3-4 7-6 9-2 2-4 6-2 9 2-3 6-7 4-9 2-2 2-6 4-9-2Z"/><circle cx="12" cy="9" r="1" fill="currentColor" stroke="none"/>',
    'flame'  => '<path d="M12 2c1 4-3 5-3 9a3 3 0 0 0 6 0c0-1.5-1-2-1-2s2 1 2 4a5 5 0 0 1-10 0C6 8 10 7 12 2Z"/>',
    'rings'  => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/>',
    'cross'  => '<path d="M12 3v18M6 9h12"/>',
    'candle' => '<path d="M12 2c1 2-1 2.5-1 4a1 1 0 0 0 2 0c0-1.5-2-2-1-4Z"/><rect x="9" y="8" width="6" height="13" rx="1"/><path d="M9 12h6"/>',
    'vessel' => '<path d="M8 3h8M12 3v4"/><path d="M6 9c0-1.1 2.7-2 6-2s6 .9 6 2-2.7 8-6 10c-3.3-2-6-8.9-6-10Z"/>',
];

$dowLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Turns a service's schedule rows into a short human-readable summary for the card. */
function summarize_schedule(array $rules, array $dowLabels): string {
    if (!$rules) return 'No schedule set';
    $parts = [];
    foreach ($rules as $r) {
        switch ($r['rule_type']) {
            case 'weekly':
                $parts[] = $dowLabels[(int)$r['day_of_week']] . ' ' . date('g:i A', strtotime($r['start_time']));
                break;
            case 'nth_weekday':
                $occ = str_replace(',', ' & ', $r['occurrences']);
                $parts[] = ucwords($occ) . ' ' . $dowLabels[(int)$r['day_of_week']] . ' ' . date('g:i A', strtotime($r['start_time']));
                break;
            case 'conditional':
                $parts[] = '+' . $r['offset_days'] . 'd after ' . str_replace('_', ' ', $r['trigger_event']) . ', ' . date('g:i A', strtotime($r['start_time']));
                break;
            case 'by_arrangement':
                $parts[] = 'By arrangement';
                break;
            case 'always_available':
                $parts[] = 'Always available';
                break;
        }
    }
    return implode(' · ', array_unique($parts));
}
?>

<style>
.catalog-grid{ display:grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap:22px; margin-bottom:28px; }
.catalog-card{ background:var(--white); border:1px solid var(--line); border-radius:var(--arch-sm); padding:22px; position:relative; }
.catalog-card.inactive{ opacity:0.55; }
.catalog-card .service-icon{ width:44px; height:44px; }
.catalog-card h3{ font-size:17px; margin:12px 0 4px; }
.catalog-card .desc{ font-size:12.5px; color:var(--ink-soft); min-height:36px; }
.catalog-card .fee{ font-family:var(--font-mono); font-size:13px; color:var(--brown); margin:10px 0; }
.catalog-card .schedule-summary{ font-size:11.5px; color:var(--ink-soft); background:var(--cream-deep); border-radius:8px; padding:6px 10px; margin-bottom:8px; }
.catalog-card .cc-actions{ display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.catalog-card .cc-actions button{ font-family:var(--font-mono); font-size:10.5px; letter-spacing:0.5px; text-transform:uppercase; padding:6px 12px; border-radius:999px; border:1px solid var(--line); background:var(--white); color:var(--navy); }
.catalog-card .cc-actions .danger{ border-color:#E9C8C0; color:#A2432F; }
.inactive-tag{ position:absolute; top:16px; right:16px; font-family:var(--font-mono); font-size:9.5px; letter-spacing:1px; text-transform:uppercase; color:#A2432F; background:#FBEAE7; padding:3px 10px; border-radius:999px; }

.cmodal-overlay{ position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; pointer-events:none; transition:opacity .2s; }
.cmodal-overlay.open{ opacity:1; pointer-events:auto; }
.cmodal-box{ background:var(--cream); border-radius:20px; border:1px solid var(--line); width:560px; max-width:100%; padding:26px; max-height:88vh; overflow-y:auto; }
.cmodal-box h3{ font-size:19px; margin-bottom:16px; }
.cmodal-box label{ display:block; font-family:var(--font-mono); font-size:10.5px; letter-spacing:1.5px; text-transform:uppercase; color:var(--ink-soft); margin-bottom:6px; margin-top:14px; }
.cmodal-box input, .cmodal-box select, .cmodal-box textarea{ width:100%; border:1px solid var(--line); border-radius:12px; padding:10px 13px; font-family:inherit; font-size:13.5px; background:var(--white); }
.cmodal-box textarea{ min-height:100px; resize:vertical; }
.icon-choice-grid{ display:grid; grid-template-columns:repeat(6,1fr); gap:8px; }
.icon-choice{ position:relative; }
.icon-choice input{ position:absolute; opacity:0; inset:0; margin:0; cursor:pointer; width:auto; }
.icon-choice label{ display:flex; align-items:center; justify-content:center; border:1px solid var(--line); border-radius:10px; padding:10px 0; cursor:pointer; margin:0; }
.icon-choice label svg{ width:18px; height:18px; color:var(--gold-dim); }
.icon-choice input:checked + label{ border-color:var(--gold); background:var(--cream-deep); }
.cmodal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:20px; }
.cmodal-error{ font-size:12px; color:#A2432F; margin-top:8px; display:none; }
.cmodal-error.show{ display:block; }

/* ---- Schedule rule builder ---- */
.sched-section{ border-top:1px dashed var(--line); margin-top:22px; padding-top:16px; }
.sched-section > label{ margin-top:0; }
.sched-rule{ border:1px solid var(--line); border-radius:14px; padding:14px; margin-bottom:12px; background:var(--white); position:relative; }
.sched-rule .sched-remove{ position:absolute; top:10px; right:10px; background:none; border:none; color:var(--ink-soft); font-size:16px; cursor:pointer; line-height:1; padding:2px 6px; }
.sched-rule label{ margin-top:10px; }
.sched-rule label:first-of-type{ margin-top:0; }
.sched-field{ display:none; }
.sched-field.show{ display:block; }
.dow-grid{ display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
.dow-choice{ position:relative; }
.dow-choice input{ position:absolute; opacity:0; inset:0; margin:0; cursor:pointer; }
.dow-choice label{ display:flex; align-items:center; justify-content:center; border:1px solid var(--line); border-radius:8px; padding:7px 0; margin:0; cursor:pointer; font-size:11px; text-transform:none; letter-spacing:0; }
.dow-choice input:checked + label{ border-color:var(--gold); background:var(--cream-deep); font-weight:600; }
.occ-grid{ display:grid; grid-template-columns:repeat(5,1fr); gap:4px; }
.occ-choice{ position:relative; }
.occ-choice input{ position:absolute; opacity:0; inset:0; margin:0; cursor:pointer; }
.occ-choice label{ display:flex; align-items:center; justify-content:center; border:1px solid var(--line); border-radius:8px; padding:7px 0; margin:0; cursor:pointer; font-size:10.5px; text-transform:none; letter-spacing:0; }
.occ-choice input:checked + label{ border-color:var(--gold); background:var(--cream-deep); font-weight:600; }
.sched-add-btn{ font-family:var(--font-mono); font-size:11px; letter-spacing:0.5px; text-transform:uppercase; padding:8px 14px; border-radius:999px; border:1px dashed var(--line); background:var(--white); color:var(--navy); cursor:pointer; margin-top:4px; }
</style>

<div class="page-head">
  <span class="eyebrow"><?php echo $_SESSION['role'] === 'secretary' ? 'Secretary' : 'Priest / Administrator'; ?></span>
  <h1>Service Catalog</h1>
  <p>The sacraments and fees shown on the homepage and in the booking flow. Changes here reflect everywhere immediately.</p>
</div>

<?php if (!$catalogReady): ?>
  <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
    <h3>Catalog migration not applied yet</h3>
    <p style="font-size:13.5px; color:var(--ink-soft);">
      This page needs <code>database/migration_add_catalog.sql</code> to be imported first (phpMyAdmin → SQL tab).
      Until then, services and fees are read from the hardcoded list in <code>includes/config.php</code> and can't be edited here.
    </p>
  </div>
<?php else: ?>

  <?php if (!$schedulesReady): ?>
    <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
      <h3>Schedule migration not applied yet</h3>
      <p style="font-size:13.5px; color:var(--ink-soft);">
        Import <code>database/migration_add_schedules.sql</code> to enable per-service schedule rules
        (which days/weeks a service is offered). Until then, services can still be edited but the
        Schedule section below is hidden and booking will not enforce any date pattern.
      </p>
    </div>
  <?php endif; ?>

  <?php if (!$feesReady): ?>
    <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
      <h3>Itemized fees migration not applied yet</h3>
      <p style="font-size:13.5px; color:var(--ink-soft);">
        Import <code>database/migration_add_service_fees.sql</code> (Supabase SQL Editor) to let a service
        list several fee lines — e.g. baptism's sponsor fee and prejordan card fee — instead of one flat
        amount. Until then, services can still be edited but the Itemized Fees section below is hidden.
      </p>
    </div>
  <?php endif; ?>

  <?php if (!$categoryReady): ?>
    <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
      <h3>Certificate requests migration not applied yet</h3>
      <p style="font-size:13.5px; color:var(--ink-soft);">
        Import <code>database/migration_add_certificate_requests.sql</code> (Supabase SQL Editor) to add the
        Category field (Sacrament vs Certificate Request) and the four certificate-request service entries.
      </p>
    </div>
  <?php endif; ?>

  <?php if (!$certFieldsReady): ?>
    <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
      <h3>Certificate form fields migration not applied yet</h3>
      <p style="font-size:13.5px; color:var(--ink-soft);">
        Import <code>database/migration_add_certificate_form_fields.sql</code> (Supabase SQL Editor) to let
        certificate-request services define which input fields the requestor fills in (e.g. "Full Name of
        Registrant", "Year of Baptism"). Until then, Certificate Request services have no configurable fields.
      </p>
    </div>
  <?php endif; ?>

  <div style="margin-bottom:22px;">
    <button type="button" class="btn btn-gold btn-sm" id="addServiceBtn">+ Add New Service</button>
  </div>

  <div class="catalog-grid">
    <?php foreach ($catalogRows as $s):
        $reqLines = $catalogRequirements[$s['service_key']] ?? [];
        $schedRules = $catalogSchedules[$s['service_key']] ?? [];
        $feeLines = $catalogFees[$s['service_key']] ?? [];
        $certFieldLines = $catalogCertFields[$s['service_key']] ?? [];
        $usage = $usageCounts[$s['service_key']] ?? 0;
        $svcCategory = $s['category'] ?? 'sacrament';
    ?>
      <div class="catalog-card<?php echo is_true($s['is_active']) ? '' : ' inactive'; ?>">
        <?php if (!is_true($s['is_active'])): ?><span class="inactive-tag">Inactive</span><?php endif; ?>
        <div class="service-icon" style="width:44px;height:44px;border-radius:50%;background:var(--cream-deep);color:var(--gold-dim);display:flex;align-items:center;justify-content:center;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><?php echo $icons[$s['icon']] ?? $icons['candle']; ?></svg>
        </div>
        <?php if ($categoryReady): ?>
          <span style="display:inline-block; font-family:var(--font-mono); font-size:9.5px; letter-spacing:0.5px; text-transform:uppercase; color:var(--ink-soft); background:var(--cream-deep); padding:2px 8px; border-radius:999px; margin-top:8px;"><?php echo $svcCategory === 'certificate' ? 'Certificate Request' : 'Sacrament'; ?></span>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($s['name']); ?></h3>
        <p class="desc"><?php echo htmlspecialchars($s['description']); ?></p>
        <?php if ($schedulesReady && $svcCategory !== 'certificate'): ?>
          <div class="schedule-summary"><?php echo htmlspecialchars(summarize_schedule($schedRules, $dowLabels)); ?></div>
        <?php endif; ?>
        <div class="fee">₱<?php echo number_format($s['fee']); ?> · <?php echo $usage; ?> request<?php echo $usage === 1 ? '' : 's'; ?> on file</div>
        <?php if ($feesReady && $feeLines): ?>
          <ul class="fee-breakdown" style="font-size:11.5px; color:var(--ink-soft); margin:0 0 10px; padding-left:16px;">
            <?php foreach ($feeLines as $f): ?>
              <li>₱<?php echo number_format($f['amount']); ?> — <?php echo htmlspecialchars($f['label']); ?><?php echo $f['note'] ? ' <span style="opacity:.75;">(' . htmlspecialchars($f['note']) . ')</span>' : ''; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="cc-actions">
          <button type="button" class="edit-service-btn"
            data-id="<?php echo $s['id']; ?>"
            data-key="<?php echo htmlspecialchars($s['service_key']); ?>"
            data-icon="<?php echo htmlspecialchars($s['icon']); ?>"
            data-name="<?php echo htmlspecialchars($s['name']); ?>"
            data-desc="<?php echo htmlspecialchars($s['description']); ?>"
            data-fee="<?php echo $s['fee']; ?>"
            data-active="<?php echo $s['is_active']; ?>"
            data-category="<?php echo htmlspecialchars($svcCategory); ?>"
            data-requirements="<?php echo htmlspecialchars(implode("\n", $reqLines)); ?>"
            data-fees="<?php echo htmlspecialchars(implode("\n", array_map(fn($f) => $f['label'] . ' | ' . $f['amount'] . ($f['note'] ? ' | ' . $f['note'] : ''), $feeLines))); ?>"
            data-cert-fields="<?php echo htmlspecialchars(implode("\n", $certFieldLines)); ?>"
            data-schedules="<?php echo htmlspecialchars(json_encode(array_map(function($r) {
                return [
                    'rule_type'     => $r['rule_type'],
                    'day_of_week'   => $r['day_of_week'],
                    'occurrences'   => $r['occurrences'],
                    'trigger_event' => $r['trigger_event'],
                    'offset_days'   => $r['offset_days'],
                    'start_time'    => $r['start_time'] ? substr($r['start_time'], 0, 5) : null,
                    'label'         => $r['label'],
                    'note'          => $r['note'],
                ];
            }, $schedRules))); ?>">Edit</button>
          <button type="button" class="toggle-service-btn" data-id="<?php echo $s['id']; ?>" data-active="<?php echo $s['is_active']; ?>">
            <?php echo is_true($s['is_active']) ? 'Deactivate' : 'Activate'; ?>
          </button>
          <button type="button" class="danger delete-service-btn" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>" data-usage="<?php echo $usage; ?>">Delete</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Add/Edit modal -->
  <div class="cmodal-overlay" id="serviceModal">
    <div class="cmodal-box">
      <h3 id="serviceModalTitle">Add New Service</h3>
      <form id="serviceForm">
        <input type="hidden" id="svcId" value="">

        <label for="svcKey">Service Key <span style="text-transform:none;">(used internally — set once, can't change later)</span></label>
        <input type="text" id="svcKey" placeholder="e.g. house_blessing" pattern="[a-z0-9_]+" title="lowercase letters, numbers, underscores only">

        <label for="svcName">Name</label>
        <input type="text" id="svcName" placeholder="e.g. House Blessing">

        <label for="svcDesc">Description</label>
        <textarea id="svcDesc" style="min-height:60px;" placeholder="Short description shown on the homepage and booking page."></textarea>

        <?php if ($categoryReady): ?>
        <label for="svcCategory">Category</label>
        <select id="svcCategory">
          <option value="sacrament">Sacrament / Rite — booked on a date (shown on Intentions)</option>
          <option value="certificate">Certificate Request — no date, requested on Certificates</option>
        </select>
        <?php endif; ?>

        <label for="svcFee">Fee (₱, enter 0 for free)</label>
        <input type="number" id="svcFee" min="0" step="1" placeholder="0">

        <label>Icon</label>
        <div class="icon-choice-grid">
          <?php foreach ($catalog_icon_keys as $ik): ?>
            <div class="icon-choice">
              <input type="radio" name="svcIcon" id="icon-<?php echo $ik; ?>" value="<?php echo $ik; ?>">
              <label for="icon-<?php echo $ik; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><?php echo $icons[$ik]; ?></svg></label>
            </div>
          <?php endforeach; ?>
        </div>

        <label for="svcRequirements">Requirements (one per line)</label>
        <textarea id="svcRequirements" placeholder="Valid ID&#10;Birth Certificate&#10;..."></textarea>

        <?php if ($feesReady): ?>
        <label for="svcFees">Itemized Fees <span style="text-transform:none;">(one per line: Label | Amount | Note — note is optional)</span></label>
        <textarea id="svcFees" placeholder="Sponsors (per head, beyond first 2) | 100 | First two sponsors free&#10;Prejordan card (per head) | 30"></textarea>
        <?php endif; ?>

        <?php if ($schedulesReady): ?>
        <div class="sched-section" id="schedSection">
          <label>Schedule Rules <span style="text-transform:none;">— when is this service actually offered?</span></label>
          <div id="schedRulesList"></div>
          <button type="button" class="sched-add-btn" id="addScheduleRuleBtn">+ Add schedule rule</button>
        </div>
        <?php endif; ?>

        <?php if ($certFieldsReady): ?>
        <div class="sched-section" id="certFieldsSection" style="display:none;">
          <label for="svcCertFields">Certificate Form Fields <span style="text-transform:none;">(one per line — what the requestor types in, e.g. "Full Name of Registrant")</span></label>
          <textarea id="svcCertFields" placeholder="Full Name of Registrant&#10;Birthday / Year of Birth&#10;Year of Baptism"></textarea>
        </div>
        <?php endif; ?>

        <p class="cmodal-error" id="serviceError"></p>

        <div class="cmodal-actions">
          <button type="button" class="btn btn-outline btn-sm" id="serviceCancel">Cancel</button>
          <button type="button" class="btn btn-gold btn-sm" id="serviceSave">Save Service</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Template for one schedule rule row, cloned by catalog.js -->
  <template id="schedRuleTemplate">
    <div class="sched-rule">
      <button type="button" class="sched-remove" title="Remove this rule">&times;</button>

      <label>Rule Type</label>
      <select class="sr-type">
        <option value="weekly">Every week, on specific day(s)</option>
        <option value="nth_weekday">Specific week(s) of the month (e.g. 1st &amp; 3rd Saturday)</option>
        <option value="conditional">Depends on another date (e.g. days after date of death)</option>
        <option value="by_arrangement">By arrangement (staff &amp; requester agree on a date)</option>
        <option value="always_available">Always available (no fixed schedule)</option>
      </select>

      <div class="sched-field sr-field-dow">
        <label>Day of Week</label>
        <div class="dow-grid">
          <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $d): ?>
            <div class="dow-choice">
              <input type="radio" class="sr-dow" name="sr-dow-TEMPLATE" value="<?php echo $i; ?>" id="sr-dow-TEMPLATE-<?php echo $i; ?>">
              <label for="sr-dow-TEMPLATE-<?php echo $i; ?>"><?php echo $d; ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="sched-field sr-field-occ">
        <label>Which occurrence(s) in the month</label>
        <div class="occ-grid">
          <?php foreach (['1'=>'1st','2'=>'2nd','3'=>'3rd','4'=>'4th','last'=>'Last'] as $val => $lbl): ?>
            <div class="occ-choice">
              <input type="checkbox" class="sr-occ" value="<?php echo $val; ?>" id="sr-occ-TEMPLATE-<?php echo $val; ?>">
              <label for="sr-occ-TEMPLATE-<?php echo $val; ?>"><?php echo $lbl; ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="sched-field sr-field-trigger">
        <label>Triggering Event</label>
        <select class="sr-trigger">
          <option value="date_of_death">Date of Death</option>
        </select>
        <label>Wait how many days after?</label>
        <input type="number" class="sr-offset" min="0" step="1" placeholder="e.g. 9">
      </div>

      <div class="sched-field sr-field-time">
        <label>Time</label>
        <input type="time" class="sr-time">
      </div>

      <div class="sched-field sr-field-label">
        <label>Label <span style="text-transform:none;">(shown to staff/parishioners)</span></label>
        <input type="text" class="sr-label" placeholder="e.g. 1st &amp; 3rd Sat Baptism">
      </div>

      <div class="sched-field sr-field-note">
        <label>Note <span style="text-transform:none;">(shown instead of a time slot)</span></label>
        <textarea class="sr-note" style="min-height:50px;" placeholder="e.g. Coordinate directly with the priest to agree on a date."></textarea>
      </div>
    </div>
  </template>

  <script src="assets/js/catalog.js"></script>

<?php endif; ?>

<?php require_once 'includes/dashboard-footer.php'; ?>