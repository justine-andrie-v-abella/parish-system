<?php
//parish-system\ajax\delete-announcement.php
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
    echo json_encode(['error' => 'Invalid announcement.']);
    exit;
}

$stmt = $pdo->prepare('SELECT title FROM announcements WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Announcement not found.']);
    exit;
}

$del = $pdo->prepare('DELETE FROM announcements WHERE id = ?');
$del->execute([$id]);

log_activity($pdo, (int) $_SESSION['user_id'], 'announcement_deleted',
    $_SESSION['full_name'] . " deleted the announcement \"{$row['title']}\".", 'announcement', $id);

echo json_encode(['success' => true]);
