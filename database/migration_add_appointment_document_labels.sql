-- ==========================================================================
-- Migration: label each uploaded document with which requirement it covers
-- Run in the Supabase SQL editor, AFTER migration_add_appointment_documents.sql.
--
-- The booking form now shows one upload slot per requirement line (instead
-- of one generic "attach several files" box), so each row in
-- appointment_documents can now say which requirement it was uploaded for.
--
-- Written for Postgres/Supabase. Safe to run multiple times.
-- ==========================================================================

ALTER TABLE appointment_documents ADD COLUMN IF NOT EXISTS requirement_label VARCHAR(255);
