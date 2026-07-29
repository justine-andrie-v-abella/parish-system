<?php
require_once '../includes/config.php';
require_role(['secretary']);
require_once '../includes/db.php';
require_once '../includes/logs.php';

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
    echo json_encode(['error' => 'Please give a reason so the parishioner understands.']);
    exit;
}
if (strlen($reason) > 255) {
    $reason = substr($reason, 0, 255);
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
    if (!in_array($appt['status'], ['pending', 'confirmed', 'approved'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This request is already ' . $appt['status'] . '.']);
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE appointments SET status = 'rejected', status_reason = ?, handled_by = ?, handled_at = NOW() WHERE id = ?"
    );
    $update->execute([$reason, $secretaryId, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Your {$svcLabel} request for " . date('F j, Y', strtotime($appt['appointment_date']))
        . " could not be accommodated: {$reason}. Please contact the parish office.";

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
    $notify->execute([$appt['user_id'], $message, 'announcement']);

    $pdo->commit();

    log_activity($pdo, $secretaryId, 'appointment_rejected',
        $_SESSION['full_name'] . " rejected {$svcLabel} request #{$id}: {$reason}",
        'appointment', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong rejecting this request. Please try again.']);
}