<?php
/**
 * config/db.php
 * ---------------------------------------------------------------------
 * Database connection for the Restaurant Food Stock Management System.
 *
 * LOCALLY: edit the four values below to match your own MySQL Server.
 *
 * ON VERCEL: the same values are read from environment variables
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS). Set them in
 * Vercel -> Project -> Settings -> Environment Variables.
 *
 * Environment variables win when they are present, so the same code
 * runs on your laptop and on the server without any changes.
 * ---------------------------------------------------------------------
 */

$db_host = 'localhost';
$db_port = '3306';
$db_name = 'restaurant_stock_management';
$db_user = 'root';
$db_pass = '';

// Read from the environment when it is available (Vercel, Docker, CI).
// getenv() returns false when the variable is not set.
$envHost = getenv('DB_HOST');
if ($envHost !== false && $envHost !== '') {
    $db_host = $envHost;
}

$envPort = getenv('DB_PORT');
if ($envPort !== false && $envPort !== '') {
    $db_port = $envPort;
}

$envName = getenv('DB_NAME');
if ($envName !== false && $envName !== '') {
    $db_name = $envName;
}

$envUser = getenv('DB_USER');
if ($envUser !== false && $envUser !== '') {
    $db_user = $envUser;
}

$envPass = getenv('DB_PASS');
if ($envPass !== false) {
    $db_pass = $envPass;
}

/**
 * Creates the PDO connection and returns it.
 *
 * Every query in this project uses PREPARED STATEMENTS, which is what
 * keeps the application safe from SQL injection.
 */
function db_connect(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host={$GLOBALS['db_host']};port={$GLOBALS['db_port']};"
             . "dbname={$GLOBALS['db_name']};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $GLOBALS['db_user'], $GLOBALS['db_pass'], [
                // Throw an exception whenever a query fails (instead of staying silent)
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Fetch rows as associative arrays: $row['supplier_name']
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Use real prepared statements (not emulated ones) for safety
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Never show the real error to the user in production, but for a
            // college project a clear message is much easier to debug.
            http_response_code(500);
            echo '<h2 style="font-family:system-ui;color:#b3261e">Database connection failed</h2>';
            echo '<p style="font-family:system-ui">Open <code>config/db.php</code> and check the '
               . 'host, database name, username and password.</p>';
            echo '<pre style="font-family:ui-monospace,monospace;background:#f6f2ec;padding:14px;'
               . 'border-radius:8px;white-space:pre-wrap">'
               . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
               . '</pre>';
            exit;
        }
    }

    return $pdo;
}
