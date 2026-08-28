<?php
// ajax/verify-documents.php
require_once '../includes/config.php';
require_role(['secretary', 'priest']);
require_once '../includes/db.php';
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

$staffId = (int) $_SESSION['user_id'];

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
    if ($appt['documents_status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This request is not awaiting document review.']);
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE appointments
         SET documents_status = 'verified', documents_reason = NULL, documents_reviewed_by = ?, documents_reviewed_at = NOW()
         WHERE id = ?"
    );
    $update->execute([$staffId, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Your documents for the {$svcLabel} request have been confirmed. You may now proceed to payment.";

    notify_user($pdo, $appt['user_id'], $message, 'approved', $id);

    $pdo->commit();

    log_activity($pdo, $staffId, 'documents_verified',
        $_SESSION['full_name'] . " confirmed documents for {$svcLabel} request #{$id}.",
        'appointment', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong confirming these documents. Please try again.']);
}
