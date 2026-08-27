<?php
// ajax/resubmit-documents.php
// Parishioner re-uploads after the secretary/priest requested a
// resubmission — replaces the previous file set and sends it back into
// the review queue. Same one-slot-per-requirement shape as booking.
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/uploads.php';

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

$check = $pdo->prepare('SELECT service_key, documents_status FROM appointments WHERE id = ? AND user_id = ?');
$check->execute([$id, $uid]);
$apptCheck = $check->fetch();

if (!$apptCheck) {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found.']);
    exit;
}
if ($apptCheck['documents_status'] !== 'resubmit_requested') {
    http_response_code(409);
    echo json_encode(['error' => 'This request is not awaiting document resubmission.']);
    exit;
}

$reqList = $requirements[$apptCheck['service_key']] ?? [];

$docResult = save_requirement_documents($reqList);
if (!$docResult['ok']) {
    http_response_code(422);
    echo json_encode(['error' => $docResult['error']]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND user_id = ? FOR UPDATE');
    $stmt->execute([$id, $uid]);
    $appt = $stmt->fetch();

    if (!$appt || $appt['documents_status'] !== 'resubmit_requested') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This request is not awaiting document resubmission.']);
        exit;
    }

    // Old files are removed from disk before their rows are replaced, so
    // rejected scans don't pile up indefinitely.
    $oldFiles = $pdo->prepare('SELECT file_path FROM appointment_documents WHERE appointment_id = ?');
    $oldFiles->execute([$id]);
    $pathsToDelete = $oldFiles->fetchAll(PDO::FETCH_COLUMN);

    $del = $pdo->prepare('DELETE FROM appointment_documents WHERE appointment_id = ?');
    $del->execute([$id]);

    $hasReqLabel = $pdo->query(
        "SELECT 1 FROM information_schema.columns WHERE table_name = 'appointment_documents' AND column_name = 'requirement_label'"
    )->fetchColumn() !== false;

    if ($hasReqLabel) {
        $insDoc = $pdo->prepare('INSERT INTO appointment_documents (appointment_id, file_path, original_name, requirement_label) VALUES (?, ?, ?, ?)');
        foreach ($docResult['files'] as $f) {
            $insDoc->execute([$id, $f['path'], $f['original_name'], $f['requirement_label']]);
        }
    } else {
        $insDoc = $pdo->prepare('INSERT INTO appointment_documents (appointment_id, file_path, original_name) VALUES (?, ?, ?)');
        foreach ($docResult['files'] as $f) {
            $insDoc->execute([$id, $f['path'], $f['original_name']]);
        }
    }

    $update = $pdo->prepare(
        "UPDATE appointments SET documents_status = 'pending', documents_reason = NULL WHERE id = ?"
    );
    $update->execute([$id]);

    $pdo->commit();

    foreach ($pathsToDelete as $path) {
        $fullPath = APPOINTMENT_DOCS_DIR . '/' . basename($path);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('resubmit-documents.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong resubmitting your documents. Please try again.']);
}
