<?php
/**
 * includes/config.php
 * Core data model for the parish. In the full build these arrays get
 * replaced by MySQL queries (services, fees, mass_schedule, requirements,
 * users tables) — kept as plain PHP arrays here so the front end and
 * page flow can be built and demoed before the database is wired up.
 */

require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');
require_once __DIR__ . '/session.php';


$parish = [
    'name'        => 'Our Lady of Mt. Carmel Parish',
    'des'         => 'Parish Management System',
    'address'     => 'Poblacion Street, Tagbilaran City, Bohol',
    'phone'       => '(038) 411 2277',
    'email'       => 'office@svfparish.ph',
    'facebook'    => 'facebook.com/svfparish',
    'priest'      => 'Rev. Fr. Emmanuel R. Salazar',
    'priest_role' => 'Parish Priest & Administrator',
];

$office_hours = [
    ['Mon – Fri', '8:00 AM – 5:00 PM'],
    ['Saturday',  '8:00 AM – 12:00 NN'],
    ['Sunday',    'Closed (Mass day)'],
];

$mass_schedule = [
    ['Weekdays', '6:00 AM · 6:00 PM'],
    ['Saturday Anticipated', '5:30 PM'],
    ['Sunday', '6:00 AM · 8:00 AM · 10:00 AM · 5:00 PM'],
];

$services = [
    ['key' => 'baptism', 'icon' => 'dove', 'name' => 'Baptism',
        'desc' => 'Welcoming a child into the faith through the Sacrament of Baptism.', 'fee' => 500],
    ['key' => 'confirmation', 'icon' => 'flame', 'name' => 'Confirmation',
        'desc' => 'Strengthening faith through the Sacrament of Confirmation.', 'fee' => 400],
    ['key' => 'matrimony', 'icon' => 'rings', 'name' => 'Matrimony',
        'desc' => 'Sacred union of marriage witnessed before God and the parish.', 'fee' => 3500],
    ['key' => 'burial', 'icon' => 'cross', 'name' => 'Burial Mass',
        'desc' => 'A Mass of Christian burial to commend the departed to God.', 'fee' => 1500],
    ['key' => 'intention', 'icon' => 'candle', 'name' => 'Mass Intention',
        'desc' => 'Offer a Mass for a special intention, thanksgiving, or the departed.', 'fee' => 150],
    ['key' => 'anointing', 'icon' => 'vessel', 'name' => 'Anointing of the Sick',
        'desc' => 'Spiritual comfort and healing grace for the sick and elderly.', 'fee' => 0],
];

$requirements = [
    'baptism'      => ['Child\'s Birth Certificate (PSA)', 'Parents\' Marriage Certificate', 'Baptismal record of sponsors', 'Valid ID of parents', 'Certificate of No Marriage (if applicable)'],
    'confirmation' => ['Baptismal Certificate', 'Certificate of Catechism completion', 'Valid ID of sponsor', 'Confirmation name & sponsor form'],
    'matrimony'    => ['Baptismal & Confirmation Certificates (both parties)', 'CENOMAR from PSA', 'Marriage License', 'Pre-Cana Seminar Certificate', 'Banns of Marriage request'],
    'burial'       => ['Death Certificate (PSA or Local Civil Registrar)', 'Funeral Home confirmation', 'Baptismal Certificate of deceased, if available'],
    'intention'    => ['Name of intention / offerant', 'Preferred Mass date and time'],
    'anointing'    => ['Name of the sick person', 'Address or hospital room', 'Contact number of family'],
];

/**
 * Itemized fee lines per service — e.g. baptism has a sponsor fee and a
 * prejordan card fee instead of one flat price. Each entry is
 * ['label' => ..., 'amount' => int, 'note' => string|null].
 * Empty here in the hardcoded fallback; populated from service_fees below
 * once database/migration_add_service_fees.sql has been run.
 */
$fees = [];

/**
 * Staff-defined input fields per certificate-request service (e.g. baptismal
 * certificate: "Full Name of Registrant", "Birthday / Year of Birth", "Year
 * of Baptism"). Each entry is just the field's label — certificates.php
 * renders one text input per label, in order, and stores what the
 * requestor typed as {label: value} JSON keyed by these same labels.
 * Empty here in the hardcoded fallback; populated from service_form_fields
 * below once database/migration_add_certificate_form_fields.sql has been run.
 */
$certFields = [];

// Staff roles allowed on the internal (non-parishioner) login/registration pages.
$staff_roles = [
    'priest'    => ['label' => 'Priest',    'sub' => 'Administrator'],
    'secretary' => ['label' => 'Secretary', 'sub' => 'Scheduling & records'],
    'treasurer' => ['label' => 'Treasurer', 'sub' => 'Payments & fees'],
];

