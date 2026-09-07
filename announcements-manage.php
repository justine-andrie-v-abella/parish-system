<?php
//parish-system\announcements-manage.php
require_once 'includes/config.php';
require_role(['priest', 'secretary']);
require_once 'includes/db.php';
require_once 'includes/calendar.php';

$uid = (int) $_SESSION['user_id'];

// ---------------- Notifications (header dropdown) ----------------
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
      <div class="notif-item<?php echo is_true($n['is_read']) ? '' : ' unread'; ?>" data-notif-id="<?php echo $n['id']; ?>" data-appointment-id="<?php echo $n['appointment_id'] ?? ''; ?>" data-notif-type="<?php echo htmlspecialchars($n['type'] ?? ''); ?>">
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

$announcementsReady = $pdo->query("SELECT to_regclass('public.announcements')")->fetchColumn() !== null;

$rows = [];
if ($announcementsReady) {
    $rows = $pdo->query(
        "SELECT a.*, u.full_name AS author_name FROM announcements a
         JOIN users u ON u.id = a.created_by
         ORDER BY a.created_at DESC"
    )->fetchAll();
}

$page_title = 'Announcements — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<style>
.ann-card{ background:var(--white); border:1px solid var(--line); border-radius:var(--arch-sm); padding:20px 22px; margin-bottom:16px; }
.ann-card.inactive{ opacity:0.55; }
.ann-card h3{ font-size:16.5px; margin:0 0 6px; }
.ann-card .ann-meta{ font-family:var(--font-mono); font-size:10.5px; letter-spacing:0.5px; text-transform:uppercase; color:var(--ink-soft); margin-bottom:10px; }
.ann-card .ann-body{ font-size:13.5px; color:var(--ink); white-space:pre-wrap; margin-bottom:14px; }
.ann-card .cc-actions{ display:flex; gap:10px; }

.cmodal-overlay{
  position:fixed; inset:0; background:rgba(11,20,36,0.55); z-index:1000;
  display:flex; align-items:center; justify-content:center; padding:20px;
  opacity:0; pointer-events:none; transition:opacity .2s;
}
.cmodal-overlay.open{ opacity:1; pointer-events:auto; }
.cmodal-box{ background:var(--cream); border-radius:20px; border:1px solid var(--line); width:480px; max-width:100%; padding:26px; max-height:88vh; overflow-y:auto; }
.cmodal-box h3{ margin-bottom:16px; }
.cmodal-box label{ display:block; font-family:var(--font-mono); font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color:var(--ink-soft); margin:14px 0 6px; }
.cmodal-box input[type="text"], .cmodal-box textarea{ width:100%; border:1px solid var(--line); border-radius:12px; padding:11px 14px; font-family:inherit; font-size:13.5px; }
.cmodal-box textarea{ min-height:140px; resize:vertical; }
.cmodal-error{ font-size:12px; color:#A2432F; margin-top:8px; display:none; }
.cmodal-error.show{ display:block; }
.cmodal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }
</style>

<div class="page-head">
  <span class="eyebrow"><?php echo ucfirst($_SESSION['role']); ?></span>
  <h1>Announcements</h1>
  <p>Post parish-wide notices — every parishioner sees these on their dashboard and the Announcements page.</p>
</div>

<?php if (!$announcementsReady): ?>
  <div class="panel" style="margin-bottom:28px; border-color: var(--gold);">
    <h3>Announcements migration not applied yet</h3>
    <p style="font-size:13.5px; color:var(--ink-soft);">Import <code>database/migration_add_announcements.sql</code> (Supabase SQL Editor) to enable this feature.</p>
  </div>
<?php else: ?>

  <div style="margin-bottom:22px;">
    <button type="button" class="btn btn-gold btn-sm" id="addAnnouncementBtn">+ New Announcement</button>
  </div>

  <?php if (empty($rows)): ?>
    <div class="requests-empty">No announcements yet.</div>
  <?php else: ?>
    <?php foreach ($rows as $a): ?>
      <div class="ann-card<?php echo is_true($a['is_active']) ? '' : ' inactive'; ?>">
        <?php if (!is_true($a['is_active'])): ?><span class="inactive-tag">Hidden</span><?php endif; ?>
        <h3><?php echo htmlspecialchars($a['title']); ?></h3>
        <div class="ann-meta">By <?php echo htmlspecialchars($a['author_name']); ?> · <?php echo date('F j, Y g:i A', strtotime($a['created_at'])); ?></div>
        <div class="ann-body"><?php echo htmlspecialchars($a['body']); ?></div>
        <div class="cc-actions">
          <button type="button" class="edit-ann-btn"
            data-id="<?php echo $a['id']; ?>"
            data-title="<?php echo htmlspecialchars($a['title']); ?>"
            data-body="<?php echo htmlspecialchars($a['body']); ?>"
            data-active="<?php echo $a['is_active']; ?>">Edit</button>
          <button type="button" class="toggle-ann-btn" data-id="<?php echo $a['id']; ?>" data-active="<?php echo $a['is_active']; ?>">
            <?php echo is_true($a['is_active']) ? 'Hide' : 'Unhide'; ?>
          </button>
          <button type="button" class="danger delete-ann-btn" data-id="<?php echo $a['id']; ?>" data-title="<?php echo htmlspecialchars($a['title']); ?>">Delete</button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Add/Edit modal -->
  <div class="cmodal-overlay" id="annModal">
    <div class="cmodal-box">
      <h3 id="annModalTitle">New Announcement</h3>
      <form id="annForm">
        <input type="hidden" id="annId" value="">

        <label for="annTitle">Title</label>
        <input type="text" id="annTitle" maxlength="150" placeholder="e.g. Christmas Mass Schedule">

        <label for="annBody">Message</label>
        <textarea id="annBody" placeholder="Write the announcement here…"></textarea>

        <p class="cmodal-error" id="annError"></p>

        <div class="cmodal-actions">
          <button type="button" class="btn btn-outline btn-sm" id="annCancel">Cancel</button>
          <button type="button" class="btn btn-gold btn-sm" id="annSave">Save Announcement</button>
        </div>
      </form>
    </div>
  </div>

<?php endif; ?>

<script src="assets/js/announcements-manage.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/dashboard-footer.php'; ?>
