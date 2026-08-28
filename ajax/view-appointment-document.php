<?php
// ajax/view-appointment-document.php
// Streams one uploaded document scan after confirming the requester is
// either the owning parishioner or parish staff. Files live in a private
// Supabase Storage bucket — this is the only place they're ever fetched
// back from it (see includes/supabase-storage.php).
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

$content = supabase_storage_download(basename($doc['file_path']));
if ($content === null) {
    http_response_code(404);
    die('Document file is missing.');
}

$ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
$contentType = APPOINTMENT_DOCS_MIME_TYPES[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . basename($doc['original_name'] ?: $doc['file_path']) . '"');
header('Content-Length: ' . strlen($content));
echo $content;
