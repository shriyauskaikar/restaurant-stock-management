<?php
/**
 * includes/session.php
 * ---------------------------------------------------------------------
 * Starts the session.
 *
 * LOCALLY (XAMPP) the normal file based PHP session is used, so nothing
 * changes for the setup described in the README.
 *
 * ON VERCEL the app runs inside a container. Containers are stateless and
 * are replaced at any time, so a file based session would be lost between
 * two requests: the CSRF token would disappear and every form would be
 * rejected with "Your session expired".
 *
 * To avoid that, the session data is kept in a SIGNED COOKIE that travels
 * with the browser. The session then survives any number of servers, and
 * the code in functions.php can keep using $_SESSION exactly as before.
 *
 * The cookie is signed with HMAC-SHA256, so nobody can edit it, and it is
 * marked "httponly" so JavaScript cannot read it.
 * ---------------------------------------------------------------------
 */

/** The name of the cookie that holds the session data. */
const APP_SESSION_COOKIE = 'rsm_session';

/**
 * Is the current request being served over HTTPS?
 */
function app_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    // Vercel and most proxies set this header.
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Works out the secret used to sign the session cookie.
 *
 * SESSION_SECRET is the normal way to set it. If it is missing on a
 * server that has a database password, that password is used instead, so
 * that every instance still agrees on the same secret.
 */
function app_session_secret(): string
{
    $secret = getenv('SESSION_SECRET');

    if ($secret !== false && $secret !== '') {
        return $secret;
    }

    $fallback = getenv('DB_PASS');

    if ($fallback !== false && $fallback !== '') {
        return hash('sha256', 'restaurant-stock-session|' . $fallback);
    }

    // Local development without any environment variables.
    return 'restaurant-stock-local-development-secret';
}

/**
 * Should the signed cookie session be used?
 *
 * It is used whenever SESSION_SECRET is set, which is what the Vercel
 * deployment does. Without it we fall back to the normal PHP session.
 */
function app_uses_cookie_session(): bool
{
    $secret = getenv('SESSION_SECRET');
    return $secret !== false && $secret !== '';
}

/**
 * Reads the session out of the signed cookie.
 *
 * If the cookie is missing or its signature does not match, an empty
 * session is returned.
 */
function app_read_signed_session(): array
{
    if (empty($_COOKIE[APP_SESSION_COOKIE])) {
        return [];
    }

    $raw = $_COOKIE[APP_SESSION_COOKIE];
    $parts = explode('.', $raw, 2);

    if (count($parts) !== 2) {
        return [];
    }

    [$data, $signature] = $parts;

    // The signature must match, otherwise somebody edited the cookie.
    $expected = hash_hmac('sha256', $data, app_session_secret());

    if (!hash_equals($expected, $signature)) {
        return [];
    }

    $decoded = base64_decode(strtr($data, '-_', '+/'), true);

    if ($decoded === false) {
        return [];
    }

    $session = json_decode($decoded, true);

    return is_array($session) ? $session : [];
}

/**
 * Sends the session back to the browser in the signed cookie.
 */
function app_write_signed_session(array $session): void
{
    if (headers_sent()) {
        return;
    }

    // A browser cookie cannot hold much. The session only ever holds a
    // CSRF token, a short flash message and the values of a form, so it
    // is normally a few hundred bytes. If it ever grows too big we drop it
    // rather than send a cookie the browser refuses.
    if (strlen(json_encode($session) ?: '') > 3000) {
        return;
    }

    $encoded = rtrim(strtr(base64_encode(json_encode($session) ?: ''), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, app_session_secret());

    setcookie(APP_SESSION_COOKIE, $encoded . '.' . $signature, [
        'expires'  => 0,                  // a browser session cookie
        'path'     => '/',
        'secure'   => app_is_https(),
        'httponly' => true,               // JavaScript cannot read it
        'samesite' => 'Lax',
    ]);
}

/**
 * Starts the session.
 *
 * With SESSION_SECRET the data is kept in a signed cookie, otherwise the
 * normal file based PHP session is used.
 */
function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (!app_uses_cookie_session()) {
        // The ordinary PHP session - this is what XAMPP uses.
        session_start();
        return;
    }

    // Put the session data straight into $_SESSION. We do not call
    // session_start() here on purpose: PHP's own session files would not
    // survive on a serverless container, and this way the code in
    // functions.php can keep using $_SESSION as usual.
    $_SESSION = app_read_signed_session();

    // Hold on to the whole page while it is being built. The cookie can
    // only be sent while the headers have not gone out yet, and by the end
    // of the page they usually have. Buffering the output keeps them open.
    ob_start();

    // Save the session, then let the page out.
    register_shutdown_function(static function (): void {
        app_write_signed_session($_SESSION ?? []);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    });
}
