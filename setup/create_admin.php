<?php
/**
 * setup/create_admin.php
 * ---------------------------------------------------------------------
 * Creates (or re-creates) the admin account.
 *
 * WHY THIS IS A SCRIPT AND NOT PART OF THE SQL FILE
 * --------------------------------------------------
 * The password has to be stored as a BCRYPT HASH. The only correct way
 * to produce that hash is PHP's password_hash() function, so the
 * account is created from PHP instead of from a plain SQL INSERT.
 *
 * HOW TO USE IT
 * -------------
 *  1. First run sql/schema.sql in MySQL Workbench (it creates the
 *     database and the `users` table).
 *  2. Open a command prompt and run:
 *
 *         cd C:\xampp\htdocs\restaurant-stock-management
 *         C:\xampp\php\php.exe setup\create_admin.php
 *
 *  3. The script prints the account it created.
 *
 * RUN IT AGAIN TO CHANGE THE PASSWORD
 * -----------------------------------
 * If an account with the same username already exists, the script
 * updates it instead of failing, so you can always use it to reset the
 * admin password.
 *
 * TO USE YOUR OWN PASSWORD
 * -------------------------
 * Edit the three values in the "SETTINGS" block below.
 * ---------------------------------------------------------------------
 */

/* =====================================================================
   SETTINGS - change these if you want different details
   ===================================================================== */
// Real values can live in config/local.php (gitignored) as:
//   putenv('RS_ADMIN_USER=admin');
//   putenv('RS_ADMIN_EMAIL=admin@restaurant.local');
//   putenv('RS_ADMIN_PASS=your-password');
if (file_exists(__DIR__ . '/../config/local.php')) {
    require_once __DIR__ . '/../config/local.php';
}
$username = getenv('RS_ADMIN_USER') ?: 'admin';
$email    = getenv('RS_ADMIN_EMAIL') ?: 'admin@restaurant.local';
$password = getenv('RS_ADMIN_PASS') ?: 'CHANGE_ME_TO_YOUR_ADMIN_PASSWORD';

require_once __DIR__ . '/../config/db.php';

echo "==============================================\n";
echo " CREATE ADMIN ACCOUNT\n";
echo "==============================================\n\n";

try {
    $pdo = db_connect();
} catch (Throwable $e) {
    echo "  [ERROR] Could not connect to the database.\n";
    echo "  " . $e->getMessage() . "\n\n";
    echo "  Check that:\n";
    echo "    1. sql/schema.sql has been run in MySQL Workbench\n";
    echo "    2. config/db.php has the right host / port / user / password\n\n";
    exit(1);
}

// --- Basic checks on the values we were given ----------------------
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    echo "  [ERROR] The username may only contain letters, numbers, dot, dash\n";
    echo "          and underscore (3 to 50 characters).\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "  [ERROR] That email address is not valid.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "  [ERROR] The password must be at least 8 characters long.\n";
    exit(1);
}

// --- Work out the hash ---------------------------------------------
// password_hash() creates a bcrypt hash. It automatically salts the
// hash with random data, so two identical passwords never produce the
// same hash in the database.
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "  Hashing the password with password_hash() ...\n";
echo "  Hash: " . $hash . "\n\n";

// --- Does this username already exist? -----------------------------
$check = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
$check->execute([$username]);
$existing = $check->fetch();

if ($existing) {
    // --- UPDATE: reset the password of the existing account --------
    $update = $pdo->prepare(
        'UPDATE users SET email = ?, password = ? WHERE user_id = ?'
    );
    $update->execute([$email, $hash, $existing['user_id']]);

    echo "  [OK] The account '$username' already existed - its password\n";
    echo "       and email have been UPDATED.\n\n";
} else {
    // --- INSERT: create a brand new account ------------------------
    $insert = $pdo->prepare(
        'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
    );
    $insert->execute([$username, $email, $hash]);

    echo "  [OK] A new admin account was CREATED.\n\n";
}

// --- Show the result ------------------------------------------------
$verify = $pdo->prepare('SELECT user_id, username, email FROM users WHERE username = ?');
$verify->execute([$username]);
$user = $verify->fetch();

echo "==============================================\n";
echo " YOUR LOGIN DETAILS\n";
echo "==============================================\n";
echo "  URL      : http://localhost/restaurant-stock-management/\n";
echo "  Username : " . $user['username'] . "\n";
echo "  Email    : " . $user['email'] . "\n";
echo "  Password : " . $password . "\n";
echo "==============================================\n\n";
echo "  The password is stored as a hash, never as plain text.\n";
echo "  You can check the row in MySQL Workbench:\n";
echo "      SELECT * FROM users;\n\n";
echo "  Change the password by editing the SETTINGS block at the top\n";
echo "  of this file and running it again.\n\n";
