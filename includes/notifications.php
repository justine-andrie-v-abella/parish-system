<?php
/**
 * includes/notifications.php
 * Central place to insert a notification, optionally linked back to the
 * appointment or certificate request it's about. assets/js/notifications.js
 * uses that link to make notifications clickable — jumping straight to the
 * reschedule modal, the payment step, or just the relevant request list.
 *
 * notifications.appointment_id already existed live before this file did
 * (it's what powers the reschedule-proposal notification click). This just
 * centralizes writing to it — and to the new certificate_id column — so
 * every notification site doesn't have to duplicate the column-existence
 * check itself.
 */

function notify_user(PDO $pdo, int $userId, string $message, string $type = 'announcement', ?int $appointmentId = null, ?int $certificateId = null): void {
    static $hasCertColumn = null;
    if ($hasCertColumn === null) {
        $hasCertColumn = $pdo->query(
            "SELECT 1 FROM information_schema.columns WHERE table_name = 'notifications' AND column_name = 'certificate_id'"
        )->fetchColumn() !== false;
    }

    if ($hasCertColumn) {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type, appointment_id, certificate_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $message, $type, $appointmentId, $certificateId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type, appointment_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $message, $type, $appointmentId]);
    }
}
