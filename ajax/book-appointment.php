<?php
//ajax\book-appointment.php
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/paymongo.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$uid           = (int) $_SESSION['user_id'];
$serviceKey    = $_POST['service_key'] ?? '';
$date          = $_POST['appointment_date'] ?? '';
$time          = $_POST['appointment_time'] ?? ''; // '' is valid for by_arrangement / always_available
$dateOfDeath   = $_POST['date_of_death'] ?? '';     // only used for 'conditional' rules
$notes         = trim($_POST['notes'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? '';

$validKeys = array_column($services, 'key');
if (!in_array($serviceKey, $validKeys, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid service.']);
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

// ---------------------------------------------------------------
// Resolve & validate the booking against this service's schedule rules.
// The server never trusts the date/time the client computed — it
// re-derives and re-checks everything here, the same way get-slots.php
// does for display, so a tampered request can't slip through.
// ---------------------------------------------------------------
$schedulesReady = $pdo->query("SELECT to_regclass('public.service_schedules')")->fetchColumn() !== null;
if (!$schedulesReady) {
    http_response_code(422);
    echo json_encode(['error' => "Scheduling isn't set up yet. Please contact the parish office."]);
    exit;
}

$ruleStmt = $pdo->prepare('SELECT * FROM service_schedules WHERE service_key = ? AND is_active = true');
$ruleStmt->execute([$serviceKey]);
$rules = $ruleStmt->fetchAll();

if (!$rules) {
    http_response_code(422);
    echo json_encode(['error' => 'This service has no schedule configured yet. Please contact the parish office.']);
    exit;
}

$byArrangementRule = null;
$alwaysAvailableRule = null;
$conditionalRule = null;
$dowRules = []; // weekly + nth_weekday

foreach ($rules as $r) {
    if ($r['rule_type'] === 'by_arrangement') $byArrangementRule = $r;
    elseif ($r['rule_type'] === 'always_available') $alwaysAvailableRule = $r;
    elseif ($r['rule_type'] === 'conditional') $conditionalRule = $r;
    else $dowRules[] = $r;
}

function is_valid_upcoming_date(string $d): bool {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) >= strtotime(date('Y-m-d'));
}

$finalDate = null;
$finalTime = null; // null = no fixed time (by_arrangement / always_available)

if ($byArrangementRule || $alwaysAvailableRule) {
    if (!is_valid_upcoming_date($date)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please choose a valid, upcoming date.']);
        exit;
    }
    $finalDate = $date;
    $finalTime = null;

} elseif ($conditionalRule) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfDeath)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please provide the ' . str_replace('_', ' ', $conditionalRule['trigger_event']) . '.']);
        exit;
    }
    $triggerDt = DateTime::createFromFormat('Y-m-d', $dateOfDeath);
    if (!$triggerDt || $triggerDt->format('Y-m-d') !== $dateOfDeath) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid date provided.']);
        exit;
    }
    $computed = clone $triggerDt;
    $computed->modify('+' . (int) $conditionalRule['offset_days'] . ' days');
    $finalDate = $computed->format('Y-m-d');
    $finalTime = substr($conditionalRule['start_time'], 0, 5) . ':00';

} else {
    // weekly / nth_weekday — the submitted date + time must match a real rule.
    if (!is_valid_upcoming_date($date)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please choose a valid, upcoming date.']);
        exit;
    }
    // Accept both "HH:MM" (what get-slots.php sends to the browser) and
    // "HH:MM:SS", so the client's exact format never causes a false reject.
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please choose a time slot.']);
        exit;
    }
    $timeNormalized = strlen($time) === 5 ? $time . ':00' : $time;

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    $dow = (int) $dt->format('w');
    $dayNum = (int) $dt->format('j');
    $daysInMonth = (int) $dt->format('t');
    $nth = (int) ceil($dayNum / 7);
    $isLast = ($dayNum + 7) > $daysInMonth;

    $matched = false;
    foreach ($dowRules as $r) {
        if ((int) $r['day_of_week'] !== $dow) continue;
        if ((substr($r['start_time'], 0, 5) . ':00') !== $timeNormalized) continue;

        if ($r['rule_type'] === 'weekly') {
            $matched = true;
            break;
        }
        if ($r['rule_type'] === 'nth_weekday') {
            $occ = array_map('trim', explode(',', (string) $r['occurrences']));
            if (in_array((string) $nth, $occ, true) || (in_array('last', $occ, true) && $isLast)) {
                $matched = true;
                break;
            }
        }
    }

    if (!$matched) {
        http_response_code(422);
        echo json_encode(['error' => 'That date/time is no longer offered for this service. Please pick another.']);
        exit;
    }

    $finalDate = $date;
    $finalTime = $timeNormalized;
}

// Look up the fee for this service
$serviceFees = array_column($services, 'fee', 'key');
$fee = (int) ($serviceFees[$serviceKey] ?? 0);

if ($paymentMethod === 'gcash' && $fee <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'This service has no fee — please choose Cash instead.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Only fixed-time bookings (weekly/nth_weekday/conditional) can collide
    // on a single slot. by_arrangement/always_available have no single time
    // to conflict over.
    if ($finalTime !== null) {
        $check = $pdo->prepare(
            "SELECT id FROM appointments
            WHERE service_key = ? AND appointment_date = ? AND appointment_time = ?
            AND status NOT IN ('cancelled','rejected') FOR UPDATE"
        );
        $check->execute([$serviceKey, $finalDate, $finalTime]);
        if ($check->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Sorry, that time slot was just taken. Please pick another.']);
            exit;
        }
    }

    $insert = $pdo->prepare(
        "INSERT INTO appointments
            (user_id, service_key, appointment_date, appointment_time, notes, status, payment_status, payment_method)
         VALUES (?, ?, ?, ?, ?, 'pending', 'unpaid', ?)"
    );
    $insert->execute([$uid, $serviceKey, $finalDate, $finalTime, $notes ?: null, $paymentMethod]);
    $newId = (int) $pdo->lastInsertId();

    $serviceNames = array_column($services, 'name', 'key');
    $svcLabel = $serviceNames[$serviceKey] ?? ucfirst($serviceKey);

    $whenLabel = $finalTime
        ? date('F j, Y', strtotime($finalDate)) . ' at ' . date('g:i A', strtotime($finalTime))
        : date('F j, Y', strtotime($finalDate)) . ' (time to be arranged with the parish office)';

    $checkoutUrl = null;

    if ($paymentMethod === 'gcash') {
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
        $message = "Your {$svcLabel} request for {$whenLabel} is awaiting GCash payment.";
    } else {
        $message = "Your {$svcLabel} request for {$whenLabel} has been submitted and is pending confirmation. Please settle payment in cash at the parish office.";
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