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

$id      = (int) ($_POST['id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
if ($message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please specify what documents are needed.']);
    exit;
}
if (strlen($message) > 255) {
    $message = substr($message, 0, 255);
}

$secretaryId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
$stmt->execute([$id]);
$appt = $stmt->fetch();

if (!$appt) {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found.']);
    exit;
}

$serviceNames = array_column($services, 'name', 'key');
$svcLabel = $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']);
$fullMessage = "Please bring the following for your {$svcLabel} request: {$message}. Documents are submitted in person at the parish office.";

try {
    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
    $notify->execute([$appt['user_id'], $fullMessage, 'reminder']);

    $touch = $pdo->prepare('UPDATE appointments SET handled_by = ?, handled_at = NOW() WHERE id = ?');
    $touch->execute([$secretaryId, $id]);

    log_activity($pdo, $secretaryId, 'documents_requested',
        $_SESSION['full_name'] . " requested documents for {$svcLabel} request #{$id}: {$message}",
        'appointment', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong sending this reminder. Please try again.']);
}