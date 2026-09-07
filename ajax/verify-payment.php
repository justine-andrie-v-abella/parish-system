<?php
require_once '../includes/config.php';
require_role(['treasurer']);
require_once '../includes/db.php';
require_once '../includes/payments.php';
require_once '../includes/logs.php';
require_once '../includes/notifications.php';

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

// 'appointment' (default) or 'donation' — both share identical payment
// columns by design (see migration_add_donations.sql), so this endpoint
// just targets the right table instead of duplicating itself.
$type = ($_POST['type'] ?? 'appointment') === 'donation' ? 'donation' : 'appointment';
$table = $type === 'donation' ? 'donations' : 'appointments';
$entityType = $type === 'donation' ? 'donation' : 'appointment';

$receiptNumber = trim((string) ($_POST['receipt_number'] ?? ''));
if ($receiptNumber === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a receipt number.']);
    exit;
}
if (mb_strlen($receiptNumber) > 50) {
    http_response_code(422);
    echo json_encode(['error' => 'Receipt number is too long.']);
    exit;
}

// The treasurer must explicitly confirm they checked the payment details
// before we mark this as paid. Without this, the checkbox in the UI is
// purely cosmetic and can be bypassed with a raw POST.
$confirmed = ($_POST['confirmed'] ?? '') === '1';
if (!$confirmed) {
    http_response_code(422);
    echo json_encode(['error' => 'Please confirm you have checked the payment details before verifying.']);
    exit;
}

$treasurerId = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? FOR UPDATE");
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

    // Manual receipt numbers must be unique across both appointments and
    // donations — they're issued from the same physical booklet.
    $dupeAppt = $pdo->prepare("SELECT id FROM appointments WHERE receipt_number = ? AND NOT (id = ? AND ? = 'appointment')");
    $dupeAppt->execute([$receiptNumber, $id, $type]);
    $dupeDonation = $pdo->prepare("SELECT id FROM donations WHERE receipt_number = ? AND NOT (id = ? AND ? = 'donation')");
    $dupeDonation->execute([$receiptNumber, $id, $type]);
    if ($dupeAppt->fetch() || $dupeDonation->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'That receipt number is already in use on another request.']);
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE {$table}
         SET payment_status = 'paid', verified_by = ?, verified_at = NOW(), receipt_number = ?, rejection_reason = NULL
         WHERE id = ?"
    );
    $update->execute([$treasurerId, $receiptNumber, $id]);

    if ($type === 'donation') {
        $svcLabel = 'donation';
        $message = "Thank you — your donation of ₱" . number_format($appt['amount']) . " has been verified. Receipt #{$receiptNumber} is ready.";
    } else {
        $serviceNames = array_column($services, 'name', 'key');
        $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
        $message = "Payment verified for your {$svcLabel} request. Receipt #{$receiptNumber} is ready.";
    }

    notify_user($pdo, $appt['user_id'], $message, 'payment', $type === 'appointment' ? $id : null);

    $pdo->commit();

    log_activity($pdo, $treasurerId, 'payment_verified',
        $_SESSION['full_name'] . " verified payment for {$svcLabel} request #{$id} (Receipt #{$receiptNumber}).",
        $entityType, $id);

    echo json_encode(['success' => true, 'receipt_number' => $receiptNumber]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong verifying this payment. Please try again.']);
}