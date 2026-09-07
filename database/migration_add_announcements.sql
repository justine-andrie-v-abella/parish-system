-- ==========================================================================
-- Migration: Announcements
-- Run in the Supabase SQL editor.
--
-- A simple parish-wide bulletin board — Priest/Secretary post, every
-- parishioner reads. Deliberately NOT built on top of the per-user
-- `notifications` table (that's a one-row-per-recipient read/unread inbox;
-- fanning a broadcast out into one notification row per parishioner would
-- mean N duplicate rows per announcement for no real benefit here). Pages
-- just query this table directly.
--
-- Written for Postgres/Supabase. Safe to run multiple times.
-- ==========================================================================

CREATE TABLE IF NOT EXISTS announcements (
    id          BIGSERIAL PRIMARY KEY,
    title       VARCHAR(150) NOT NULL,
    body        TEXT NOT NULL,
    created_by  INT NOT NULL REFERENCES users(id),
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_announcements_active_created ON announcements (is_active, created_at DESC);
