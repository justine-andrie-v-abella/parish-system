<?php
//parish-system\announcements.php
require_once 'includes/config.php';
require_role(['parishioner']);
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
         WHERE a.is_active = true
         ORDER BY a.created_at DESC"
    )->fetchAll();
}

$page_title = 'Announcements — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<style>
.ann-card{ background:var(--white); border:1px solid var(--line); border-radius:var(--arch-sm); padding:20px 22px; margin-bottom:16px; }
.ann-card h3{ font-size:16.5px; margin:0 0 6px; }
.ann-card .ann-meta{ font-family:var(--font-mono); font-size:10.5px; letter-spacing:0.5px; text-transform:uppercase; color:var(--ink-soft); margin-bottom:10px; }
.ann-card .ann-body{ font-size:13.5px; color:var(--ink); white-space:pre-wrap; }
</style>

<div class="dash-hero page-hero">
  <span class="eyebrow">Announcements</span>
  <h1>Parish Announcements</h1>
  <p>News and updates from the parish office.</p>
</div>

<?php if (empty($rows)): ?>
  <div class="requests-empty">No announcements yet — check back soon.</div>
<?php else: ?>
  <?php foreach ($rows as $a): ?>
    <div class="ann-card">
      <h3><?php echo htmlspecialchars($a['title']); ?></h3>
      <div class="ann-meta"><?php echo date('F j, Y', strtotime($a['created_at'])); ?></div>
      <div class="ann-body"><?php echo htmlspecialchars($a['body']); ?></div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once 'includes/dashboard-footer.php'; ?>
