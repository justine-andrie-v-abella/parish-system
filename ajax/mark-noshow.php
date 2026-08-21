<?php
// C:\xampp\htdocs\parish-system\ajax\mark-noshow.php
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
        echo json_encode(['error' => 'Only confirmed appointments can be marked as no-show.']);
        exit;
    }
    if ($appt['appointment_date'] > date('Y-m-d')) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This appointment date hasn\'t happened yet.']);
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE appointments SET status = 'no_show', status_reason = 'Parishioner did not attend.', handled_by = ?, handled_at = NOW() WHERE id = ?"
    );
    $update->execute([$staffId, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Your {$svcLabel} appointment on " . date('F j, Y', strtotime($appt['appointment_date']))
        . ' was marked as a no-show. Please contact the parish office to rebook if this was in error.';

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
    $notify->execute([$appt['user_id'], $message, 'no_show']);

    $pdo->commit();

    log_activity($pdo, $staffId, 'appointment_no_show',
        $_SESSION['full_name'] . " marked {$svcLabel} request #{$id} as a no-show.",
        'appointment', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong marking this request as no-show. Please try again.']);
}