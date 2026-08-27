<?php
//ajax\cancel-certificate.php
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/logs.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$uid = (int) $_SESSION['user_id'];
$id  = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$check = $pdo->prepare('SELECT service_key FROM certificate_requests WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
$cert = $check->fetch();

// Only the owner can cancel, and only while it's still pending.
$stmt = $pdo->prepare("UPDATE certificate_requests SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
$stmt->execute([$id, $uid]);

if ($stmt->rowCount() === 0) {
    http_response_code(409);
    echo json_encode(['error' => 'This request can no longer be cancelled (it may already be approved or handled by the office).']);
    exit;
}

if ($cert) {
    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$cert['service_key']] ?? ucfirst($cert['service_key']);
    log_activity($pdo, $uid, 'certificate_cancelled',
        $_SESSION['full_name'] . " cancelled their {$svcLabel} request #{$id}.",
        'certificate_request', $id);
}

echo json_encode(['success' => true]);
