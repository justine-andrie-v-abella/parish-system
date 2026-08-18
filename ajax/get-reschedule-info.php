<?php
// ajax/get-reschedule-info.php
require_once '../includes/config.php';
require_role(['parishioner', 'secretary', 'priest']);
require_once '../includes/db.php';

header('Content-Type: application/json');

$id  = (int) ($_GET['appointment_id'] ?? 0);
$uid = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

$stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
$stmt->execute([$id]);
$appt = $stmt->fetch();

if (!$appt) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found.']);
    exit;
}
if ($role === 'parishioner' && (int) $appt['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized.']);
    exit;
}

$serviceNames = array_column($services, 'name', 'key');

echo json_encode([
    'success' => true,
    'service_name' => $serviceNames[$appt['service_key']] ?? ucfirst($appt['service_key']),
    'current_date' => $appt['appointment_date'],
    'current_time' => $appt['appointment_time'],
    'reschedule_status' => $appt['reschedule_status'],
    'proposed_date' => $appt['proposed_date'],
    'proposed_time' => $appt['proposed_time'],
    'can_act' => $appt['reschedule_status'] === 'pending' && (int) $appt['proposed_by'] !== $uid,
]);