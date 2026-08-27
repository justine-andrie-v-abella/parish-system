<?php
// payment-return.php
require_once 'includes/config.php';
require_role(['parishioner']);
require_once 'includes/db.php';

$appointmentId = (int) ($_GET['appointment_id'] ?? 0);
$certificateId = (int) ($_GET['certificate_id'] ?? 0);
$isCert = $certificateId > 0;
$result = $_GET['result'] ?? '';
$uid = (int) $_SESSION['user_id'];

$appt = null;
if ($appointmentId) {
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND user_id = ?');
    $stmt->execute([$appointmentId, $uid]);
    $appt = $stmt->fetch();
} elseif ($certificateId) {
    $stmt = $pdo->prepare('SELECT * FROM certificate_requests WHERE id = ? AND user_id = ?');
    $stmt->execute([$certificateId, $uid]);
    $appt = $stmt->fetch();
}

$trackLink = $isCert ? 'certificates.php' : 'requests.php';
$trackLabel = $isCert ? 'Certificates' : 'View Requests';
$retryLink = $isCert ? 'certificates.php' : 'intentions.php';
$retryLabel = $isCert ? 'Certificates' : 'My Intentions';

$page_title = 'Payment Status — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="page-head" style="text-align:center; padding: 60px 20px;">
  <?php if ($result === 'success'): ?>
    <h1>Payment received!</h1>
    <p>Thank you — we're confirming your GCash payment now. This usually takes just a few seconds. You'll see the updated status under <a href="<?php echo $trackLink; ?>"><?php echo $trackLabel; ?></a>.</p>
  <?php elseif ($result === 'failed'): ?>
    <h1>Payment not completed</h1>
    <p>It looks like the GCash payment wasn't completed. You can try again from <a href="<?php echo $retryLink; ?>"><?php echo $retryLabel; ?></a>, or choose Cash instead.</p>
  <?php else: ?>
    <h1>Payment status unknown</h1>
    <p>Please check <a href="<?php echo $trackLink; ?>"><?php echo $trackLabel; ?></a> for the latest status of your request.</p>
  <?php endif; ?>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>
