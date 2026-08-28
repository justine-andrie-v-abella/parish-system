<?php
// ajax/submit-payment.php
// The payment step for a sacrament appointment, now separated from booking
// itself — only usable once the secretary/priest has confirmed the
// uploaded documents (or the service had none to review in the first place).
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
$id            = (int) ($_POST['id'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? '';

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
if (!in_array($paymentMethod, ['cash', 'gcash'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a payment method.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND user_id = ? FOR UPDATE');
    $stmt->execute([$id, $uid]);
    $appt = $stmt->fetch();

    if (!$appt) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Request not found.']);
        exit;
    }
    if ($appt['documents_status'] !== 'verified') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'The parish office needs to confirm your documents before you can pay.']);
        exit;
    }
    if ($appt['payment_status'] !== 'unpaid' || $appt['payment_method'] !== null) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Payment has already been submitted for this request.']);
        exit;
    }

    $serviceFees = array_column($services, 'fee', 'key');
    $fee = (int) ($serviceFees[$appt['service_key']] ?? 0);

    if ($paymentMethod === 'gcash' && $fee <= 0) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['error' => 'This service has no fee — please choose Cash instead.']);
        exit;
    }

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);

    $checkoutUrl = null;

    if ($paymentMethod === 'gcash') {
        $successUrl = APP_URL . '/payment-return.php?appointment_id=' . $id . '&result=success';
        $failedUrl  = APP_URL . '/payment-return.php?appointment_id=' . $id . '&result=failed';

        $source = paymongo_create_gcash_source($fee * 100, $successUrl, $failedUrl); // centavos

        if (!$source || empty($source['id']) || empty($source['checkout_url'])) {
            $pdo->rollBack();
            http_response_code(502);
            echo json_encode(['error' => 'Could not connect to GCash right now. Please try again.']);
            exit;
        }

        $update = $pdo->prepare('UPDATE appointments SET payment_method = ?, paymongo_source_id = ? WHERE id = ?');
        $update->execute(['gcash', $source['id'], $id]);

        $checkoutUrl = $source['checkout_url'];
        $message = "Your {$svcLabel} request is awaiting GCash payment.";
    } else {
        $update = $pdo->prepare('UPDATE appointments SET payment_method = ? WHERE id = ?');
        $update->execute(['cash', $id]);

        $message = "Please settle payment in cash at the parish office for your {$svcLabel} request.";
    }

    notify_user($pdo, $uid, $message, 'payment', $id);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'checkout_url' => $checkoutUrl, // null for cash, present for gcash
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('submit-payment.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong submitting your payment. Please try again.']);
}
