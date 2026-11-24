<?php
/**
 * index.php  ->  ENTRY POINT
 * ---------------------------------------------------------------------
 * This is the address the user opens:
 *     http://localhost/restaurant-stock-management/
 *
 * It does not show anything itself. It simply decides where to send
 * the visitor:
 *
 *   - not logged in  -> the login page
 *   - logged in     -> the dashboard
 *
 * The real dashboard is dashboard.php. Keeping it in a separate file
 * makes the structure clearer: index.php is the front door, and
 * dashboard.php is one of the rooms behind it.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/includes/auth.php';

start_session();

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}
