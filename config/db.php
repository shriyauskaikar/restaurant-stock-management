<?php
/**
 * config/db.php
 * ---------------------------------------------------------------------
 * Database connection for the Restaurant Food Stock Management System.
 *
 * THIS IS THE ONLY FILE YOU NORMALY NEED TO EDIT.
 * Change the four values below so they match your own MySQL Server.
 *
 *   $db_host  -> the MySQL Server host
 *   $db_name  -> the database name created by sql/schema.sql
 *   $db_user  -> your MySQL username
 *   $db_pass  -> your MySQL password
 *
 * ---------------------------------------------------------------------
 *  A NOTE ABOUT "localhost" vs "127.0.0.1"
 * ---------------------------------------------------------------------
 *  When the host is written as "localhost", PDO on Windows connects to
 *  MySQL over a NAMED PIPE instead of over TCP. That has a surprising
 *  side effect: the named pipe always belongs to ONE MySQL instance
 *  (the one XAMPP installs), so you can end up talking to a different
 *  server than the one you think you are.
 *
 *  Writing "127.0.0.1" forces a plain TCP connection to the port
 *  number written below. That is why this project uses 127.0.0.1.
 * ---------------------------------------------------------------------
 */

/*
 * SECURITY
 * --------
 * No real password is stored in this file. Change the DEFAULTS below to
 * match your own machine, or (better) set environment variables so the
 * password never appears in the source code at all.
 *
 * On your laptop you can set them in a local file that is NOT uploaded to
 * GitHub: create config/local.php with
 *
 *   <?php
 *   putenv('RS_DB_PASS=your-mysql-password');
 *   putenv('RS_ADMIN_PASS=your-admin-password');
 *
 * and this file already loads it automatically (see just below).
 */
if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

$db_host = getenv('RS_DB_HOST') ?: '127.0.0.1';
$db_name = getenv('RS_DB_NAME') ?: 'restaurant_stock_management';
$db_user = getenv('RS_DB_USER') ?: 'root';
$db_pass = getenv('RS_DB_PASS') ?: 'CHANGE_ME_TO_YOUR_MYSQL_PASSWORD';
$db_port = getenv('RS_DB_PORT') ?: '3307';

// The TCP port of the MySQL Server that MySQL Workbench also connects to.
//   3306 -> XAMPP's MariaDB (the one phpMyAdmin uses)
//   3307 -> MySQL Server 8.0 (the one MySQL Workbench uses here)
//
// On your laptop, keep the real values in config/local.php (gitignored) so
// the GitHub copy never contains a working password.

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
        $dsn = "mysql:host={$GLOBALS['db_host']};port={$GLOBALS['db_port']};dbname={$GLOBALS['db_name']};charset=utf8mb4";

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
               . 'host, port, database name, username and password.</p>';
            echo '<pre style="font-family:ui-monospace,monospace;background:#f6f2ec;padding:14px;'
               . 'border-radius:8px;white-space:pre-wrap">'
               . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
               . '</pre>';
            exit;
        }
    }

    return $pdo;
}
