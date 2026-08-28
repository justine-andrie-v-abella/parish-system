<?php
// ajax/request-certificate-documents.php
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

$staffId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM certificate_requests WHERE id = ?');
$stmt->execute([$id]);
$cert = $stmt->fetch();

if (!$cert) {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found.']);
    exit;
}

$serviceNames = array_column($services, 'name', 'key');
$svcLabel = $serviceNames[$cert['service_key']] ?? ucfirst($cert['service_key']);
$fullMessage = "Please bring the following for your {$svcLabel} request: {$message}. Documents are submitted in person at the parish office.";

try {
    notify_user($pdo, $cert['user_id'], $fullMessage, 'reminder', null, $id);

    $touch = $pdo->prepare('UPDATE certificate_requests SET handled_by = ?, handled_at = NOW() WHERE id = ?');
    $touch->execute([$staffId, $id]);

    log_activity($pdo, $staffId, 'certificate_documents_requested',
        $_SESSION['full_name'] . " requested documents for {$svcLabel} request #{$id}: {$message}",
        'certificate_request', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong sending this reminder. Please try again.']);
}
