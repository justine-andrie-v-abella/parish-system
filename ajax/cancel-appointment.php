<?php
//ajax\cancel-appointment.php
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';

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

// Only the owner can cancel, and only while it's still pending.
$stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
$stmt->execute([$id, $uid]);

if ($stmt->rowCount() === 0) {
    http_response_code(409);
    echo json_encode(['error' => 'This request can no longer be cancelled (it may already be confirmed or handled by the office).']);
    exit;
}

echo json_encode(['success' => true]);