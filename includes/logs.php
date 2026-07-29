<?php
/**
 * includes/logs.php
 * One helper, used everywhere something log-worthy happens: logins,
 * registrations, appointment approvals/rejections/reschedules, payment
 * verifications. Powers the Priest/Admin's Recent Logs timeline.
 */

function log_activity(PDO $pdo, ?int $userId, string $action, string $description, ?string $targetType = null, ?int $targetId = null): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, description, target_type, target_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $description, $targetType, $targetId]);
    } catch (Exception $e) {
        // Logging must never break the action it's attached to.
    }
}