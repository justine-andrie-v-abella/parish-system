<?php
//parish-system\ajax\save-announcement.php
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
$uid = (int) $_SESSION['user_id'];

// Toggle-active is a distinct, simpler action (no title/body needed) —
// handled first so the hide/unhide buttons don't need to resend content.
if (($_POST['toggle_active'] ?? '') === '1') {
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid announcement.']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE announcements SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

$title = trim($_POST['title'] ?? '');
$body  = trim($_POST['body'] ?? '');

if ($title === '' || $body === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please fill in both the title and the message.']);
    exit;
}
if (mb_strlen($title) > 150) {
    http_response_code(422);
    echo json_encode(['error' => 'Title is too long (150 characters max).']);
    exit;
}

try {
    if ($id > 0) {
        $update = $pdo->prepare('UPDATE announcements SET title = ?, body = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$title, $body, $id]);
        $logAction = 'announcement_updated';
        $logMsg = $_SESSION['full_name'] . " updated the announcement \"{$title}\".";
    } else {
        $insert = $pdo->prepare('INSERT INTO announcements (title, body, created_by) VALUES (?, ?, ?)');
        $insert->execute([$title, $body, $uid]);
        $id = (int) $pdo->lastInsertId();
        $logAction = 'announcement_created';
        $logMsg = $_SESSION['full_name'] . " posted a new announcement: \"{$title}\".";
    }

    log_activity($pdo, $uid, $logAction, $logMsg, 'announcement', $id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong saving this announcement. Please try again.']);
}
