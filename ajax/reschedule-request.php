<?php
require_once '../includes/config.php';
require_role(['secretary']);
require_once '../includes/db.php';
require_once '../includes/slots.php';
require_once '../includes/logs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$id   = (int) ($_POST['id'] ?? 0);
$date = $_POST['appointment_date'] ?? '';
$time = $_POST['appointment_time'] ?? '';

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime(date('Y-m-d'))) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid, upcoming date.']);
    exit;
}
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a time slot.']);
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
    if (in_array($appt['status'], ['cancelled', 'rejected', 'completed'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This request is ' . $appt['status'] . ' and can no longer be rescheduled.']);
        exit;
    }

    // Re-check availability, excluding this appointment's own current slot.
    $availability = get_slot_availability($pdo, $date, $id);
    if (!in_array($time, $availability['available'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'That time slot is no longer open. Please pick another.']);
        exit;
    }

    $oldDate = $appt['appointment_date'];

    $update = $pdo->prepare(
        "UPDATE appointments SET appointment_date = ?, appointment_time = ?, handled_by = ?, handled_at = NOW() WHERE id = ?"
    );
    $update->execute([$date, $time, $secretaryId, $id]);

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
    $message = "Your {$svcLabel} request was moved from " . date('F j, Y', strtotime($oldDate))
        . ' to ' . date('F j, Y', strtotime($date)) . ' at ' . format_slot_label($time) . '.';

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
    $notify->execute([$appt['user_id'], $message, 'schedule']);

    $pdo->commit();

    log_activity($pdo, $secretaryId, 'appointment_rescheduled',
        $_SESSION['full_name'] . " rescheduled {$svcLabel} request #{$id} to " . date('M j, Y', strtotime($date)) . '.',
        'appointment', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong rescheduling this request. Please try again.']);
}