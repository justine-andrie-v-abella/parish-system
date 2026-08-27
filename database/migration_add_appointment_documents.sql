-- ==========================================================================
-- Migration: document upload + review gate for sacrament appointments
-- Run in the Supabase SQL editor (Project → SQL Editor → New query).
--
-- Parishioners now upload scans of required documents when booking a
-- sacrament appointment; the secretary/priest reviews them before the
-- parishioner is allowed to pay. Certificate requests are unaffected —
-- they keep submitting and paying in one step.
--
-- Written for Postgres/Supabase. Safe to run multiple times.
-- ==========================================================================

-- Add the columns + grandfather existing appointments, but only the very
-- first time this runs — re-running this migration later must never flip
-- a genuinely new 'pending' (awaiting review) row to 'verified'.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'appointments' AND column_name = 'documents_status'
    ) THEN
        ALTER TABLE appointments ADD COLUMN documents_status VARCHAR(20) NOT NULL DEFAULT 'pending';
        ALTER TABLE appointments ADD COLUMN documents_reason VARCHAR(255);
        ALTER TABLE appointments ADD COLUMN documents_reviewed_by INT REFERENCES users(id);
        ALTER TABLE appointments ADD COLUMN documents_reviewed_at TIMESTAMP;

        -- Every appointment that already existed before this feature was
        -- already processed under the old flow — it shouldn't suddenly show
        -- up as "awaiting document review" or block an already-paid/confirmed
        -- request.
        UPDATE appointments SET documents_status = 'verified';
    END IF;
END $$;

ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_documents_status_check;
ALTER TABLE appointments ADD CONSTRAINT appointments_documents_status_check
    CHECK (documents_status IN ('pending', 'verified', 'resubmit_requested'));

CREATE TABLE IF NOT EXISTS appointment_documents (
    id              BIGSERIAL PRIMARY KEY,
    appointment_id  INT NOT NULL REFERENCES appointments(id) ON DELETE CASCADE,
    file_path       VARCHAR(255) NOT NULL,
    original_name   VARCHAR(255),
    uploaded_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_appointment_documents_appointment ON appointment_documents (appointment_id);
