<?php
/**
 * includes/db.php
 * Single PDO connection reused by every backend script.
 * Adjust credentials for your environment (XAMPP defaults shown).
 */

$DB_HOST = 'localhost';
$DB_NAME = 'parish_system';
$DB_USER = 'root';
$DB_PASS = '';   // set your MySQL root password here if you have one

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    // In production, log $e->getMessage() instead of echoing it.
    die('Database connection failed. Make sure MySQL is running and database/schema.sql has been imported. (' . $e->getMessage() . ')');
}