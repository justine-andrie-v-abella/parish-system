-- ==========================================================================
-- Migration: Donations
-- Run in the Supabase SQL editor.
--
-- A donation isn't tied to any service/appointment — a logged-in parishioner
-- gives an amount for a general purpose, not to book anything. Payment/
-- verification columns are named identically to appointments'/(the now-
-- removed) certificate_requests' equivalents on purpose, so
-- ajax/verify-payment.php and ajax/reject-payment.php can branch on table
-- name alone instead of duplicating their logic for a third time.
--
-- GCash donations are charged live through the existing PayMongo
-- integration (paymongo_source_id set, same as appointments). Maya/PayPal/
-- Card donations are recorded the same way Cash payments already work for
-- appointments: the donor states intent, the treasurer verifies manually —
-- no live processor integration for those three yet.
--
-- Written for Postgres/Supabase. Safe to run multiple times.
-- ==========================================================================

CREATE TABLE IF NOT EXISTS donations (
    id                  BIGSERIAL PRIMARY KEY,
    user_id             INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,

    donor_name          VARCHAR(150),   -- NULL/blank = "Anonymous" in the UI; the system still knows who submitted it via user_id
    email               VARCHAR(150) NOT NULL,
    amount              INT NOT NULL CHECK (amount > 0),
    purpose             VARCHAR(20) NOT NULL CHECK (purpose IN ('general', 'maintenance', 'charity', 'mass_activities')),
    message             VARCHAR(500),

    payment_method      VARCHAR(20) NOT NULL CHECK (payment_method IN ('gcash', 'maya', 'paypal', 'card')),
    payment_status      VARCHAR(20) NOT NULL DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid', 'paid', 'rejected')),
    reference_number    VARCHAR(50),
    payment_screenshot  VARCHAR(255),
    paymongo_source_id  VARCHAR(100),
    rejection_reason    VARCHAR(255),
    verified_by         INT REFERENCES users(id),
    verified_at         TIMESTAMPTZ,
    receipt_number      VARCHAR(30) UNIQUE,

    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_donations_user ON donations (user_id);
CREATE INDEX IF NOT EXISTS idx_donations_payment_status ON donations (payment_status);
CREATE INDEX IF NOT EXISTS idx_donations_paymongo_source ON donations (paymongo_source_id);
