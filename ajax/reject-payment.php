<?php
require_once '../includes/config.php';
require_role(['treasurer']);
require_once '../includes/db.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
if ($reason === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please give a reason so the parishioner knows what to fix.']);
    exit;
}
if (strlen($reason) > 255) {
    $reason = substr($reason, 0, 255);
}

// 'appointment' (default) or 'certificate' — see verify-payment.php for why
// this endpoint branches on table name instead of duplicating itself.
$type = ($_POST['type'] ?? 'appointment') === 'certificate' ? 'certificate' : 'appointment';
$table = $type === 'certificate' ? 'certificate_requests' : 'appointments';

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

    $update = $pdo->prepare(
        "UPDATE {$table}
         SET payment_status = 'rejected', verified_by = ?, verified_at = NOW(), rejection_reason = ?
         WHERE id = ?"
    );
    $update->execute([$treasurerId, $reason, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Your payment for the {$svcLabel} request could not be verified: {$reason}. Please visit the parish office to resolve this.";

    notify_user($pdo, $appt['user_id'], $message, 'payment', $type === 'appointment' ? $id : null, $type === 'certificate' ? $id : null);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong rejecting this payment. Please try again.']);
}