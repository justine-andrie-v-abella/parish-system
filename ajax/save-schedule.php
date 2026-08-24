<?php
// ajax/save-schedule.php
// Replaces all schedule rules for one service. Staff-only. Delete-and-reinsert
// keeps this simple and avoids diffing rule rows against what's already saved.
require_once '../includes/config.php';
require_role(['priest']);
require_once '../includes/db.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || empty($body['service_key'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing service_key.']);
    exit;
}

$serviceKey = trim($body['service_key']);
$rules = is_array($body['rules'] ?? null) ? $body['rules'] : [];

// Confirm the service actually exists before touching schedule rows for it.
$check = $pdo->prepare('SELECT 1 FROM services WHERE service_key = ?');
$check->execute([$serviceKey]);
if (!$check->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown service key. Save the service first.']);
    exit;
}

$validTypes = ['weekly', 'nth_weekday', 'conditional', 'by_arrangement', 'always_available'];

// Validate every rule before writing anything, so a bad rule can't leave
// a service with a half-saved schedule.
foreach ($rules as $r) {
    $type = $r['rule_type'] ?? '';
    if (!in_array($type, $validTypes, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid rule type: ' . $type]);
        exit;
    }
    if (in_array($type, ['weekly', 'nth_weekday'], true) && !isset($r['day_of_week']) || $r['day_of_week'] === null || $r['day_of_week'] === '') {
        if (in_array($type, ['weekly', 'nth_weekday'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Day of week is required for "' . $type . '" rules.']);
            exit;
        }
    }
    if ($type === 'nth_weekday' && empty($r['occurrences'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Occurrences (e.g. 1st & 3rd) are required for "nth_weekday" rules.']);
        exit;
    }
    if ($type === 'conditional' && (empty($r['trigger_event']) || !isset($r['offset_days']) || $r['offset_days'] === '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Trigger event and offset days are required for "conditional" rules.']);
        exit;
    }
    if (in_array($type, ['weekly', 'nth_weekday', 'conditional'], true) && empty($r['start_time'])) {
        http_response_code(400);
        echo json_encode(['error' => 'A time is required for "' . $type . '" rules.']);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $del = $pdo->prepare('DELETE FROM service_schedules WHERE service_key = ?');
    $del->execute([$serviceKey]);

    $ins = $pdo->prepare(
        'INSERT INTO service_schedules
            (service_key, rule_type, day_of_week, occurrences, trigger_event, offset_days, start_time, label, note, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $sort = 0;
    foreach ($rules as $r) {
        $type = $r['rule_type'];
        $isDowType   = in_array($type, ['weekly', 'nth_weekday'], true);
        $isNthType   = $type === 'nth_weekday';
        $isCondType  = $type === 'conditional';
        $isTimedType = in_array($type, ['weekly', 'nth_weekday', 'conditional'], true);
        $isArrType   = $type === 'by_arrangement';

        $ins->execute([
            $serviceKey,
            $type,
            $isDowType && $r['day_of_week'] !== '' ? $r['day_of_week'] : null,
            $isNthType ? ($r['occurrences'] ?: null) : null,
            $isCondType ? ($r['trigger_event'] ?: null) : null,
            $isCondType && ($r['offset_days'] ?? '') !== '' ? (int) $r['offset_days'] : null,
            $isTimedType ? ($r['start_time'] ?: null) : null,
            $r['label'] ?: null,
            $isArrType ? ($r['note'] ?: null) : null,
            $sort++,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'count' => count($rules)]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not save schedule. Please try again.']);
}