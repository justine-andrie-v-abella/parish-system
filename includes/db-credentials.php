<?php
/**
 * includes/db-credentials.php
 * Just the connection settings, shared by includes/db.php (the hard
 * dependency every page uses) and includes/config.php (which makes a
 * soft, non-fatal attempt to load live catalog data). Adjust for your
 * environment — XAMPP defaults shown.
 */
$DB_HOST = 'localhost';
$DB_NAME = 'parish_system';
$DB_USER = 'root';
$DB_PASS = '';   // set your MySQL root password here if you have one