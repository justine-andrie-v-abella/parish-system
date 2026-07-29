-- ==========================================================================
-- Migration: Secretary appointment-management support
-- Run in phpMyAdmin (SQL tab, on the parish_system database) or:
--   mysql -u root parish_system < database/migration_add_secretary_fields.sql
-- ==========================================================================

ALTER TABLE appointments
  ADD COLUMN status_reason VARCHAR(255) NULL AFTER status,
  ADD COLUMN handled_by INT UNSIGNED NULL AFTER status_reason,
  ADD COLUMN handled_at TIMESTAMP NULL AFTER handled_by,
  ADD CONSTRAINT fk_appointments_handled_by FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL;