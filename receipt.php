<?php
require_once 'includes/config.php';
require_role(['treasurer', 'parishioner']);
require_once 'includes/db.php';
require_once 'includes/payments.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT a.*, u.full_name, u.email, v.full_name AS verified_by_name
     FROM appointments a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN users v ON v.id = a.verified_by
     WHERE a.id = ?"
);
$stmt->execute([$id]);
$appt = $stmt->fetch();

if (!$appt) {
    http_response_code(404);
    die('Receipt not found.');
}

// A parishioner may only view their own receipt.
if ($_SESSION['role'] === 'parishioner' && (int) $appt['user_id'] !== (int) $_SESSION['user_id']) {
    http_response_code(403);
    die('You do not have access to this receipt.');
}

if ($appt['payment_status'] !== 'paid') {
    http_response_code(404);
    die('No receipt is available for this request yet — payment has not been verified.');
}

$serviceNames = array_column($services, 'name', 'key');
$feeMap = get_fee_map($services);
$amount = payment_amount($appt['service_key'], $feeMap);
$svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?php echo htmlspecialchars($appt['receipt_number']); ?> — <?php echo htmlspecialchars($parish['name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Jost:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{ --navy:#16223F; --gold:#C6A15B; --ink-soft:#5B534A; --line:#E3D8BF; --cream:#FAF6EC; }
  *{ box-sizing:border-box; }
  body{ font-family:'Jost',sans-serif; background: var(--cream); margin:0; padding:40px 20px; color:#241F1B; }
  .receipt{ max-width:520px; margin:0 auto; background:#fff; border:1px solid var(--line); border-radius:20px; padding:40px; box-shadow:0 12px 32px -12px rgba(22,34,63,0.18); }
  .r-head{ text-align:center; border-bottom:2px solid var(--gold); padding-bottom:20px; margin-bottom:24px; }
  .r-head h1{ font-family:'Cormorant Garamond',serif; font-size:24px; color:var(--navy); margin:8px 0 2px; }
  .r-head p{ font-size:11px; color:var(--ink-soft); margin:0; text-transform:uppercase; letter-spacing:1px; font-family:'IBM Plex Mono',monospace; }
  .r-title{ text-align:center; margin-bottom:24px; }
  .r-title span{ font-family:'IBM Plex Mono',monospace; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:var(--gold); }
  .r-title h2{ font-family:'Cormorant Garamond',serif; font-size:20px; margin:4px 0; }
  .r-rows{ display:flex; flex-direction:column; gap:12px; margin-bottom:24px; }
  .r-row{ display:flex; justify-content:space-between; font-size:14px; padding-bottom:10px; border-bottom:1px dashed var(--line); }
  .r-row span:first-child{ color:var(--ink-soft); }
  .r-row span:last-child{ font-weight:500; color:var(--navy); }
  .r-amount{ text-align:center; background:#FBF6E9; border-radius:14px; padding:18px; margin-bottom:24px; }
  .r-amount .lbl{ font-family:'IBM Plex Mono',monospace; font-size:10px; letter-spacing:1.5px; text-transform:uppercase; color:var(--ink-soft); }
  .r-amount .val{ font-family:'Cormorant Garamond',serif; font-size:34px; font-weight:700; color:var(--navy); }
  .r-foot{ text-align:center; font-size:11.5px; color:var(--ink-soft); border-top:1px solid var(--line); padding-top:18px; }
  .actions{ max-width:520px; margin:20px auto 0; display:flex; justify-content:center; gap:12px; }
  .actions button, .actions a{
    font-family:'Jost',sans-serif; font-size:13.5px; padding:10px 22px; border-radius:999px; border:1px solid var(--navy);
    background: var(--navy); color:#fff; cursor:pointer;
  }
  .actions a.secondary{ background:#fff; color:var(--navy); }
  @media print{
    body{ padding:0; background:#fff; }
    .actions{ display:none; }
    .receipt{ box-shadow:none; border:none; }
  }
</style>
</head>
<body>

<div class="receipt">
  <div class="r-head">
    <h1><?php echo htmlspecialchars($parish['name']); ?></h1>
    <p><?php echo htmlspecialchars($parish['diocese'] ?? ''); ?></p>
  </div>

  <div class="r-title">
    <span>Official Receipt</span>
    <h2>#<?php echo htmlspecialchars($appt['receipt_number']); ?></h2>
  </div>

  <div class="r-rows">
    <div class="r-row"><span>Received From</span><span><?php echo htmlspecialchars($appt['full_name']); ?></span></div>
    <div class="r-row"><span>Service</span><span><?php echo htmlspecialchars($svcLabel); ?></span></div>
    <div class="r-row"><span>Appointment Date</span><span><?php echo date('F j, Y', strtotime($appt['appointment_date'])); ?></span></div>
    <div class="r-row"><span>Payment Method</span><span><?php echo strtoupper($appt['payment_method'] ?? '—'); ?></span></div>
    <?php if ($appt['reference_number']): ?>
      <div class="r-row"><span>Reference No.</span><span><?php echo htmlspecialchars($appt['reference_number']); ?></span></div>
    <?php endif; ?>
    <div class="r-row"><span>Date Verified</span><span><?php echo date('F j, Y g:i A', strtotime($appt['verified_at'])); ?></span></div>
    <div class="r-row"><span>Verified By</span><span><?php echo htmlspecialchars($appt['verified_by_name'] ?? '—'); ?></span></div>
  </div>

  <div class="r-amount">
    <div class="lbl">Amount Paid</div>
    <div class="val">₱<?php echo number_format($amount); ?></div>
  </div>

  <div class="r-foot">
    Thank you for your offering. This receipt confirms payment for the service listed above.<br>
    <?php echo htmlspecialchars($parish['address'] ?? ''); ?>
  </div>
</div>

<div class="actions">
  <button type="button" onclick="window.print()">Print / Save as PDF</button>
  <a href="<?php echo $_SESSION['role'] === 'treasurer' ? 'payments.php' : 'requests.php'; ?>" class="secondary">← Back</a>
</div>

</body>
</html>