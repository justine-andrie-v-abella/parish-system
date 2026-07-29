<?php
/**
 * export-queue.php
 * Two export formats, both without needing a PDF/DOCX library:
 *  - format=pdf : a clean print-friendly HTML page. The user hits
 *    "Print / Save as PDF" (browser-native), same pattern as receipt.php.
 *  - format=doc : Word-compatible HTML served with a .doc filename and
 *    application/msword content type. Microsoft Word (and most other
 *    word processors) open this natively — it's a real downloadable
 *    file, just not binary OOXML. If you later want true .docx/.pdf
 *    binaries, that needs a library (PHPWord / TCPDF) via Composer.
 */
require_once 'includes/config.php';
require_role(['secretary']);
require_once 'includes/db.php';

$format = $_GET['format'] ?? 'pdf';
if (!in_array($format, ['pdf', 'doc'], true)) {
    $format = 'pdf';
}

$allowedFilters = ['all', 'pending', 'confirmed', 'completed', 'cancelled', 'rejected'];
$filter = in_array($_GET['status'] ?? 'all', $allowedFilters, true) ? ($_GET['status'] ?? 'all') : 'all';
$q = trim($_GET['q'] ?? '');

$where = '1=1';
$params = [];
if ($filter !== 'all') {
    $where .= ' AND a.status = ?';
    $params[] = $filter;
}
if ($q !== '') {
    $where .= ' AND (u.full_name LIKE ? OR a.service_key LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$stmt = $pdo->prepare(
    "SELECT a.*, u.full_name FROM appointments a
     JOIN users u ON u.id = a.user_id
     WHERE $where
     ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 500"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$serviceNames = array_column($services, 'name', 'key');
$generatedAt = date('F j, Y g:i A');
$filterLabel = $filter === 'all' ? 'All Statuses' : ucfirst($filter);

if ($format === 'doc') {
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="appointment-queue-' . date('Y-m-d') . '.doc"');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Appointment Queue Export</title>
<style>
  body{ font-family: Calibri, Arial, sans-serif; color:#241F1B; margin: <?php echo $format === 'doc' ? '0' : '40px'; ?>; }
  h1{ font-size:20px; color:#16223F; margin-bottom:2px; }
  .meta{ font-size:11px; color:#5B534A; margin-bottom:18px; }
  table{ width:100%; border-collapse:collapse; font-size:12px; }
  th{ text-align:left; background:#F1E8D3; padding:8px 10px; border:1px solid #E3D8BF; }
  td{ padding:8px 10px; border:1px solid #E3D8BF; }
  .actions{ margin-top:20px; }
  @media print{ .actions{ display:none; } }
</style>
</head>
<body>

<h1><?php echo htmlspecialchars($parish['name']); ?> — Appointment Queue</h1>
<div class="meta">Filter: <?php echo htmlspecialchars($filterLabel); ?><?php echo $q ? ' · Search: "' . htmlspecialchars($q) . '"' : ''; ?> · Generated <?php echo $generatedAt; ?></div>

<table>
  <thead>
    <tr><th>Request</th><th>Parishioner</th><th>Service</th><th>Date</th><th>Time</th><th>Payment</th><th>Status</th></tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7">No requests match this filter.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td>#<?php echo $r['id']; ?></td>
        <td><?php echo htmlspecialchars($r['full_name']); ?></td>
        <td><?php echo htmlspecialchars($serviceNames[$r['service_key']] ?? ucfirst($r['service_key'])); ?></td>
        <td><?php echo date('M j, Y', strtotime($r['appointment_date'])); ?></td>
        <td><?php echo $r['appointment_time'] ? date('g:i A', strtotime($r['appointment_time'])) : '—'; ?></td>
        <td><?php echo ucfirst($r['payment_status']); ?></td>
        <td><?php echo ucfirst($r['status']); ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<?php if ($format === 'pdf'): ?>
<div class="actions">
  <button type="button" onclick="window.print()" style="font-family:Jost,sans-serif; font-size:13.5px; padding:10px 22px; border-radius:999px; border:1px solid #16223F; background:#16223F; color:#fff; cursor:pointer;">Print / Save as PDF</button>
</div>
<?php endif; ?>

</body>
</html>