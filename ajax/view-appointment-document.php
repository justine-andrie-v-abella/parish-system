<?php
// ajax/view-appointment-document.php
// Streams one uploaded document scan after confirming the requester is
// either the owning parishioner or parish staff. Files themselves live
// outside direct web access (see uploads/appointment_documents/.htaccess).
require_once '../includes/config.php';
require_role(['parishioner', 'secretary', 'priest']);
require_once '../includes/db.php';
require_once '../includes/uploads.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Invalid document.');
}

$stmt = $pdo->prepare(
    'SELECT d.*, a.user_id FROM appointment_documents d
     JOIN appointments a ON a.id = d.appointment_id
     WHERE d.id = ?'
);
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

$isOwner = $_SESSION['role'] === 'parishioner' && (int) $doc['user_id'] === (int) $_SESSION['user_id'];
$isStaff = in_array($_SESSION['role'], ['secretary', 'priest'], true);

if (!$isOwner && !$isStaff) {
    http_response_code(403);
    die('You do not have access to this document.');
}

$fullPath = APPOINTMENT_DOCS_DIR . '/' . basename($doc['file_path']);
if (!is_file($fullPath)) {
    http_response_code(404);
    die('Document file is missing.');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . basename($doc['original_name'] ?: $doc['file_path']) . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
