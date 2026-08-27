<?php
/**
 * includes/uploads.php
 * Shared handling for parishioner-uploaded document scans (appointment
 * requirements). Files are stored outside direct web access — see
 * uploads/appointment_documents/.htaccess and ajax/view-appointment-document.php.
 *
 * The booking form shows one upload slot per requirement line (e.g. baptism's
 * "Child's Birth Certificate", "Parents' Marriage Certificate", ...), posted
 * as req_doc_0, req_doc_1, ... in the same order the requirements are shown
 * in — one file per requirement, so it's unambiguous which document covers
 * which requirement, both for the parishioner and for whoever reviews them.
 */

define('APPOINTMENT_DOCS_DIR', __DIR__ . '/../uploads/appointment_documents');
define('APPOINTMENT_DOCS_MAX_BYTES', 5 * 1024 * 1024); // 5MB per file
define('APPOINTMENT_DOCS_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'pdf']);

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
 * Validates and saves one uploaded document per requirement label, reading
 * $_FILES['req_doc_0'], $_FILES['req_doc_1'], ... (index matching the order
 * of $requirementLabels). Every requirement must have a file attached.
 * Returns ['ok' => true, 'files' => [['path' => ..., 'original_name' => ...,
 * 'requirement_label' => ...], ...]] on success, or ['ok' => false, 'error'
 * => '...'] on the first problem found. Saves nothing to disk if any file
 * fails validation.
 */
function save_requirement_documents(array $requirementLabels): array {
    if (!$requirementLabels) {
        return ['ok' => true, 'files' => []];
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

    if (!is_dir(APPOINTMENT_DOCS_DIR)) {
        mkdir(APPOINTMENT_DOCS_DIR, 0755, true);
    }

    $saved = [];
    foreach ($validated as $f) {
        $randomName = bin2hex(random_bytes(16)) . '.' . $f['ext'];
        $destPath = APPOINTMENT_DOCS_DIR . '/' . $randomName;
        if (!move_uploaded_file($f['tmp_name'], $destPath)) {
            return ['ok' => false, 'error' => 'Could not save one of the uploaded files. Please try again.'];
        }
        $saved[] = ['path' => $randomName, 'original_name' => $f['original_name'], 'requirement_label' => $f['requirement_label']];
    }

    return ['ok' => true, 'files' => $saved];
}
