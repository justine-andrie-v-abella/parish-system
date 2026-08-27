-- ==========================================================================
-- Migration: Certificate Requests
-- Run in the Supabase SQL editor (Project → SQL Editor → New query).
--
-- Certificates (Baptismal, Confirmation, Death, Marriage) are document
-- requests, not scheduled appointments — no date/time, just a requestor,
-- a registrant (or a couple, for marriage), and a year. They get their
-- own table instead of overloading `appointments` (which has several
-- date-gated rules — see ajax/approve-request.php, ajax/mark-completed.php
-- — that don't make sense without a real appointment date).
--
-- Written for Postgres/Supabase. Safe to run multiple times.
-- ==========================================================================

-- 1. Tell certificate services apart from bookable sacraments in the
--    existing catalog, so intentions.php / index.php can exclude them
--    from the date-driven booking flow.
ALTER TABLE services ADD COLUMN IF NOT EXISTS category VARCHAR(20) NOT NULL DEFAULT 'sacrament';

ALTER TABLE services DROP CONSTRAINT IF EXISTS services_category_check;
ALTER TABLE services ADD CONSTRAINT services_category_check CHECK (category IN ('sacrament', 'certificate'));

-- 2. Seed the four certificate types. Fee is a flat ₱100 each (no
--    itemized fee lines needed here — see migration_add_service_fees.sql's
--    original design note). Requirements are left empty for the
--    priest/secretary to fill in via catalog.php.
INSERT INTO services (service_key, icon, name, description, fee, category, sort_order)
SELECT * FROM (VALUES
    ('cert_baptismal',    'dove',  'Baptismal Certificate',    'A certified copy of a baptismal record.',  100, 'certificate', 100),
    ('cert_confirmation', 'flame', 'Confirmation Certificate', 'A certified copy of a confirmation record.', 100, 'certificate', 101),
    ('cert_marriage',     'rings', 'Marriage Certificate',     'A certified copy of a marriage record.',    100, 'certificate', 102),
    ('cert_death',        'cross', 'Death Certificate',        'A certified copy of a death/burial record.', 100, 'certificate', 103)
) AS v(service_key, icon, name, description, fee, category, sort_order)
WHERE NOT EXISTS (SELECT 1 FROM services WHERE services.service_key = v.service_key);

-- 3. Certificate requests themselves. Payment/approval columns are named
--    identically to their `appointments` counterparts on purpose, so the
--    treasurer-side verification code can branch on table name alone
--    instead of duplicating its logic.
CREATE TABLE IF NOT EXISTS certificate_requests (
    id                  BIGSERIAL PRIMARY KEY,
    user_id             INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_key         VARCHAR(30) NOT NULL REFERENCES services(service_key),

    requestor_name      VARCHAR(150) NOT NULL,
    registrant_name     VARCHAR(150),   -- baptismal / confirmation / death
    groom_name          VARCHAR(150),   -- marriage only
    bride_name          VARCHAR(150),   -- marriage only
    event_year          INT NOT NULL,
    notes               VARCHAR(255),

    status              VARCHAR(20) NOT NULL DEFAULT 'pending',
    status_reason       VARCHAR(255),
    handled_by          INT REFERENCES users(id),
    handled_at          TIMESTAMP,

    payment_status      VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    payment_method      VARCHAR(20),
    reference_number    VARCHAR(50),
    payment_screenshot  VARCHAR(255),
    paymongo_source_id  VARCHAR(100),
    rejection_reason    VARCHAR(255),
    verified_by         INT REFERENCES users(id),
    verified_at         TIMESTAMP,
    receipt_number      VARCHAR(30) UNIQUE,

    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_certificate_requests_user ON certificate_requests (user_id);
CREATE INDEX IF NOT EXISTS idx_certificate_requests_status ON certificate_requests (status);
CREATE INDEX IF NOT EXISTS idx_certificate_requests_paymongo_source ON certificate_requests (paymongo_source_id);
