<?php
// payment-return.php
require_once 'includes/config.php';
require_role(['parishioner']);
require_once 'includes/db.php';

$appointmentId = (int) ($_GET['appointment_id'] ?? 0);
$donationId    = (int) ($_GET['donation_id'] ?? 0);
$isDonation = $donationId > 0;
$result = $_GET['result'] ?? '';
$uid = (int) $_SESSION['user_id'];

$appt = null;
if ($appointmentId) {
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND user_id = ?');
    $stmt->execute([$appointmentId, $uid]);
    $appt = $stmt->fetch();
} elseif ($donationId) {
    $stmt = $pdo->prepare('SELECT * FROM donations WHERE id = ? AND user_id = ?');
    $stmt->execute([$donationId, $uid]);
    $appt = $stmt->fetch();
}

$trackLink = $isDonation ? 'intentions.php' : 'requests.php';
$trackLabel = $isDonation ? 'My Intentions' : 'View Requests';
$retryLink = 'intentions.php';
$retryLabel = $isDonation ? 'Donate' : 'My Intentions';

$page_title = 'Payment Status — ' . $parish['name'];
require_once 'includes/dashboard-header.php';
?>

<div class="page-head" style="text-align:center; padding: 60px 20px;">
  <?php if ($result === 'success'): ?>
    <h1><?php echo $isDonation ? 'Thank you for your donation!' : 'Payment received!'; ?></h1>
    <p>Thank you — we're confirming your GCash payment now. This usually takes just a few seconds. <?php echo $isDonation ? 'God bless you for your generosity.' : 'You\'ll see the updated status under <a href="' . $trackLink . '">' . $trackLabel . '</a>.'; ?></p>
  <?php elseif ($result === 'failed'): ?>
    <h1>Payment not completed</h1>
    <p>It looks like the GCash payment wasn't completed. You can try again from <a href="<?php echo $retryLink; ?>"><?php echo $retryLabel; ?></a>, or choose a different payment method instead.</p>
  <?php else: ?>
    <h1>Payment status unknown</h1>
    <p>Please check <a href="<?php echo $trackLink; ?>"><?php echo $trackLabel; ?></a> for the latest status.</p>
  <?php endif; ?>
</div>

<?php require_once 'includes/dashboard-footer.php'; ?>
