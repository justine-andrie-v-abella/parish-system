<?php
//ajax\get-slots.php
require_once '../includes/config.php';
require_role(['parishioner']);
require_once '../includes/db.php';
require_once '../includes/slots.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date.']);
    exit;
}

if (strtotime($date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['available' => [], 'taken' => [], 'message' => 'That date has already passed.']);
    exit;
}

$result = get_slot_availability($pdo, $date);

$slots = [];
foreach (get_possible_slots($date) as $time) {
    $slots[] = [
        'time'      => $time,
        'label'     => format_slot_label($time),
        'available' => in_array($time, $result['available'], true),
    ];
}

echo json_encode([
    'date'  => $date,
    'slots' => $slots,
    'closed' => empty(get_possible_slots($date)),
]);