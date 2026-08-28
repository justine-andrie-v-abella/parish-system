-- ==========================================================================
-- Migration: link notifications to the request they're about
-- Run in the Supabase SQL editor.
--
-- notifications.appointment_id already exists live (added directly at some
-- point, undocumented in any checked-in migration until now — it's what
-- powers the existing "tap a reschedule notification to respond" feature
-- in assets/js/notifications.js). This migration documents it (idempotent,
-- so safe to run even though it's already there) and adds the certificate
-- equivalent, so certificate-request notifications can link back to their
-- row the same way.
--
-- Written for Postgres/Supabase. Safe to run multiple times.
-- ==========================================================================

ALTER TABLE notifications ADD COLUMN IF NOT EXISTS appointment_id INT REFERENCES appointments(id) ON DELETE SET NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS certificate_id INT REFERENCES certificate_requests(id) ON DELETE SET NULL;
