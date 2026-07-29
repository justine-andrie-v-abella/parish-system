<?php
/**
 * includes/slots.php
 * Fixed appointment time-slot logic shared by get-slots.php and
 * book-appointment.php, so both always agree on what's bookable.
 *
 * Assumption: one appointment slot = one time block per day, shared
 * across all services (the parish can only host one scheduled
 * appointment at a time regardless of sacrament type). Adjust the
 * arrays below if you'd rather allow parallel bookings per service.
 */

/**
 * Returns the list of possible slot times (H:i:s) for a given date,
 * based on office hours. Sunday is closed for admin appointments
 * (reserved for Mass schedule).
 */
function get_possible_slots(string $dateStr): array
{
    $dow = (int) date('N', strtotime($dateStr)); // 1 = Mon ... 7 = Sun

    if ($dow === 7) {
        return []; // Sunday — no office appointments
    }

    if ($dow === 6) {
        // Saturday, 8:00 AM – 12:00 NN
        return ['08:00:00', '09:00:00', '10:00:00', '11:00:00'];
    }

    // Mon–Fri, 8:00 AM – 5:00 PM, lunch break 12–1
    return ['08:00:00', '09:00:00', '10:00:00', '11:00:00', '13:00:00', '14:00:00', '15:00:00', '16:00:00'];
}

/**
 * Returns [ 'available' => [...], 'taken' => [...] ] for a given date.
 * "Taken" = any appointment on that date whose status isn't
 * cancelled/rejected (pending counts as taken too, first-come-first-served).
 *
 * $excludeAppointmentId: when rescheduling an existing appointment, pass
 * its own id so its current slot doesn't count as "taken" against itself.
 * Optional and defaults to null, so existing callers are unaffected.
 */
function get_slot_availability(PDO $pdo, string $dateStr, ?int $excludeAppointmentId = null): array
{
    $possible = get_possible_slots($dateStr);
    if (empty($possible)) {
        return ['available' => [], 'taken' => []];
    }

    $sql = "SELECT appointment_time FROM appointments
            WHERE appointment_date = ? AND status NOT IN ('cancelled','rejected')
            AND appointment_time IS NOT NULL";
    $params = [$dateStr];
    if ($excludeAppointmentId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeAppointmentId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $taken = array_map(fn($r) => $r['appointment_time'], $stmt->fetchAll());

    $available = array_values(array_diff($possible, $taken));

    return ['available' => $available, 'taken' => array_values($taken)];
}

function format_slot_label(string $time): string
{
    return date('g:i A', strtotime($time));
}