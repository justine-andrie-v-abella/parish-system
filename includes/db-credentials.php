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
$DB_PORT = getenv('DB_PORT');
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');

/**
 * Opens one PDO connection to the Supabase database, with the options both
 * db.php and config.php need. Pulled out here so both can share a single
 * connection per request instead of each opening their own — the DB is
 * remote (Supabase), so every extra connection is a full TCP+TLS+auth
 * round trip (roughly 1.5-2s from a typical XAMPP box), not a local
 * near-instant socket.
 */
function open_db_connection(string $host, string $port, string $name, string $user, string $pass): PDO
{
    return new PDO(
        "pgsql:host={$host};port={$port};dbname={$name};sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // See the same flag in includes/db.php: emulating prepares
            // client-side turns each query into one network round trip
            // instead of the 3-4 the real prepared-statement protocol
            // needs, which matters a lot once the DB isn't on localhost.
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_PERSISTENT         => true,
        ]
    );
}