<?php
/**
 * includes/uploads.php
 * Shared handling for parishioner-uploaded document scans (appointment
 * requirements). Files are stored in a private Supabase Storage bucket —
 * see includes/supabase-storage.php and ajax/view-appointment-document.php
 * (the only place they're ever read back from).
 *
 * The booking form shows one upload slot per requirement line (e.g. baptism's
 * "Child's Birth Certificate", "Parents' Marriage Certificate", ...), posted
 * as req_doc_0, req_doc_1, ... in the same order the requirements are shown
 * in — one file per requirement, so it's unambiguous which document covers
 * which requirement, both for the parishioner and for whoever reviews them.
 */

require_once __DIR__ . '/supabase-storage.php';

define('APPOINTMENT_DOCS_MAX_BYTES', 5 * 1024 * 1024); // 5MB per file
define('APPOINTMENT_DOCS_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'pdf']);
define('APPOINTMENT_DOCS_MIME_TYPES', ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf']);

/**
 * Validates one $_FILES[...] entry (the ['name'=>, 'tmp_name'=>, 'error'=>,
 * 'size'=>] shape for a single, non-array file input). Returns
 * ['ok' => true, 'original_name' => ..., 'ext' => ..., 'tmp_name' => ...]
 * or ['ok' => false, 'error' => '...'].
 */
function validate_uploaded_file(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The file failed to upload. Please try again.'];
    }
    if ($file['size'] > APPOINTMENT_DOCS_MAX_BYTES) {
        return ['ok' => false, 'error' => 'The file must be 5MB or smaller.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, APPOINTMENT_DOCS_ALLOWED_EXT, true)) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, and PDF files are allowed.'];
    }
    return ['ok' => true, 'original_name' => $file['name'], 'ext' => $ext, 'tmp_name' => $file['tmp_name']];
}

/**
 * Validates and uploads one document per requirement label to Supabase
 * Storage, reading $_FILES['req_doc_0'], $_FILES['req_doc_1'], ... (index
 * matching the order of $requirementLabels). Every requirement must have a
 * file attached. Returns ['ok' => true, 'files' => [['path' => ...,
 * 'original_name' => ..., 'requirement_label' => ...], ...]] on success —
 * 'path' is the object's key within the bucket — or ['ok' => false, 'error'
 * => '...'] on the first problem found. Uploads nothing if any file fails
 * validation.
 */
function save_requirement_documents(array $requirementLabels): array {
    if (!$requirementLabels) {
        return ['ok' => true, 'files' => []];
    }

    if (!supabase_storage_configured()) {
        return ['ok' => false, 'error' => 'Document storage is not configured yet. Please contact the parish office.'];
    }

    $validated = [];
    foreach ($requirementLabels as $i => $label) {
        $fileKey = 'req_doc_' . $i;
        if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Please upload a document for "' . $label . '".'];
        }
        $result = validate_uploaded_file($_FILES[$fileKey]);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $label . ': ' . $result['error']];
        }
        $validated[] = ['tmp_name' => $result['tmp_name'], 'original_name' => $result['original_name'], 'ext' => $result['ext'], 'requirement_label' => $label];
    }

    $saved = [];
    foreach ($validated as $f) {
        $objectPath = bin2hex(random_bytes(16)) . '.' . $f['ext'];
        $contentType = APPOINTMENT_DOCS_MIME_TYPES[$f['ext']] ?? 'application/octet-stream';

        if (!supabase_storage_upload($objectPath, $f['tmp_name'], $contentType)) {
            // Best-effort cleanup of whatever already made it up, so a
            // partial submission doesn't leave orphaned files in the bucket.
            supabase_storage_delete(array_column($saved, 'path'));
            return ['ok' => false, 'error' => 'Could not upload one of the files. Please try again.'];
        }
        $saved[] = ['path' => $objectPath, 'original_name' => $f['original_name'], 'requirement_label' => $f['requirement_label']];
    }

    return ['ok' => true, 'files' => $saved];
}
