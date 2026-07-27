<?php
/**
 * includes/config.php
 * Core data model for the parish. In the full build these arrays get
 * replaced by MySQL queries (services, fees, mass_schedule, requirements,
 * users tables) — kept as plain PHP arrays here so the front end and
 * page flow can be built and demoed before the database is wired up.
 */

require_once __DIR__ . '/session.php';

$parish = [
    'name'        => 'San Vicente Ferrer Parish',
    'diocese'     => 'Diocese of Tagbilaran',
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

// Staff roles allowed on the internal (non-parishioner) login/registration pages.
$staff_roles = [
    'priest'    => ['label' => 'Priest',    'sub' => 'Administrator'],
    'secretary' => ['label' => 'Secretary', 'sub' => 'Scheduling & records'],
    'treasurer' => ['label' => 'Treasurer', 'sub' => 'Payments & fees'],
];