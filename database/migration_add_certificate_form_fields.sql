-- ==========================================================================
-- Migration: dynamic, staff-editable form fields for certificate requests
-- Run in the Supabase SQL editor, AFTER migration_add_certificate_requests.sql.
--
-- Instead of hardcoding which input fields each certificate type collects
-- (registrant name, birth year, groom/bride names, etc.), the priest or
-- secretary now defines them per service in Catalog — exactly like the
-- existing Requirements list, but these are things the requestor types in
-- rather than documents they bring. A submitted request's answers are
-- stored as {label: value} JSON, keyed by the label text itself, so a
-- request stays self-describing even if the field list is edited later.
--
-- Written for Postgres/Supabase. Safe to run multiple times.
-- ==========================================================================

CREATE TABLE IF NOT EXISTS service_form_fields (
    id           BIGSERIAL PRIMARY KEY,
    service_key  VARCHAR(30)  NOT NULL REFERENCES services(service_key) ON DELETE CASCADE ON UPDATE CASCADE,
    field_label  VARCHAR(150) NOT NULL,
    sort_order   INT          NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_service_form_fields_service_key ON service_form_fields (service_key);

ALTER TABLE certificate_requests ADD COLUMN IF NOT EXISTS field_values JSONB NOT NULL DEFAULT '{}'::jsonb;

-- Seed the four certificate types with the fields from the parish's paper
-- forms. registrant_name/groom_name/bride_name/event_year (from
-- migration_add_certificate_requests.sql) are superseded by field_values
-- and no longer written to, but are left in place rather than dropped.
INSERT INTO service_form_fields (service_key, field_label, sort_order)
SELECT * FROM (VALUES
    ('cert_baptismal',    'Full Name of Registrant',   1),
    ('cert_baptismal',    'Birthday / Year of Birth',  2),
    ('cert_baptismal',    'Year of Baptism',           3),

    ('cert_confirmation', 'Full Name of Registrant',   1),
    ('cert_confirmation', 'Birthday / Year of Birth',  2),
    ('cert_confirmation', 'Year of Confirmation',      3),

    ('cert_death',        'Full Name of the Deceased', 1),
    ('cert_death',        'Year of Death',              2),

    ('cert_marriage',     'Groom''s Full Name',        1),
    ('cert_marriage',     'Bride''s Full Name',        2),
    ('cert_marriage',     'Year of Marriage',           3)
) AS v(service_key, field_label, sort_order)
WHERE NOT EXISTS (SELECT 1 FROM service_form_fields WHERE service_form_fields.service_key = v.service_key);
