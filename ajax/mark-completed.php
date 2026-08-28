<?php
// C:\xampp\htdocs\parish-system\ajax\mark-completed.php
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
    if (!in_array($appt['status'], ['confirmed', 'approved'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Only confirmed appointments can be marked completed.']);
        exit;
    }
    // Don't let staff mark a future-dated appointment completed early —
    // the service hasn't happened yet.
    if ($appt['appointment_date'] > date('Y-m-d')) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This appointment date hasn\'t happened yet.']);
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE appointments SET status = 'completed', status_reason = NULL, handled_by = ?, handled_at = NOW() WHERE id = ?"
    );
    $update->execute([$staffId, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Your {$svcLabel} on " . date('F j, Y', strtotime($appt['appointment_date']))
        . ' has been marked as completed. Thank you.';

    notify_user($pdo, $appt['user_id'], $message, 'completed', $id);

    $pdo->commit();

    log_activity($pdo, $staffId, 'appointment_completed',
        $_SESSION['full_name'] . " marked {$svcLabel} request #{$id} as completed.",
        'appointment', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong marking this request completed. Please try again.']);
}