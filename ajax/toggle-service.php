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

$newState = $svc['is_active'] ? 0 : 1;
$update = $pdo->prepare('UPDATE services SET is_active = ? WHERE id = ?');
$update->execute([$newState, $id]);

log_activity(
    $pdo, (int) $_SESSION['user_id'],
    $newState ? 'service_activated' : 'service_deactivated',
    $_SESSION['full_name'] . ' ' . ($newState ? 'reactivated' : 'deactivated') . " the {$svc['name']} service.",
    'service', $id
);

echo json_encode(['success' => true, 'is_active' => $newState]);