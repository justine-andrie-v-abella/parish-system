<?php
// ajax/get-slots.php
// Returns bookable time slots for a given service + date, based on that
// service's rows in service_schedules. Replaces the old date-only,
// service-agnostic version.
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$serviceKey = $_GET['service_key'] ?? '';
$dateOfDeath = $_GET['date_of_death'] ?? ''; // only used for 'conditional' rules

if (!$date || !$serviceKey) {
    echo json_encode(['error' => 'Missing date or service.']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    echo json_encode(['error' => 'Invalid date.']);
    exit;
}

$schedulesReady = $pdo->query("SELECT to_regclass('public.service_schedules')")->fetchColumn() !== null;
if (!$schedulesReady) {
    echo json_encode(['error' => 'Scheduling isn\'t set up yet. Please contact the parish office.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM service_schedules WHERE service_key = ? AND is_active = true');
$stmt->execute([$serviceKey]);
$rules = $stmt->fetchAll();

if (!$rules) {
    echo json_encode(['error' => 'This service has no schedule configured yet. Please contact the parish office.']);
    exit;
}

// ---- by_arrangement: no fixed slots, front end shows a note instead ----
$byArrangement = array_values(array_filter($rules, fn($r) => $r['rule_type'] === 'by_arrangement'));
if ($byArrangement) {
    echo json_encode([
        'by_arrangement' => true,
        'note' => $byArrangement[0]['note'] ?: 'This service has no fixed schedule. The parish office will coordinate a date with you.',
    ]);
    exit;
}

// ---- always_available: any date works, no fixed time slot to pick ----
$alwaysAvailable = array_values(array_filter($rules, fn($r) => $r['rule_type'] === 'always_available'));
if ($alwaysAvailable) {
    echo json_encode([
        'always_available' => true,
        'message' => 'This service is available any day — no fixed time slot needed.',
    ]);
    exit;
}

// ---- conditional: date is derived from a trigger event + offset, not the picked date ----
$conditional = array_values(array_filter($rules, fn($r) => $r['rule_type'] === 'conditional'));
if ($conditional) {
    $rule = $conditional[0];
    if (!$dateOfDeath) {
        echo json_encode([
            'requires_trigger_date' => true,
            'trigger_event' => $rule['trigger_event'],
            'message' => 'Please provide the ' . str_replace('_', ' ', $rule['trigger_event']) . ' first.',
        ]);
        exit;
    }
    $triggerDt = DateTime::createFromFormat('Y-m-d', $dateOfDeath);
    if (!$triggerDt) {
        echo json_encode(['error' => 'Invalid trigger date.']);
        exit;
    }
    $computed = clone $triggerDt;
    $computed->modify('+' . (int) $rule['offset_days'] . ' days');
    $computedDate = $computed->format('Y-m-d');

    $bookedStmt = $pdo->prepare("SELECT appointment_time FROM appointments WHERE service_key = ? AND appointment_date = ? AND status NOT IN ('cancelled','rejected','no_show')");
    $bookedStmt->execute([$serviceKey, $computedDate]);
    $isTaken = in_array($rule['start_time'], array_column($bookedStmt->fetchAll(), 'appointment_time'));

    echo json_encode([
        'computed_date' => $computedDate,
        'slots' => [[
            'time' => substr($rule['start_time'], 0, 5),
            'label' => ($rule['label'] ?: 'Mass') . ' — ' . date('F j, Y', strtotime($computedDate)) . ' at ' . date('g:i A', strtotime($rule['start_time'])),
            'available' => !$isTaken,
        ]],
    ]);
    exit;
}

// ---- weekly / nth_weekday: check the picked date against day-of-week rules ----
$dow = (int) $dt->format('w');
$dayNum = (int) $dt->format('j');
$daysInMonth = (int) $dt->format('t');
$nth = (int) ceil($dayNum / 7);
$isLastOccurrence = ($dayNum + 7) > $daysInMonth;

$matches = [];
foreach ($rules as $r) {
    if ((int) $r['day_of_week'] !== $dow) continue;

    if ($r['rule_type'] === 'weekly') {
        $matches[] = $r;
    } elseif ($r['rule_type'] === 'nth_weekday') {
        $occ = array_map('trim', explode(',', (string) $r['occurrences']));
        if (in_array((string) $nth, $occ, true) || (in_array('last', $occ, true) && $isLastOccurrence)) {
            $matches[] = $r;
        }
    }
}

if (!$matches) {
    echo json_encode(['closed' => true, 'slots' => []]);
    exit;
}

$bookedStmt = $pdo->prepare("SELECT appointment_time FROM appointments WHERE service_key = ? AND appointment_date = ? AND status NOT IN ('cancelled','rejected','no_show')");
$bookedStmt->execute([$serviceKey, $date]);
$booked = array_column($bookedStmt->fetchAll(), 'appointment_time');

$slots = array_map(function ($r) use ($booked) {
    return [
        'time' => substr($r['start_time'], 0, 5),
        'label' => ($r['label'] ?: 'Available') . ' (' . date('g:i A', strtotime($r['start_time'])) . ')',
        'available' => !in_array($r['start_time'], $booked, true),
    ];
}, $matches);

echo json_encode(['slots' => $slots]);