/**
 * Icon keys the front end knows how to render (see the inline $icons
 * arrays in index.php / intentions.php). The Catalog admin page only
 * lets the Priest choose from this set so a new service never ends up
 * with a broken/missing icon.
 */
$catalog_icon_keys = ['dove', 'flame', 'rings', 'cross', 'candle', 'vessel'];

/**
 * Live catalog override: if database/migration_add_catalog.sql has been
 * run, $services and $requirements above get replaced with the current
 * database contents, so edits made on catalog.php show up everywhere
 * (landing page, booking, receipts) without a code change. If the
 * catalog tables don't exist yet, or the DB is unreachable, this fails
 * silently and the hardcoded arrays above keep working exactly as
 * before — nothing breaks mid-upgrade.
 */
try {
    require_once __DIR__ . '/db-credentials.php';
    $catalogPdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};sslmode=require",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    if ($catalogPdo->query("SELECT to_regclass('public.services')")->fetchColumn() !== null) {
        // The `category` column only exists once migration_add_certificate_requests.sql
        // has been run — check first so an un-migrated install doesn't lose the
        // entire live catalog to the hardcoded fallback over one missing column.
        $hasCategory = $catalogPdo->query(
            "SELECT 1 FROM information_schema.columns WHERE table_name = 'services' AND column_name = 'category'"
        )->fetchColumn() !== false;
        $categorySelect = $hasCategory ? ', category' : '';

        $dbServices = $catalogPdo->query(
            "SELECT service_key, icon, name, description, fee{$categorySelect} FROM services WHERE is_active = TRUE ORDER BY sort_order ASC, id ASC"
        )->fetchAll();

        if ($dbServices) {
            $services = array_map(function ($s) {
                return ['key' => $s['service_key'], 'icon' => $s['icon'], 'name' => $s['name'], 'desc' => $s['description'], 'fee' => (int) $s['fee'], 'category' => $s['category'] ?? 'sacrament'];
            }, $dbServices);

            $requirements = [];
            $reqRows = $catalogPdo->query(
                "SELECT service_key, requirement_text FROM service_requirements ORDER BY service_key, sort_order ASC, id ASC"
            )->fetchAll();
            foreach ($reqRows as $r) {
                $requirements[$r['service_key']][] = $r['requirement_text'];
            }
            // Any service with zero requirement rows still needs an empty array
            // so existing pages that loop $requirements[$key] don't warn.
            foreach ($services as $s) {
                if (!isset($requirements[$s['key']])) {
                    $requirements[$s['key']] = [];
                }
            }

            if ($catalogPdo->query("SELECT to_regclass('public.service_fees')")->fetchColumn() !== null) {
                $fees = [];
                $feeRows = $catalogPdo->query(
                    "SELECT service_key, label, amount, note FROM service_fees ORDER BY service_key, sort_order ASC, id ASC"
                )->fetchAll();
                foreach ($feeRows as $f) {
                    $fees[$f['service_key']][] = ['label' => $f['label'], 'amount' => (int) $f['amount'], 'note' => $f['note']];
                }
            }

            if ($catalogPdo->query("SELECT to_regclass('public.service_form_fields')")->fetchColumn() !== null) {
                $certFields = [];
                $fieldRows = $catalogPdo->query(
                    "SELECT service_key, field_label FROM service_form_fields ORDER BY service_key, sort_order ASC, id ASC"
                )->fetchAll();
                foreach ($fieldRows as $f) {
                    $certFields[$f['service_key']][] = $f['field_label'];
                }
            }
        }
    }
    unset($catalogPdo);
} catch (Exception $e) {
    // DB unreachable or migration not applied yet — keep the hardcoded
    // fallback arrays defined above. Nothing to do here.
}

define('PAYMONGO_SECRET_KEY', getenv('PAYMONGO_SECRET_KEY'));
define('PAYMONGO_WEBHOOK_SECRET', getenv('PAYMONGO_WEBHOOK_SECRET'));
define('APP_URL', getenv('APP_URL'));

// Supabase Storage — uploaded appointment document scans (see
// includes/supabase-storage.php). SUPABASE_URL is the project's API URL
// (Project Settings → API → Project URL), and SUPABASE_SERVICE_ROLE_KEY is
// the secret service_role key from that same page (NOT the anon key — this
// app manages its own sessions rather than Supabase Auth, so server-side
// requests need the service role to read/write a private bucket).
define('SUPABASE_URL', rtrim(getenv('SUPABASE_URL') ?: '', '/'));
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY'));
