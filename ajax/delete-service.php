<?php
require_once '../includes/config.php';
require_role(['priest', 'secretary']);
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

$stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
$stmt->execute([$id]);
$svc = $stmt->fetch();
if (!$svc) {
    http_response_code(404);
    echo json_encode(['error' => 'Service not found.']);
    exit;
}

$usageStmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE service_key = ?');
$usageStmt->execute([$svc['service_key']]);
$usage = (int) $usageStmt->fetchColumn();

if ($usage > 0) {
    http_response_code(409);
    echo json_encode(['error' => "This service has {$usage} appointment(s) on file and can't be deleted — deactivate it instead so history is preserved."]);
    exit;
}

// service_requirements cascades via FK ON DELETE CASCADE.
$delete = $pdo->prepare('DELETE FROM services WHERE id = ?');
$delete->execute([$id]);

log_activity($pdo, (int) $_SESSION['user_id'], 'service_deleted',
    $_SESSION['full_name'] . " deleted the {$svc['name']} service.", 'service', $id);

echo json_encode(['success' => true]);