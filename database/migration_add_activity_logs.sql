-- ==========================================================================
-- Migration: activity_logs (real audit trail for the Priest/Admin dashboard)
-- Run in phpMyAdmin (SQL tab, on the parish_system database) or:
--   mysql -u root parish_system < database/migration_add_activity_logs.sql
-- ==========================================================================

CREATE TABLE IF NOT EXISTS activity_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NULL,           -- who did it (null-safe: user may be deleted later)
    action       VARCHAR(50)  NOT NULL,       -- e.g. 'login', 'appointment_approved', 'payment_verified'
    description  VARCHAR(255) NOT NULL,       -- human-readable summary shown in the timeline
    target_type  VARCHAR(30)  NULL,           -- e.g. 'appointment', 'user'
    target_id    INT UNSIGNED NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_created (created_at),
    INDEX idx_logs_user (user_id)
) ENGINE=InnoDB;