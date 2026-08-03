<?php
require_once '../includes/config.php';
require_role(['secretary', 'priest']);
require_once '../includes/db.php';
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

$secretaryId = (int) $_SESSION['user_id'];

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
    if ($appt['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This request is already ' . $appt['status'] . '.']);
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE appointments SET status = 'confirmed', status_reason = NULL, handled_by = ?, handled_at = NOW() WHERE id = ?"
    );
    $update->execute([$secretaryId, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Your {$svcLabel} request for " . date('F j, Y', strtotime($appt['appointment_date']))
        . ' has been approved and confirmed by the parish office.';

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
    $notify->execute([$appt['user_id'], $message, 'approved']);

    $pdo->commit();

    log_activity($pdo, $secretaryId, 'appointment_approved',
        $_SESSION['full_name'] . " approved {$svcLabel} request #{$id}.",
        'appointment', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong approving this request. Please try again.']);
}