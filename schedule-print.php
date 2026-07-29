<?php
require_once 'includes/config.php';
require_role(['secretary', 'priest']);
require_once 'includes/db.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stmt = $pdo->prepare(
    "SELECT a.*, u.full_name, u.contact_number FROM appointments a
     JOIN users u ON u.id = a.user_id
     WHERE a.appointment_date = ? AND a.status IN ('confirmed','approved')
     ORDER BY a.appointment_time ASC"
);
$stmt->execute([$date]);
$rows = $stmt->fetchAll();

$serviceNames = array_column($services, 'name', 'key');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Schedule for <?php echo date('F j, Y', strtotime($date)); ?> — <?php echo htmlspecialchars($parish['name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Jost:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{ --navy:#16223F; --gold:#C6A15B; --ink-soft:#5B534A; --line:#E3D8BF; --cream:#FAF6EC; }
  *{ box-sizing:border-box; }
  body{ font-family:'Jost',sans-serif; background: var(--cream); margin:0; padding:40px 24px; color:#241F1B; }
  .sheet{ max-width:760px; margin:0 auto; background:#fff; border:1px solid var(--line); border-radius:20px; padding:40px; }
  .s-head{ text-align:center; border-bottom:2px solid var(--gold); padding-bottom:18px; margin-bottom:22px; }
  .s-head h1{ font-family:'Cormorant Garamond',serif; font-size:22px; color:var(--navy); margin:6px 0 2px; }
  .s-head p{ font-size:11px; color:var(--ink-soft); margin:0; text-transform:uppercase; letter-spacing:1px; font-family:'IBM Plex Mono',monospace; }
  .s-title{ text-align:center; margin-bottom:24px; }
  .s-title span{ font-family:'IBM Plex Mono',monospace; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:var(--gold); }
  .s-title h2{ font-family:'Cormorant Garamond',serif; font-size:24px; margin:4px 0; }
  table{ width:100%; border-collapse:collapse; }
  th{ text-align:left; font-family:'IBM Plex Mono',monospace; font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color:var(--ink-soft); padding:10px 12px; border-bottom:2px solid var(--line); }
  td{ padding:12px; border-bottom:1px dashed var(--line); font-size:13.5px; }
  .empty{ text-align:center; padding:40px 0; color:var(--ink-soft); }
  .actions{ max-width:760px; margin:20px auto 0; display:flex; justify-content:center; gap:12px; }
  .actions button, .actions a{ font-family:'Jost',sans-serif; font-size:13.5px; padding:10px 22px; border-radius:999px; border:1px solid var(--navy); background: var(--navy); color:#fff; cursor:pointer; }
  .actions a.secondary{ background:#fff; color:var(--navy); }
  @media print{ body{ padding:0; background:#fff; } .actions{ display:none; } .sheet{ box-shadow:none; border:none; } }
</style>
</head>
<body>

<div class="sheet">
  <div class="s-head">
    <h1><?php echo htmlspecialchars($parish['name']); ?></h1>
    <p><?php echo htmlspecialchars($parish['diocese'] ?? ''); ?></p>
  </div>
  <div class="s-title">
    <span>Daily Schedule</span>
    <h2><?php echo date('l, F j, Y', strtotime($date)); ?></h2>
  </div>

  <?php if (empty($rows)): ?>
    <p class="empty">No confirmed appointments for this date.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Time</th><th>Service</th><th>Parishioner</th><th>Contact</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo $r['appointment_time'] ? date('g:i A', strtotime($r['appointment_time'])) : 'TBA'; ?></td>
            <td><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
            <td><?php echo htmlspecialchars($r['contact_number'] ?? '—'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="actions">
  <button type="button" onclick="window.print()">Print / Save as PDF</button>
  <a href="dashboard-secretary.php" class="secondary">← Back</a>
</div>

</body>
</html>