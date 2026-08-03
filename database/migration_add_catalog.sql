-- ==========================================================================
-- Migration: services catalog (Fees/Catalog Management)
-- Run in phpMyAdmin (SQL tab, on the parish_system database) or:
--   mysql -u root parish_system < database/migration_add_catalog.sql
--
-- Once this runs, includes/config.php automatically switches from the
-- hardcoded $services/$requirements arrays to these tables — no other
-- code changes needed. Seeded with the exact same data that was
-- hardcoded before, so nothing visibly changes until you actually edit
-- something on catalog.php.
-- ==========================================================================

CREATE TABLE IF NOT EXISTS services (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_key   VARCHAR(30)   NOT NULL UNIQUE,
    icon          VARCHAR(20)   NOT NULL DEFAULT 'candle',
    name          VARCHAR(100)  NOT NULL,
    description   VARCHAR(255)  NOT NULL,
    fee           INT UNSIGNED  NOT NULL DEFAULT 0,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order    INT           NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_requirements (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_key       VARCHAR(30)  NOT NULL,
    requirement_text  VARCHAR(255) NOT NULL,
    sort_order        INT          NOT NULL DEFAULT 0,
    FOREIGN KEY (service_key) REFERENCES services(service_key) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO services (service_key, icon, name, description, fee, sort_order) VALUES
  ('baptism',      'dove',   'Baptism',               'Welcoming a child into the faith through the Sacrament of Baptism.', 500,  1),
  ('confirmation', 'flame',  'Confirmation',          'Strengthening faith through the Sacrament of Confirmation.',         400,  2),
  ('matrimony',    'rings',  'Matrimony',             'Sacred union of marriage witnessed before God and the parish.',      3500, 3),
  ('burial',       'cross',  'Burial Mass',           'A Mass of Christian burial to commend the departed to God.',         1500, 4),
  ('intention',    'candle', 'Mass Intention',        'Offer a Mass for a special intention, thanksgiving, or the departed.', 150, 5),
  ('anointing',    'vessel', 'Anointing of the Sick', 'Spiritual comfort and healing grace for the sick and elderly.',      0,    6)
ON DUPLICATE KEY UPDATE service_key = service_key;

INSERT INTO service_requirements (service_key, requirement_text, sort_order) VALUES
  ('baptism', 'Child\'s Birth Certificate (PSA)', 1),
  ('baptism', 'Parents\' Marriage Certificate', 2),
  ('baptism', 'Baptismal record of sponsors', 3),
  ('baptism', 'Valid ID of parents', 4),
  ('baptism', 'Certificate of No Marriage (if applicable)', 5),

  ('confirmation', 'Baptismal Certificate', 1),
  ('confirmation', 'Certificate of Catechism completion', 2),
  ('confirmation', 'Valid ID of sponsor', 3),
  ('confirmation', 'Confirmation name & sponsor form', 4),

  ('matrimony', 'Baptismal & Confirmation Certificates (both parties)', 1),
  ('matrimony', 'CENOMAR from PSA', 2),
  ('matrimony', 'Marriage License', 3),
  ('matrimony', 'Pre-Cana Seminar Certificate', 4),
  ('matrimony', 'Banns of Marriage request', 5),

  ('burial', 'Death Certificate (PSA or Local Civil Registrar)', 1),
  ('burial', 'Funeral Home confirmation', 2),
  ('burial', 'Baptismal Certificate of deceased, if available', 3),

  ('intention', 'Name of intention / offerant', 1),
  ('intention', 'Preferred Mass date and time', 2),

  ('anointing', 'Name of the sick person', 1),
  ('anointing', 'Address or hospital room', 2),
  ('anointing', 'Contact number of family', 3);