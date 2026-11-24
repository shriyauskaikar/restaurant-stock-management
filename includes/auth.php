<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------------------
 * Everything to do with logging in and staying logged in.
 *
 * HOW LOGIN WORKS (the four steps)
 * --------------------------------
 *  1. The user types a username (or email) and a password.
 *  2. We look the user up in the `users` table with a prepared statement.
 *  3. We check the typed password against the stored HASH using
 *     password_verify(). The plain password is never stored anywhere.
 *  4. If it matches, we put the user's id and name into the PHP SESSION.
 *
 * From then on, every protected page calls require_login() at the top.
 * That function checks the session and sends the visitor back to the
 * login page if they are not signed in.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Starts the PHP session if it is not running yet.
 *
 * A session is a small store on the server that remembers who the
 * visitor is between page loads. It is what makes "stay logged in"
 * work, and it is also what the CSRF token is stored in.
 */
function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;   // already running, nothing to do
    }

    // The cookie settings can only be changed BEFORE anything has been
    // printed to the page, so we only set them when that is still
    // possible. (This matters when the code is run from the command
    // line, where output may already have started.)
    if (!headers_sent()) {
        session_set_cookie_params([
            'httponly' => true, // JavaScript cannot read the cookie
            'samesite' => 'Lax', // helps protect against CSRF
        ]);
    }

    // The @ stops a warning in the rare case that output has already
    // started. The session array still works, which is all we need.
    @session_start();
}

/**
 * Is somebody currently logged in?
 */
function is_logged_in(): bool
{
    start_session();
    return !empty($_SESSION['user_id']);
}

/**
 * The details of the user who is logged in right now.
 * Returns an empty array if nobody is logged in.
 */
function current_user(): array
{
    start_session();
    return $_SESSION['user'] ?? [];
}

/**
 * The id of the user who is logged in right now (0 if nobody is).
 *
 * Every inventory query uses this number in a WHERE clause, which is
 * what keeps one user's stock separate from another's.
 */
function current_user_id(): int
{
    start_session();
    return (int) ($_SESSION['user_id'] ?? 0);
}

/**
 * Sends the visitor to the login page if they are not signed in.
 *
 * Every protected page (dashboard, suppliers, ingredients, purchases,
 * low stock) includes this file and calls this function at the very
 * top, BEFORE any data is read from the database.
 *
 * The page the user was trying to reach is remembered in the session,
 * so that after a successful login they land there instead of always
 * on the dashboard.
 */
function require_login(): void
{
    start_session();

    if (!is_logged_in()) {
        // Remember where the user was heading. We only do this for GET
        // requests, so we never redirect to a page that changes data.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_SESSION['redirect_after_login'] = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        }

        redirect('auth/login.php');
    }
}

/**
 * Checks the login details that were posted to the login form.
 *
 * Returns an array:
 *   ['ok' => true,  'user' => ['user_id' => 1, 'username' => 'admin']]
 *   ['ok' => false, 'error' => 'Wrong password.']
 */
function attempt_login(string $login, string $password): array
{
    $pdo = db_connect();

    // The user can type EITHER their username OR their email address.
    // A prepared statement is used, so the input cannot break the query.
    $statement = $pdo->prepare(
        'SELECT user_id, username, email, password
           FROM users
          WHERE username = ? OR email = ?
          LIMIT 1'
    );
    $statement->execute([$login, $login]);
    $user = $statement->fetch();

    // --- Case 1: no such user -------------------------------------
    if (!$user) {
        // We give the same message as a wrong password so that nobody
        // can find out which usernames exist.
        return ['ok' => false, 'error' => 'Incorrect username or password.'];
    }

    // --- Case 2: the user exists, check the password ---------------
    // password_verify() compares the typed password with the stored
    // hash. It returns true when they match.
    if (!password_verify($password, $user['password'])) {
        return ['ok' => false, 'error' => 'Incorrect username or password.'];
    }

    // --- Case 3: correct -------------------------------------------
    // If the hash was made with an older cost, refresh it so the
    // stored hash always uses the strongest settings.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $update->execute([$newHash, $user['user_id']]);
    }

    // Log the user in by storing their id in the session.
    start_session();

    // A new session id stops an attacker from fixing the session id in
    // the visitor's browser before they log in.
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id']   = (int) $user['user_id'];
    $_SESSION['user']      = [
        'user_id'  => (int) $user['user_id'],
        'username' => $user['username'],
        'email'    => $user['email'],
    ];
    $_SESSION['logged_in_at'] = time();

    return ['ok' => true, 'user' => $_SESSION['user']];
}

/**
 * Creates a NEW user account from the sign-up form.
 *
 * The password is turned into a bcrypt hash with password_hash() BEFORE
 * it is inserted, so the plain password is never stored in MySQL.
 *
 * Returns:
 *   ['ok' => true,  'user' => [...]]
 *   ['ok' => false, 'error' => '...']
 */
function register_user(string $username, string $email, string $password): array
{
    $pdo = db_connect();

    // Re-check the rules on the server. Never trust the browser.
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        return ['ok' => false, 'error' => 'Username must be 3-50 characters: letters, numbers, dot, dash or underscore.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters long.'];
    }

    // The UNIQUE constraint on username and email stops duplicates at the
    // database level. We check first only to give a friendlier message.
    $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
    $check->execute([$username, $email]);
    if ((int) $check->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'That username or email is already registered. Please sign in instead.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $insert = $pdo->prepare(
            'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
        );
        $insert->execute([$username, $email, $hash]);
    } catch (PDOException $e) {
        // If two people sign up at the same instant, the UNIQUE index wins.
        return ['ok' => false, 'error' => 'That username or email is already registered.'];
    }

    $id = (int) $pdo->lastInsertId();

    // Sign the new user in straight away.
    start_session();
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $id;
    $_SESSION['user'] = [
        'user_id'  => $id,
        'username' => $username,
        'email'    => $email,
    ];
    $_SESSION['logged_in_at'] = time();

    return ['ok' => true, 'user' => $_SESSION['user']];
}

/**
 * Deletes a user account and ALL of that user's inventory data.
 *
 * The order matters. purchases has ON DELETE RESTRICT foreign keys to
 * suppliers and ingredients, so we must delete purchases first, then
 * ingredients, then suppliers, then finally the user row. Doing it in
 * a transaction means a failure leaves everything untouched.
 */
function delete_user_account(int $userId): bool
{
    $pdo = db_connect();

    try {
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM purchases WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM ingredients WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM suppliers WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

/**
 * Ends the session and clears it completely.
 */
function logout(): void
{
    start_session();

    // Remove every key from the session array ...
    $_SESSION = [];

    // ... and delete the session cookie from the browser.
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Finally destroy the session on the server.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
