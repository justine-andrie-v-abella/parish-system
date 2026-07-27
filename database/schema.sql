-- ==========================================================================
-- Parish Appointment & Management System — core auth schema
-- Run this first: mysql -u root -p < database/schema.sql
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS parish_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE parish_system;

-- ---------------------------------------------------------------------
-- users: holds all four roles. Parishioners self-register via
-- register.php. Priest/Secretary/Treasurer are provisioned once via
-- staff-register.php using an invite code from invite_codes below.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('parishioner','priest','secretary','treasurer') NOT NULL,
    full_name       VARCHAR(150)      NOT NULL,
    email           VARCHAR(190)      NOT NULL UNIQUE,
    password_hash   VARCHAR(255)      NOT NULL,
    address         VARCHAR(255)      NULL,          -- parishioners
    birthday        DATE              NULL,          -- parishioners
    contact_number  VARCHAR(30)       NULL,
    is_active       TINYINT(1)        NOT NULL DEFAULT 1,
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- invite_codes: gates staff-register.php so the public can't create
-- Priest/Secretary/Treasurer accounts. The Priest/Admin generates these
-- (later, from the User Management screen) and hands them out.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invite_codes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(40)  NOT NULL UNIQUE,
    role        ENUM('priest','secretary','treasurer') NOT NULL,
    is_used     TINYINT(1)   NOT NULL DEFAULT 0,
    used_by     INT UNSIGNED NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Demo seed codes so staff-register.php has something to test against.
-- Replace/delete these once real invite generation exists.
INSERT INTO invite_codes (code, role) VALUES
  ('SVF-2026-PRST', 'priest'),
  ('SVF-2026-SECR', 'secretary'),
  ('SVF-2026-TRSR', 'treasurer')
ON DUPLICATE KEY UPDATE code = code;

-- ---------------------------------------------------------------------
-- appointments: one row per sacrament/service request. service_key
-- matches the keys used in includes/config.php ($services array).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appointments (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL,
    service_key        VARCHAR(30)  NOT NULL,
    appointment_date   DATE         NOT NULL,
    appointment_time   TIME         NULL,
    status             ENUM('pending','confirmed','approved','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
    payment_status      ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    notes              VARCHAR(255) NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_appt_date (appointment_date),
    INDEX idx_appt_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- notifications: simple per-user notice feed.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    message     VARCHAR(255) NOT NULL,
    type        ENUM('approved','payment','schedule','reminder','announcement') NOT NULL DEFAULT 'announcement',
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- holidays: dates the office is closed / no Mass bookings. Shown as
-- "Holiday" (gray) on the parishioner's calendar regardless of load.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS holidays (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date  DATE NOT NULL UNIQUE,
    name          VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Demo data — reseeded (delete + reinsert) every time this file is run,
-- with dates relative to CURDATE() so the dashboard always has something
-- meaningful to show regardless of when you import it. Tied to whichever
-- parishioner account was created first (register.php) — if no
-- parishioner has registered yet, these SELECT...LIMIT 1 inserts simply
-- insert nothing (no error). Re-run this file after your first
-- parishioner signs up to populate their dashboard. Safe to delete this
-- whole block once you have real appointment data.
-- ---------------------------------------------------------------------
DELETE FROM appointments WHERE notes = 'DEMO_SEED';
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'baptism', DATE_SUB(CURDATE(), INTERVAL 20 DAY), '09:00:00', 'completed', 'paid', 'DEMO_SEED' FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'confirmation', DATE_ADD(CURDATE(), INTERVAL 6 DAY), '10:00:00', 'approved', 'paid', 'DEMO_SEED' FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'intention', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '06:00:00', 'pending', 'unpaid', 'DEMO_SEED' FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'matrimony', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '14:00:00', 'confirmed', 'unpaid', 'DEMO_SEED' FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'burial', DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:00:00', 'cancelled', 'unpaid', 'DEMO_SEED' FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;

-- A few other parishioners' bookings (attached to a filler demo account, not
-- your real test user) so the shared calendar shows Reserved / Fully Booked
-- days too, without inflating your own personal request counts.
INSERT INTO users (role, full_name, email, password_hash)
VALUES ('parishioner', 'Demo Filler Parishioner', 'demo.filler@svfparish.ph', '!not-a-real-login-hash!')
ON DUPLICATE KEY UPDATE full_name = full_name;

DELETE FROM appointments WHERE notes = 'DEMO_SEED_OTHER';
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'baptism', DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', 'confirmed', 'paid', 'DEMO_SEED_OTHER' FROM users WHERE email='demo.filler@svfparish.ph';
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'anointing', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '09:00:00', 'confirmed', 'paid', 'DEMO_SEED_OTHER' FROM users WHERE email='demo.filler@svfparish.ph';
INSERT INTO appointments (user_id, service_key, appointment_date, appointment_time, status, payment_status, notes)
SELECT id, 'burial', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '10:00:00', 'confirmed', 'paid', 'DEMO_SEED_OTHER' FROM users WHERE email='demo.filler@svfparish.ph';

DELETE FROM notifications WHERE message LIKE 'DEMO:%';
INSERT INTO notifications (user_id, message, type, is_read)
SELECT id, 'DEMO: Your Confirmation appointment was approved by the Secretary.', 'approved', 0 FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;
INSERT INTO notifications (user_id, message, type, is_read)
SELECT id, 'DEMO: Payment received for your Baptism request.', 'payment', 1 FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;
INSERT INTO notifications (user_id, message, type, is_read)
SELECT id, 'DEMO: Reminder — your Matrimony appointment is in 2 weeks.', 'reminder', 0 FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;
INSERT INTO notifications (user_id, message, type, is_read)
SELECT id, 'DEMO: Office will be closed for the Parish Fiesta.', 'announcement', 1 FROM users WHERE role='parishioner' ORDER BY id LIMIT 1;

DELETE FROM holidays WHERE name LIKE 'Demo:%';
INSERT INTO holidays (holiday_date, name) VALUES (DATE_ADD(CURDATE(), INTERVAL 9 DAY), 'Demo: Parish Fiesta');
INSERT INTO holidays (holiday_date, name) VALUES (DATE_ADD(CURDATE(), INTERVAL 24 DAY), 'Demo: Diocesan Holiday');