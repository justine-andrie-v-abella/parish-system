<?php
/**
 * includes/notifications.php
 * Central place to insert a notification, optionally linked back to the
 * appointment it's about. assets/js/notifications.js uses that link to make
 * notifications clickable — jumping straight to the reschedule modal, the
 * payment step, or just the relevant request list.
 *
 * notifications.appointment_id already existed live before this file did
 * (it's what powers the reschedule-proposal notification click). This just
 * centralizes writing to it so every notification site doesn't have to
 * duplicate the same insert.
 */

function notify_user(PDO $pdo, int $userId, string $message, string $type = 'announcement', ?int $appointmentId = null): void {
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type, appointment_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $message, $type, $appointmentId]);
}
