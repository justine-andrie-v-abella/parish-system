<?php
/**
 * includes/db.php
 * Single PDO connection reused by every backend script.
 */
require_once __DIR__ . '/db-credentials.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO(
            "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};sslmode=require",
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
        die('Database connection failed. Check your Supabase connection details in .env. (' . $e->getMessage() . ')');
    }
}

function is_true($val): bool {
    return $val === true || $val === 't' || $val === '1' || $val === 1;
}