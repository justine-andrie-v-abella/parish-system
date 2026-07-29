<?php
require_once '../includes/config.php';
require_role(['treasurer']);
require_once '../includes/db.php';
require_once '../includes/payments.php';
require_once '../includes/logs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$treasurerId = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $appt = $stmt->fetch();

    if (!$appt) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Request not found.']);
        exit;
    }
    if ($appt['payment_status'] !== 'unpaid') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This payment has already been ' . $appt['payment_status'] . '.']);
        exit;
    }

    $receiptNumber = generate_receipt_number($id);

    $update = $pdo->prepare(
        "UPDATE appointments
         SET payment_status = 'paid', verified_by = ?, verified_at = NOW(), receipt_number = ?, rejection_reason = NULL
         WHERE id = ?"
    );
    $update->execute([$treasurerId, $receiptNumber, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Payment verified for your {$svcLabel} request. Receipt #{$receiptNumber} is ready.";

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
    $notify->execute([$appt['user_id'], $message, 'payment']);

    $pdo->commit();

    log_activity($pdo, $treasurerId, 'payment_verified',
        $_SESSION['full_name'] . " verified payment for {$svcLabel} request #{$id} (Receipt #{$receiptNumber}).",
        'appointment', $id);

    echo json_encode(['success' => true, 'receipt_number' => $receiptNumber]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong verifying this payment. Please try again.']);
}