-- ==========================================================================
-- Migration: Treasurer payment verification support
-- Run in phpMyAdmin (SQL tab, on the parish_system database) or:
--   mysql -u root parish_system < database/migration_add_treasurer_fields.sql
--
-- Requires migration_add_payment_fields.sql to have already been applied
-- (payment_method / reference_number / payment_screenshot columns).
-- ==========================================================================

ALTER TABLE appointments
  MODIFY COLUMN payment_status ENUM('unpaid','paid','rejected') NOT NULL DEFAULT 'unpaid';

ALTER TABLE appointments
  ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER payment_screenshot,
  ADD COLUMN verified_by INT UNSIGNED NULL AFTER rejection_reason,
  ADD COLUMN verified_at TIMESTAMP NULL AFTER verified_by,
  ADD COLUMN receipt_number VARCHAR(30) NULL UNIQUE AFTER verified_at,
  ADD CONSTRAINT fk_appointments_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;