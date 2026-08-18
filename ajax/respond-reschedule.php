<?php
// ajax/respond-reschedule.php
require_once '../includes/config.php';
require_role(['parishioner', 'secretary', 'priest']);
require_once '../includes/db.php';
require_once '../includes/slots.php';
require_once '../includes/logs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$uid    = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'];

if ($id <= 0 || !in_array($action, ['accept', 'counter'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

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
    if ($appt['reschedule_status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'There is no pending reschedule proposal on this request.']);
        exit;
    }
    if ($role === 'parishioner' && (int) $appt['user_id'] !== $uid) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized.']);
        exit;
    }
    if ((int) $appt['proposed_by'] === $uid) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'You made this proposal — waiting on the other party to respond.']);
        exit;
    }

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);

    if ($action === 'accept') {
        $update = $pdo->prepare(
            "UPDATE appointments
             SET appointment_date = proposed_date, appointment_time = proposed_time,
                 proposed_date = NULL, proposed_time = NULL, reschedule_status = 'none', proposed_by = NULL
             WHERE id = ?"
        );
        $update->execute([$id]);

        $whoActed = $role === 'parishioner' ? $_SESSION['full_name'] : $_SESSION['full_name'] . ' (office)';
        $message = "{$whoActed} confirmed the new date for the {$svcLabel} request: "
            . date('F j, Y', strtotime($appt['proposed_date'])) . ' at ' . format_slot_label($appt['proposed_time']) . '.';

        $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type, appointment_id) VALUES (?, ?, ?, ?)');
        $notify->execute([$appt['proposed_by'], $message, 'schedule', $id]);

        $pdo->commit();
        log_activity($pdo, $uid, 'reschedule_accepted', "{$_SESSION['full_name']} confirmed the rescheduled {$svcLabel} request #{$id}.", 'appointment', $id);
        echo json_encode(['success' => true]);
        exit;
    }

    // action === 'counter'
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime(date('Y-m-d'))) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['error' => 'Please choose a valid, upcoming date.']);
        exit;
    }
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['error' => 'Please choose a time slot.']);
        exit;
    }

    $availability = get_slot_availability($pdo, $date, $id);
    if (!in_array($time, $availability['available'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'That time slot is no longer open. Please pick another.']);
        exit;
    }

    $update = $pdo->prepare("UPDATE appointments SET proposed_date = ?, proposed_time = ?, proposed_by = ? WHERE id = ?");
    $update->execute([$date, $time, $uid, $id]);

    $whoActed = $role === 'parishioner' ? $_SESSION['full_name'] : $_SESSION['full_name'] . ' from the office';
    $message = "{$whoActed} suggested a different date for the {$svcLabel} request: "
        . date('F j, Y', strtotime($date)) . ' at ' . format_slot_label($time) . '. Tap to confirm or suggest another date.';

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type, appointment_id) VALUES (?, ?, ?, ?)');
    $notify->execute([$appt['proposed_by'], $message, 'schedule', $id]);

    $pdo->commit();
    log_activity($pdo, $uid, 'reschedule_countered', "{$_SESSION['full_name']} countered with a new date for {$svcLabel} request #{$id}.", 'appointment', $id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('respond-reschedule.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}