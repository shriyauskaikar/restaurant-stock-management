<?php
/**
 * auth/logout.php
 * ---------------------------------------------------------------------
 * Ends the session and sends the user back to the login page.
 *
 * Logging out is done with a GET request so that the link in the
 * sidebar can be a plain <a href>. It is safe here because logging
 * out does not change or destroy any data.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';

logout();

// Back to the login page.
redirect('auth/login.php');
