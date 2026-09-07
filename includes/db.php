<?php
/**
 * includes/db.php
 * Single PDO connection reused by every backend script.
 */
require_once __DIR__ . '/db-credentials.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Most pages require config.php first, which already opened this same
    // connection (see its "Live catalog override" block) — this normally
    // just reuses it rather than opening a second one.
    try {
        $pdo = open_db_connection($DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS);
    } catch (PDOException $e) {
        http_response_code(500);
        die('Database connection failed. Check your Supabase connection details in .env. (' . $e->getMessage() . ')');
    }
}

function is_true($val): bool {
    return $val === true || $val === 't' || $val === '1' || $val === 1;
}