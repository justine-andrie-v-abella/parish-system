<?php
// ajax/get-appointment-documents.php
require_once '../includes/config.php';
require_role(['secretary', 'priest']);
require_once '../includes/db.php';

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$hasReqLabel = $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_name = 'appointment_documents' AND column_name = 'requirement_label'"
)->fetchColumn() !== false;
$labelSelect = $hasReqLabel ? ', requirement_label' : '';

$stmt = $pdo->prepare("SELECT id, original_name{$labelSelect} FROM appointment_documents WHERE appointment_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$docs = $stmt->fetchAll();

echo json_encode(['success' => true, 'documents' => $docs]);
