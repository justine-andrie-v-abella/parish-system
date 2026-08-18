<?php
//ajax\book-appointment.php
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/slots.php';
require_once '../includes/paymongo.php'; // new helper, see below

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

if (!in_array($paymentMethod, ['cash', 'gcash'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a payment method.']);
    exit;
}

// Look up the fee for this service — needed to create the PayMongo Source amount.
$serviceFees = array_column($services, 'fee', 'key');
$fee = (int) ($serviceFees[$serviceKey] ?? 0);

if ($paymentMethod === 'gcash' && $fee <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'This service has no fee — please choose Cash instead.']);
    exit;
}

// Re-check availability server-side in case someone else grabbed the slot.
$availability = get_slot_availability($pdo, $date);
if (!in_array($time, $availability['available'], true)) {
    http_response_code(409);
    echo json_encode(['error' => 'Sorry, that time slot was just taken. Please pick another.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare(
        "SELECT id FROM appointments
        WHERE appointment_date = ? AND appointment_time = ?
        AND status NOT IN ('cancelled','rejected') FOR UPDATE"
    );
    $check->execute([$date, $time]);
    if ($check->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Sorry, that time slot was just taken. Please pick another.']);
        exit;
    }

    $insert = $pdo->prepare(
        "INSERT INTO appointments
            (user_id, service_key, appointment_date, appointment_time, notes, status, payment_status, payment_method)
         VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid', ?)"
    );
    $insert->execute([$uid, $serviceKey, $date, $time, $notes ?: null, $paymentMethod]);
    $newId = (int) $pdo->lastInsertId();

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$serviceKey] ?? ucfirst($serviceKey);

    $checkoutUrl = null;

    if ($paymentMethod === 'gcash') {
        // Create the PayMongo Source now, while inside the transaction is fine
        // since it's just an outbound API call — but we commit before redirecting.
        $successUrl = APP_URL . '/payment-return.php?appointment_id=' . $newId . '&result=success';
        $failedUrl  = APP_URL . '/payment-return.php?appointment_id=' . $newId . '&result=failed';

        $source = paymongo_create_gcash_source($fee * 100, $successUrl, $failedUrl); // centavos

        if (!$source || empty($source['id']) || empty($source['checkout_url'])) {
            $pdo->rollBack();
            http_response_code(502);
            echo json_encode(['error' => 'Could not connect to GCash right now. Please try again.']);
            exit;
        }

        $updateSource = $pdo->prepare('UPDATE appointments SET paymongo_source_id = ? WHERE id = ?');
        $updateSource->execute([$source['id'], $newId]);

        $checkoutUrl = $source['checkout_url'];
        $message = "Your {$svcLabel} request for " . date('F j, Y', strtotime($date)) . ' at ' . format_slot_label($time)
            . ' is awaiting GCash payment.';
    } else {
        $message = "Your {$svcLabel} request for " . date('F j, Y', strtotime($date)) . ' at ' . format_slot_label($time)
            . ' has been submitted and is pending confirmation. Please settle payment in cash at the parish office.';
    }

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $notify->execute([$uid, $message]);

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
    error_log('book-appointment.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong saving your request. Please try again.']);
}