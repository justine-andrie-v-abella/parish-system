<?php
//ajax\book-appointment.php
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/slots.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$uid          = (int) $_SESSION['user_id'];
$serviceKey   = $_POST['service_key'] ?? '';
$date         = $_POST['appointment_date'] ?? '';
$time         = $_POST['appointment_time'] ?? '';
$notes        = trim($_POST['notes'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? '';
$referenceNumber = trim($_POST['reference_number'] ?? '');

$validKeys = array_column($services, 'key');
if (!in_array($serviceKey, $validKeys, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid service.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime(date('Y-m-d'))) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid, upcoming date.']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a time slot.']);
    exit;
}

if (strlen($notes) > 255) {
    $notes = substr($notes, 0, 255);
}

// ---------------- Payment method validation ----------------
if (!in_array($paymentMethod, ['cash', 'gcash'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a payment method.']);
    exit;
}

$screenshotRelativePath = null;

if ($paymentMethod === 'gcash') {
    if ($referenceNumber === '' || strlen($referenceNumber) > 50) {
        http_response_code(422);
        echo json_encode(['error' => 'Please enter a valid GCash reference number.']);
        exit;
    }
    // Reference numbers are digits/letters only in practice — keep it loose but sane.
    if (!preg_match('/^[A-Za-z0-9\-]{4,50}$/', $referenceNumber)) {
        http_response_code(422);
        echo json_encode(['error' => 'Reference number should only contain letters, numbers, and dashes.']);
        exit;
    }

    if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] === UPLOAD_ERR_NO_FILE) {
        http_response_code(422);
        echo json_encode(['error' => 'Please upload a screenshot of your GCash payment.']);
        exit;
    }

    $file = $_FILES['screenshot'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['error' => 'There was a problem uploading your screenshot. Please try again.']);
        exit;
    }

    $MAX_BYTES = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $MAX_BYTES) {
        http_response_code(422);
        echo json_encode(['error' => 'Screenshot is too large. Please upload an image under 5MB.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowedMimes[$mime])) {
        http_response_code(422);
        echo json_encode(['error' => 'Screenshot must be a JPG, PNG, or WEBP image.']);
        exit;
    }
    $ext = $allowedMimes[$mime];

    $uploadDir = __DIR__ . '/../uploads/payments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    // Lock down the folder: no script execution, ever, regardless of file content.
    $htaccessPath = $uploadDir . '.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "php_flag engine off\nAddHandler cgi-script .php .php3 .php4 .php5 .phtml\nOptions -ExecCGI\n");
    }

    $filename = 'gcash_' . $uid . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save your screenshot. Please try again.']);
        exit;
    }

    $screenshotRelativePath = 'uploads/payments/' . $filename;
} else {
    // Cash: no reference number or screenshot expected.
    $referenceNumber = null;
}

// Re-check availability server-side in case someone else grabbed the slot
// between the page loading and this submit (first-come-first-served).
$availability = get_slot_availability($pdo, $date);
if (!in_array($time, $availability['available'], true)) {
    if ($screenshotRelativePath) {
        @unlink(__DIR__ . '/../' . $screenshotRelativePath);
    }
    http_response_code(409);
    echo json_encode(['error' => 'Sorry, that time slot was just taken. Please pick another.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock/re-verify inside the transaction to close the race window further.
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments
         WHERE appointment_date = ? AND appointment_time = ?
         AND status NOT IN ('cancelled','rejected') FOR UPDATE"
    );
    $check->execute([$date, $time]);
    if ((int) $check->fetchColumn() > 0) {
        $pdo->rollBack();
        if ($screenshotRelativePath) {
            @unlink(__DIR__ . '/../' . $screenshotRelativePath);
        }
        http_response_code(409);
        echo json_encode(['error' => 'Sorry, that time slot was just taken. Please pick another.']);
        exit;
    }

    $insert = $pdo->prepare(
        "INSERT INTO appointments
            (user_id, service_key, appointment_date, appointment_time, notes, status, payment_status, payment_method, reference_number, payment_screenshot)
         VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid', ?, ?, ?)"
    );
    $insert->execute([$uid, $serviceKey, $date, $time, $notes ?: null, $paymentMethod, $referenceNumber, $screenshotRelativePath]);
    $newId = (int) $pdo->lastInsertId();

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$serviceKey] ?? ucfirst($serviceKey);
    $paymentNote = $paymentMethod === 'gcash'
        ? ' Your GCash payment is being verified by the Treasurer.'
        : ' Please settle payment in cash at the parish office.';
    $message = "Your {$svcLabel} request for " . date('F j, Y', strtotime($date)) . ' at ' . format_slot_label($time)
        . ' has been submitted and is pending confirmation.' . $paymentNote;

    // Assumes notifications(user_id, message) with is_read/created_at defaults.
    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $notify->execute([$uid, $message]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $newId,
        'message' => $message,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($screenshotRelativePath) {
        @unlink(__DIR__ . '/../' . $screenshotRelativePath);
    }
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong saving your request. Please try again.']);
}