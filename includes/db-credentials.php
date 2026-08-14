<?php
/**
 * includes/db-credentials.php
 * Just the connection settings, shared by includes/db.php (the hard
 * dependency every page uses) and includes/config.php (which makes a
 * soft, non-fatal attempt to load live catalog data). Adjust for your
 * environment — XAMPP defaults shown.
 */
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

$DB_HOST = getenv('DB_HOST');
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
