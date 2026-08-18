<?php
//parish-system\catalog.php
require_once 'includes/config.php';
require_role(['priest']);
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
      <div class="notif-item<?php echo is_true($n['is_read']) ? '' : ' unread'; ?>">
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

$catalogRows = [];
$catalogRequirements = [];
if ($catalogReady) {
    $catalogRows = $pdo->query("SELECT * FROM services ORDER BY sort_order ASC, id ASC")->fetchAll();
    $reqRows = $pdo->query("SELECT * FROM service_requirements ORDER BY service_key, sort_order ASC, id ASC")->fetchAll();
    foreach ($reqRows as $r) {
        $catalogRequirements[$r['service_key']][] = $r['requirement_text'];
    }
    // Count appointments per service (to warn before delete)
    $countRows = $pdo->query("SELECT service_key, COUNT(*) c FROM appointments GROUP BY service_key")->fetchAll();
    $usageCounts = array_column($countRows, 'c', 'service_key');
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
?>

<style>
.catalog-grid{ display:grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap:22px; margin-bottom:28px; }
.catalog-card{ background:var(--white); border:1px solid var(--line); border-radius:var(--arch-sm); padding:22px; position:relative; }
.catalog-card.inactive{ opacity:0.55; }
.catalog-card .service-icon{ width:44px; height:44px; }
.catalog-card h3{ font-size:17px; margin:12px 0 4px; }
.catalog-card .desc{ font-size:12.5px; color:var(--ink-soft); min-height:36px; }
.catalog-card .fee{ font-family:var(--font-mono); font-size:13px; color:var(--brown); margin:10px 0; }
.catalog-card .cc-actions{ display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.catalog-card .cc-actions button{ font-family:var(--font-mono); font-size:10.5px; letter-spacing:0.5px; text-transform:uppercase; padding:6px 12px; border-radius:999px; border:1px solid var(--line); background:var(--white); color:var(--navy); }
.catalog-card .cc-actions .danger{ border-color:#E9C8C0; color:#A2432F; }
.inactive-tag{ position:absolute; top:16px; right:16px; font-family:var(--font-mono); font-size:9.5px; letter-spacing:1px; text-transform:uppercase; color:#A2432F; background:#FBEAE7; padding:3px 10px; border-radius:999px; }

.cmodal-overlay{ position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; pointer-events:none; transition:opacity .2s; }
.cmodal-overlay.open{ opacity:1; pointer-events:auto; }
.cmodal-box{ background:var(--cream); border-radius:20px; border:1px solid var(--line); width:480px; max-width:100%; padding:26px; max-height:88vh; overflow-y:auto; }
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
</style>

<div class="page-head">
  <span class="eyebrow">Priest / Administrator</span>
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

  <div style="margin-bottom:22px;">
    <button type="button" class="btn btn-gold btn-sm" id="addServiceBtn">+ Add New Service</button>
  </div>

  <div class="catalog-grid">
    <?php foreach ($catalogRows as $s):
        $reqLines = $catalogRequirements[$s['service_key']] ?? [];
        $usage = $usageCounts[$s['service_key']] ?? 0;
    ?>
      <div class="catalog-card<?php echo is_true($s['is_active']) ? '' : ' inactive'; ?>">
        <?php if (!is_true($s['is_active'])): ?><span class="inactive-tag">Inactive</span><?php endif; ?>
        <div class="service-icon" style="width:44px;height:44px;border-radius:50%;background:var(--cream-deep);color:var(--gold-dim);display:flex;align-items:center;justify-content:center;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><?php echo $icons[$s['icon']] ?? $icons['candle']; ?></svg>
        </div>
        <h3><?php echo htmlspecialchars($s['name']); ?></h3>
        <p class="desc"><?php echo htmlspecialchars($s['description']); ?></p>
        <div class="fee">₱<?php echo number_format($s['fee']); ?> · <?php echo $usage; ?> request<?php echo $usage === 1 ? '' : 's'; ?> on file</div>
        <div class="cc-actions">
          <button type="button" class="edit-service-btn"
            data-id="<?php echo $s['id']; ?>"
            data-key="<?php echo htmlspecialchars($s['service_key']); ?>"
            data-icon="<?php echo htmlspecialchars($s['icon']); ?>"
            data-name="<?php echo htmlspecialchars($s['name']); ?>"
            data-desc="<?php echo htmlspecialchars($s['description']); ?>"
            data-fee="<?php echo $s['fee']; ?>"
            data-active="<?php echo $s['is_active']; ?>"
            data-requirements="<?php echo htmlspecialchars(implode("\n", $reqLines)); ?>">Edit</button>
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

        <p class="cmodal-error" id="serviceError"></p>

        <div class="cmodal-actions">
          <button type="button" class="btn btn-outline btn-sm" id="serviceCancel">Cancel</button>
          <button type="button" class="btn btn-gold btn-sm" id="serviceSave">Save Service</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/catalog.js"></script>

<?php endif; ?>

<?php require_once 'includes/dashboard-footer.php'; ?>