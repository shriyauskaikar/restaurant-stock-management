<?php
/**
 * test_auth.php
 * ---------------------------------------------------------------------
 * Tests the LOGIN and PASSWORD SECURITY.
 *
 * What it checks:
 *   [A] The password is stored as a hash, not as plain text
 *   [B] password_verify() accepts the right password and rejects the wrong one
 *   [C] The login works with the username AND with the email address
 *   [D] A wrong password is refused
 *   [E] A user that does not exist is refused
 *   [F] The session is set up correctly after logging in
 *   [G] The admin account is protected
 *
 * Run from the command line:  php test_auth.php
 * ---------------------------------------------------------------------
 */

if (file_exists(__DIR__ . '/config/local.php')) {
    require_once __DIR__ . '/config/local.php';
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Never hardcode a real password in the uploaded source code.
$TEST_PASS = getenv('RS_ADMIN_PASS') ?: 'CHANGE_ME';
require_once __DIR__ . '/includes/auth.php';

// Start the session BEFORE any output, so the session functions work
// properly on the command line.
start_session();

$pdo = db_connect();
$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        echo "  [PASS] $label\n";
        $pass++;
    } else {
        echo "  [FAIL] $label" . ($detail ? "  -> $detail" : '') . "\n";
        $fail++;
    }
}

echo "==============================================\n";
echo " LOGIN / PASSWORD SECURITY TEST\n";
echo "==============================================\n\n";

// --- Find the admin account ---------------------------------------
$statement = $pdo->prepare('SELECT * FROM users ORDER BY user_id LIMIT 1');
$statement->execute();
$admin = $statement->fetch();

echo "[A] The password is stored as a hash\n";

if (!$admin) {
    echo "  [FAIL] there is no user in the users table\n";
    echo "         Run:  php setup/create_admin.php\n";
    echo "\n==============================================\n";
    echo " RESULT:  0 passed, 1 failed\n";
    echo "==============================================\n";
    exit(1);
}

check('a user account exists', true);

$storedHash = $admin['password'];

check('the stored password is NOT plain text',
      $storedHash !== $TEST_PASS,
      'the password appears to be stored in plain text!');

check('the stored password looks like a bcrypt hash',
      str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$2a$'),
      'got: ' . substr($storedHash, 0, 7));

check('the hash is 60 characters long (bcrypt always is)',
      strlen($storedHash) === 60,
      'length is ' . strlen($storedHash));

echo "\n[B] password_verify() works correctly\n";

check('the correct password verifies', password_verify($TEST_PASS, $storedHash));
check('a wrong password does NOT verify', !password_verify('wrong-password', $storedHash));
check('the password is case sensitive', !password_verify(strtolower($TEST_PASS), $storedHash));
check('an empty password does NOT verify', !password_verify('', $storedHash));

echo "\n[C] The same ingredient always gives a different hash\n";
$hashA = password_hash($TEST_PASS, PASSWORD_DEFAULT);
$hashB = password_hash($TEST_PASS, PASSWORD_DEFAULT);
check('two hashes of the same password are different (random salt)', $hashA !== $hashB);
check('but both still verify', password_verify($TEST_PASS, $hashA) && password_verify($TEST_PASS, $hashB));

echo "\n[D] Logging in with the username\n";
$result = attempt_login($admin['username'], $TEST_PASS);
check('the login succeeds', $result['ok'], $result['error'] ?? 'no error key');
check('the session holds the user id', !empty($_SESSION['user_id']));
check('is_logged_in() returns true', is_logged_in());
check('current_user() returns the username',
      (current_user()['username'] ?? '') === $admin['username']);
check('the session stores the user id as a number',
      is_int($_SESSION['user_id'] ?? null));

// --- Log out again so the next test starts clean -------------------
logout();
check('after logout nobody is logged in', !is_logged_in());
check('after logout the session is empty', empty($_SESSION['user_id']));

echo "\n[E] Logging in with the email address\n";
$result = attempt_login($admin['email'], $TEST_PASS);
check('the login succeeds', $result['ok'], $result['error'] ?? 'no error key');
check('the same user id comes back', ($_SESSION['user_id'] ?? 0) === (int) $admin['user_id']);
logout();

echo "\n[F] Bad credentials are refused\n";

$result = attempt_login($admin['username'], 'totally-wrong');
check('a wrong password is refused', !$result['ok']);
check('the error message is shown', !empty($result['error']));
check('the message does not say which part was wrong',
      stripos($result['error'], 'password') === false || stripos($result['error'], 'username or password') !== false);
check('nobody is logged in after a failed attempt', !is_logged_in());

$result = attempt_login('no-such-user-here', $TEST_PASS);
check('an unknown username is refused', !$result['ok']);
check('the message is the same as for a wrong password',
      ($result['error'] ?? '') === 'Incorrect username or password.');

$result = attempt_login('', $TEST_PASS);
check('an empty username is refused', !$result['ok']);

$result = attempt_login($admin['username'], '');
check('an empty password is refused', !$result['ok']);

echo "\n[G] The admin account is protected\n";

$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$check->execute(['admin']);
check('an "admin" account exists', (int) $check->fetchColumn() > 0);

$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE password = ?');
$check->execute([$TEST_PASS]);
check('NO account stores a plain-text password', (int) $check->fetchColumn() === 0);

echo "\n==============================================\n";
echo " RESULT:  $pass passed, $fail failed\n";
echo "==============================================\n";

exit($fail === 0 ? 0 : 1);
