-- ==========================================================================
-- Migration: itemized service fees (Fees/Catalog Management)
-- Run in the Supabase SQL editor (Project → SQL Editor → New query).
--
-- A single service (e.g. Baptism) can have several separate fee lines
-- instead of one flat number — e.g. "Sponsors ₱100/head (first 2 free)"
-- and "Prejordan card ₱30/head". This table stores those lines.
--
-- The existing services.fee column is left in place as an optional
-- flat/base fee (used as-is by services like certificate requests that
-- really do have just one price). Leave it at 0 for services that are
-- fully itemized here instead.
--
-- Written for Postgres/Supabase (the app connects via a pgsql PDO DSN —
-- see includes/config.php). Safe to run multiple times.
-- ==========================================================================

CREATE TABLE IF NOT EXISTS service_fees (
    id           BIGSERIAL PRIMARY KEY,
    service_key  VARCHAR(30)   NOT NULL REFERENCES services(service_key) ON DELETE CASCADE ON UPDATE CASCADE,
    label        VARCHAR(150)  NOT NULL,
    amount       INT           NOT NULL DEFAULT 0,
    note         VARCHAR(255),
    sort_order   INT           NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_service_fees_service_key ON service_fees (service_key);
