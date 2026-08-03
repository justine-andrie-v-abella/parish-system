<?php
/**
 * includes/db.php
 * Single PDO connection reused by every backend script.
 */
require_once __DIR__ . '/db-credentials.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
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
}