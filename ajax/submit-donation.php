<?php
// ajax/submit-donation.php
// A donation isn't tied to any service/appointment — a logged-in
// parishioner gives a one-off amount for a general purpose. GCash is
// charged live through PayMongo (see includes/paymongo.php); Maya/PayPal/
// Card are recorded the same way Cash payments work elsewhere — the donor
// states intent, the treasurer verifies manually later (see donations.php).
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/paymongo.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$uid           = (int) $_SESSION['user_id'];
$donorName     = trim($_POST['donor_name'] ?? '');
$email         = trim($_POST['email'] ?? '');
$amount        = $_POST['amount'] ?? '';
$purpose       = $_POST['purpose'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$message       = trim($_POST['message'] ?? '');

$allowedPurposes = ['general', 'maintenance', 'charity', 'mass_activities'];
$allowedMethods  = ['gcash', 'maya', 'paypal', 'card'];

if ($donorName !== '' && mb_strlen($donorName) > 150) {
    $donorName = mb_substr($donorName, 0, 150);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}
if (!is_numeric($amount) || (int) $amount <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid donation amount.']);
    exit;
}
if (!in_array($purpose, $allowedPurposes, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid purpose.']);
    exit;
}
if (!in_array($paymentMethod, $allowedMethods, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a payment method.']);
    exit;
}
if (mb_strlen($message) > 500) {
    $message = mb_substr($message, 0, 500);
}
$amount = (int) $amount;

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        'INSERT INTO donations (user_id, donor_name, email, amount, purpose, payment_method, message)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$uid, $donorName ?: null, $email, $amount, $purpose, $paymentMethod, $message ?: null]);
    $donationId = (int) $pdo->lastInsertId();

    $checkoutUrl = null;

    if ($paymentMethod === 'gcash') {
        $successUrl = APP_URL . '/payment-return.php?donation_id=' . $donationId . '&result=success';
        $failedUrl  = APP_URL . '/payment-return.php?donation_id=' . $donationId . '&result=failed';

        $source = paymongo_create_gcash_source($amount * 100, $successUrl, $failedUrl); // centavos

        if (!$source || empty($source['id']) || empty($source['checkout_url'])) {
            $pdo->rollBack();
            http_response_code(502);
            echo json_encode(['error' => 'Could not connect to GCash right now. Please try again.']);
            exit;
        }

        $update = $pdo->prepare('UPDATE donations SET paymongo_source_id = ? WHERE id = ?');
        $update->execute([$source['id'], $donationId]);

        $checkoutUrl = $source['checkout_url'];
        $notifyMessage = "Your donation of ₱" . number_format($amount) . " is awaiting GCash payment.";
    } else {
        $methodLabels = ['maya' => 'Maya', 'paypal' => 'PayPal', 'card' => 'Credit/Debit Card'];
        $notifyMessage = "Thank you for your donation of ₱" . number_format($amount) . ". The parish office will reach out to confirm your {$methodLabels[$paymentMethod]} payment.";
    }

    notify_user($pdo, $uid, $notifyMessage, 'payment');

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $notifyMessage,
        'checkout_url' => $checkoutUrl, // null for maya/paypal/card, present for gcash
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('submit-donation.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong submitting your donation. Please try again.']);
}
