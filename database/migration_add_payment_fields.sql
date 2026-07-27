-- ==========================================================================
-- Migration: add payment details to appointments
-- Run in phpMyAdmin (SQL tab, on the parish_system database) or:
--   mysql -u root parish_system < database/migration_add_payment_fields.sql
--
-- Safe to run once. Re-running will error ("duplicate column") which is
-- harmless — it just means it's already applied.
-- ==========================================================================

ALTER TABLE appointments
  ADD COLUMN payment_method ENUM('cash','gcash') NULL AFTER payment_status,
  ADD COLUMN reference_number VARCHAR(50) NULL AFTER payment_method,
  ADD COLUMN payment_screenshot VARCHAR(255) NULL AFTER reference_number;