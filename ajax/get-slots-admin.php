<?php
require_once '../includes/config.php';
require_role(['secretary']);
require_once '../includes/db.php';
require_once '../includes/slots.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$excludeId = isset($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid date.']);
    exit;
}

$possible = get_possible_slots($date);
if (empty($possible)) {
    echo json_encode(['closed' => true, 'slots' => []]);
    exit;
}

$availability = get_slot_availability($pdo, $date, $excludeId);

$slots = array_map(function ($time) use ($availability) {
    return [
        'time' => $time,
        'label' => format_slot_label($time),
        'available' => in_array($time, $availability['available'], true),
    ];
}, $possible);

echo json_encode(['closed' => false, 'slots' => $slots]);