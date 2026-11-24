<?php
/**
 * auth/signup.php  ->  CREATE AN ACCOUNT
 * ---------------------------------------------------------------------
 * A visitor can register their own account here.
 *
 * The password is hashed with password_hash() in includes/auth.php and
 * stored in the `users` table in MySQL. The new user is signed in
 * immediately after registration.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';

start_session();

// Already signed in? No need to register again.
if (is_logged_in()) {
    redirect('index.php');
}

$error      = '';
$oldUser    = '';
$oldEmail   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $oldUser  = trim($_POST['username'] ?? '');
        $oldEmail = trim($_POST['email'] ?? '');

        $username = $oldUser;
        $email    = $oldEmail;
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        // --- Server-side validation ---------------------------------
        if ($username === '' || $email === '' || $password === '') {
            $error = 'Please fill in every field.';
        } elseif ($password !== $confirm) {
            $error = 'The two passwords do not match.';
        } else {
            // register_user() hashes the password and writes to MySQL.
            $result = register_user($username, $email, $password);

            if ($result['ok']) {
                flash('success', 'Account created. Welcome, ' . $result['user']['username'] . '!');
                redirect('index.php');
            }

            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account &middot; Restaurant Stock</title>
<link rel="stylesheet" href="<?= e(url('assets/css/login.css')) ?>">
</head>
<body>

<div class="login-shell">

    <!-- ============ LEFT : BRANDING (same as the login page) ============ -->
    <section class="login-brand">
        <div class="brand-inner">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 3v7a3 3 0 0 0 6 0V3"/>
                    <path d="M7 10v11"/>
                    <path d="M17 3c-1.5 2-2 4-2 6 0 1.7.8 3 2 3.5V21"/>
                </svg>
            </span>

            <h1 class="brand-title">Kitchen Stock</h1>
            <p class="brand-sub">Restaurant Food Stock Management System</p>

            <ul class="brand-points">
                <li>
                    <span class="point-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    Track every ingredient in one place
                </li>
                <li>
                    <span class="point-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    Know what to re-order before you run out
                </li>
                <li>
                    <span class="point-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    Purchases update your stock instantly
                </li>
            </ul>

            <p class="brand-foot">RDBMS Mini Project &nbsp;&middot;&nbsp; PHP &nbsp;&middot;&nbsp; MySQL</p>
        </div>
    </section>

    <!-- ============ RIGHT : THE SIGN-UP FORM ============ -->
    <section class="login-panel">
        <div class="login-card">

            <div class="login-head">
                <h2>Create your account</h2>
                <p>Sign up to open the kitchen dashboard.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <span class="alert-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round">
                            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                    </span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('auth/signup.php')) ?>" class="login-form auth-form" novalidate>
                <?= csrf_field() ?>

                <!-- --- Username --- -->
                <div class="field">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                        </span>
                        <input type="text" id="username" name="username" autocomplete="username"
                               placeholder="e.g. shriya" value="<?= e($oldUser) ?>"
                               minlength="3" maxlength="50" required autofocus>
                    </div>
                    <span class="field-note">3-50 characters: letters, numbers, dot, dash, underscore.</span>
                </div>

                <!-- --- Email --- -->
                <div class="field">
                    <label for="email">Email address</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <input type="email" id="email" name="email" autocomplete="email"
                               placeholder="you@example.com" value="<?= e($oldEmail) ?>" required>
                    </div>
                </div>

                <!-- --- Password --- -->
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="2"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        </span>
                        <input type="password" id="password" name="password"
                               autocomplete="new-password" placeholder="At least 8 characters"
                               minlength="8" required>
                        <button type="button" class="toggle-password" id="togglePassword"
                                aria-label="Show password" aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" width="18" height="18" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" width="18" height="18" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3l18 18"/><path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6.4 0 10 6 10 6a17.6 17.6 0 0 1-3.2 3.9"/>
                                <path d="M6.3 6.4A17.4 17.4 0 0 0 2 12s3.6 6 10 6a9.7 9.7 0 0 0 4-.85"/></svg>
                        </button>
                    </div>
                </div>

                <!-- --- Confirm password --- -->
                <div class="field">
                    <label for="confirm_password">Confirm password</label>
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="2"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password"
                               autocomplete="new-password" placeholder="Repeat your password"
                               minlength="8" required>
                        <button type="button" class="toggle-password" data-toggle-target="confirm_password"
                                aria-label="Show password" aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" width="18" height="18" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" width="18" height="18" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3l18 18"/><path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6.4 0 10 6 10 6a17.6 17.6 0 0 1-3.2 3.9"/>
                                <path d="M6.3 6.4A17.4 17.4 0 0 0 2 12s3.6 6 10 6a9.7 9.7 0 0 0 4-.85"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                    Create account
                </button>
            </form>

            <!-- Link back to sign in -->
            <p class="auth-switch">
                Already have an account?
                <a href="<?= e(url('auth/login.php')) ?>">Sign in</a>
            </p>

        </div>
    </section>
</div>

<script src="<?= e(url('assets/js/login.js')) ?>"></script>
</body>
</html>
