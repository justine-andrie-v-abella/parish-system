-- ==========================================================================
-- Migration: remove the certificate-request feature entirely
-- Run in the Supabase SQL editor, AFTER backing up if you haven't already.
--
-- Certificates (baptismal/confirmation/marriage/death) are being dropped as
-- a feature. This migration snapshots the current certificate_requests data
-- into a timestamped backup table (cheap insurance — the feature itself is
-- gone for good, but the historical rows aren't lost outright), then drops
-- everything certificate-specific and folds the catalog's category set down
-- to just ('mass_intention', 'sacrament').
--
-- Written for Postgres/Supabase. NOT safe to run twice (the DROP TABLE
-- statements aren't IF-EXISTS-guarded on purpose, so a second run fails
-- loudly instead of silently doing nothing).
-- ==========================================================================

-- 1. Snapshot before deleting anything.
CREATE TABLE certificate_requests_backup_20260907 AS
SELECT * FROM certificate_requests;

-- 2. notifications.certificate_id FKs into certificate_requests(id) — the
--    column (and its FK) has to go before the table it references can be
--    dropped.
ALTER TABLE notifications DROP COLUMN IF EXISTS certificate_id;

-- 3. Drop the certificate-specific tables.
DROP TABLE service_form_fields;
DROP TABLE certificate_requests;

-- 4. Remove the seeded certificate services and their catalog rows (no
--    ON DELETE CASCADE on these FKs, so child rows must go first).
DELETE FROM service_requirements WHERE service_key IN ('cert_baptismal', 'cert_confirmation', 'cert_marriage', 'cert_death');
DELETE FROM service_fees        WHERE service_key IN ('cert_baptismal', 'cert_confirmation', 'cert_marriage', 'cert_death');
DELETE FROM service_schedules   WHERE service_key IN ('cert_baptismal', 'cert_confirmation', 'cert_marriage', 'cert_death');
DELETE FROM services            WHERE service_key IN ('cert_baptismal', 'cert_confirmation', 'cert_marriage', 'cert_death');

-- 5. Catalog categories become ('mass_intention', 'sacrament') instead of
--    ('sacrament', 'certificate').
ALTER TABLE services DROP CONSTRAINT IF EXISTS services_category_check;
ALTER TABLE services ADD CONSTRAINT services_category_check CHECK (category IN ('mass_intention', 'sacrament'));

-- 6. Move the existing Mass Intention service into its new category.
--    Everything else is already 'sacrament' (the pre-existing default).
UPDATE services SET category = 'mass_intention' WHERE service_key = 'intention';
