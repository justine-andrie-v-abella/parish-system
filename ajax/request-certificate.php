<?php
// ajax/request-certificate.php
// Certificate requests have no date/schedule to resolve (unlike
// ajax/book-appointment.php) — just requestor details plus whichever
// fields the priest/secretary has defined for this certificate type in
// Catalog (service_form_fields). The field list itself is always re-fetched
// from the DB here rather than trusted from the client, so a request's
// field_values can only ever be keyed by labels staff actually configured.
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/paymongo.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$uid            = (int) $_SESSION['user_id'];
$serviceKey     = $_POST['service_key'] ?? '';
$requestorName  = trim($_POST['requestor_name'] ?? '');
$notes          = trim($_POST['notes'] ?? '');
$paymentMethod  = $_POST['payment_method'] ?? '';

$certServices = array_column(array_filter($services, fn($s) => ($s['category'] ?? 'sacrament') === 'certificate'), 'key');
if (!in_array($serviceKey, $certServices, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid certificate type.']);
    exit;
}

if ($requestorName === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter the requestor\'s full name.']);
    exit;
}

if (!$pdo->query("SELECT to_regclass('public.service_form_fields')")->fetchColumn()) {
    http_response_code(500);
    echo json_encode(['error' => 'Certificate form fields are not set up yet. Please contact the parish office.']);
    exit;
}

$fieldStmt = $pdo->prepare('SELECT field_label FROM service_form_fields WHERE service_key = ? ORDER BY sort_order ASC, id ASC');
$fieldStmt->execute([$serviceKey]);
$fieldLabels = $fieldStmt->fetchAll(PDO::FETCH_COLUMN);

$fieldValues = [];
foreach ($fieldLabels as $i => $label) {
    $value = trim($_POST['field_' . $i] ?? '');
    if ($value === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Please fill in "' . $label . '".']);
        exit;
    }
    if (strlen($value) > 150) {
        $value = substr($value, 0, 150);
    }
    $fieldValues[$label] = $value;
}

if (strlen($notes) > 255) {
    $notes = substr($notes, 0, 255);
}

if (!in_array($paymentMethod, ['cash', 'gcash'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a payment method.']);
    exit;
}

$serviceFees = array_column($services, 'fee', 'key');
$fee = (int) ($serviceFees[$serviceKey] ?? 0);

if ($paymentMethod === 'gcash' && $fee <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'This request has no fee — please choose Cash instead.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        "INSERT INTO certificate_requests
            (user_id, service_key, requestor_name, field_values, notes, status, payment_status, payment_method)
         VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid', ?)"
    );
    $insert->execute([$uid, $serviceKey, $requestorName, json_encode($fieldValues), $notes ?: null, $paymentMethod]);
    $newId = (int) $pdo->lastInsertId();

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$serviceKey] ?? ucfirst($serviceKey);

    $checkoutUrl = null;

    if ($paymentMethod === 'gcash') {
        $successUrl = APP_URL . '/payment-return.php?certificate_id=' . $newId . '&result=success';
        $failedUrl  = APP_URL . '/payment-return.php?certificate_id=' . $newId . '&result=failed';

        $source = paymongo_create_gcash_source($fee * 100, $successUrl, $failedUrl); // centavos

        if (!$source || empty($source['id']) || empty($source['checkout_url'])) {
            $pdo->rollBack();
            http_response_code(502);
            echo json_encode(['error' => 'Could not connect to GCash right now. Please try again.']);
            exit;
        }

        $updateSource = $pdo->prepare('UPDATE certificate_requests SET paymongo_source_id = ? WHERE id = ?');
        $updateSource->execute([$source['id'], $newId]);

        $checkoutUrl = $source['checkout_url'];
        $message = "Your {$svcLabel} request is awaiting GCash payment.";
    } else {
        $message = "Your {$svcLabel} request has been submitted and is pending review. Please settle payment in cash at the parish office.";
    }

    notify_user($pdo, $uid, $message, 'announcement', null, $newId);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $newId,
        'message' => $message,
        'checkout_url' => $checkoutUrl, // null for cash, present for gcash
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('request-certificate.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong saving your request. Please try again.']);
}
