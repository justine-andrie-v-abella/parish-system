<?php
// ajax/get-service-calendar.php
// Returns, for a given service + month, which calendar days are worth
// showing as pickable — used to render an actual calendar grid in the
// booking modal (instead of a bare native date input) so a parishioner can
// see at a glance which days a service is even offered on before picking one.
// Only meaningful for 'weekly'/'nth_weekday' schedules; by_arrangement,
// always_available and conditional services fall back to the plain date
// field the front end already has, so this only ever reports mode=calendar
// for the day-of-week-based case.
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/slots.php';

header('Content-Type: application/json');

$serviceKey = $_GET['service_key'] ?? '';
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
$month = max(1, min(12, $month));

if (!$serviceKey) {
    echo json_encode(['error' => 'Missing service.']);
    exit;
}

$schedulesReady = $pdo->query("SELECT to_regclass('public.service_schedules')")->fetchColumn() !== null;
if (!$schedulesReady) {
    echo json_encode(['mode' => 'unavailable', 'message' => "Scheduling isn't set up yet. Please contact the parish office."]);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM service_schedules WHERE service_key = ? AND is_active = true');
$stmt->execute([$serviceKey]);
$rules = $stmt->fetchAll();

if (!$rules) {
    echo json_encode(['mode' => 'unavailable', 'message' => 'This service has no schedule configured yet. Please contact the parish office.']);
    exit;
}

foreach ($rules as $r) {
    if ($r['rule_type'] === 'by_arrangement') {
        echo json_encode(['mode' => 'by_arrangement', 'note' => $r['note'] ?: 'This service has no fixed schedule. The parish office will coordinate a date with you.']);
        exit;
    }
    if ($r['rule_type'] === 'always_available') {
        echo json_encode(['mode' => 'always_available', 'message' => 'This service is available any day — no fixed time slot needed.']);
        exit;
    }
    if ($r['rule_type'] === 'conditional') {
        echo json_encode(['mode' => 'conditional']);
        exit;
    }
}

// weekly / nth_weekday — build the availability map for the requested month.
$firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth  = (int) date('t', strtotime($firstOfMonth));
$lastOfMonth  = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$today        = date('Y-m-d');

$bookedStmt = $pdo->prepare(
    "SELECT appointment_date, appointment_time FROM appointments
     WHERE service_key = ? AND appointment_date BETWEEN ? AND ?
     AND status NOT IN ('cancelled','rejected','no_show')"
);
$bookedStmt->execute([$serviceKey, $firstOfMonth, $lastOfMonth]);
$bookedByDate = [];
foreach ($bookedStmt->fetchAll() as $row) {
    $bookedByDate[$row['appointment_date']][] = $row['appointment_time'];
}

$days = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
    if ($date < $today) continue; // past days are never pickable

    $matched = match_dated_schedule_rules($rules, $date);
    if (!$matched) continue; // not offered this day at all — omitted, renders as closed

    $possibleTimes = array_unique(array_map(fn($r) => $r['start_time'], $matched));
    $booked = $bookedByDate[$date] ?? [];
    $openTimes = array_diff($possibleTimes, $booked);

    $days[$date] = empty($openTimes) ? 'full' : 'available';
}

echo json_encode(['mode' => 'calendar', 'days' => $days]);