<?php
// ajax/mark-notification-read.php
require_once '../includes/config.php';
require_role(['parishioner', 'secretary', 'priest', 'treasurer']);
require_once '../includes/db.php';

header('Content-Type: application/json');

$id  = (int) ($_POST['id'] ?? 0);
$uid = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $uid]);

echo json_encode(['success' => true]);