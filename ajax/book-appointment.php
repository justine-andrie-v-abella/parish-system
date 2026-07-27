<?php
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/slots.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$uid          = (int) $_SESSION['user_id'];
$serviceKey   = $_POST['service_key'] ?? '';
$date         = $_POST['appointment_date'] ?? '';
$time         = $_POST['appointment_time'] ?? '';
$notes        = trim($_POST['notes'] ?? '');

$validKeys = array_column($services, 'key');
if (!in_array($serviceKey, $validKeys, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid service.']);
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

if (strlen($notes) > 255) {
    $notes = substr($notes, 0, 255);
}

// Re-check availability server-side in case someone else grabbed the slot
// between the page loading and this submit (first-come-first-served).
$availability = get_slot_availability($pdo, $date);
if (!in_array($time, $availability['available'], true)) {
    http_response_code(409);
    echo json_encode(['error' => 'Sorry, that time slot was just taken. Please pick another.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock/re-verify inside the transaction to close the race window further.
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments
         WHERE appointment_date = ? AND appointment_time = ?
         AND status NOT IN ('cancelled','rejected') FOR UPDATE"
    );
    $check->execute([$date, $time]);
    if ((int) $check->fetchColumn() > 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Sorry, that time slot was just taken. Please pick another.']);
        exit;
    }

    $insert = $pdo->prepare(
        "INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, notes, status, payment_status)
         VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid')"
    );
    $insert->execute([$uid, $serviceKey, $date, $time, $notes ?: null]);
    $newId = (int) $pdo->lastInsertId();

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$serviceKey] ?? ucfirst($serviceKey);
    $message = "Your {$svcLabel} request for " . date('F j, Y', strtotime($date)) . ' at ' . format_slot_label($time) . ' has been submitted and is pending confirmation.';

    // Assumes notifications(user_id, message) with is_read/created_at defaults.
    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $notify->execute([$uid, $message]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $newId,
        'message' => $message,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong saving your request. Please try again.']);
}