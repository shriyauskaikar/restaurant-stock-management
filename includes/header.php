<?php
/**
 * includes/header.php
 * ---------------------------------------------------------------------
 * Opens the HTML page and prints the sidebar navigation.
 * Every page includes this file at the top.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

app_start_session();

// Handle any POSTed form before the page is drawn.
require_once __DIR__ . '/actions.php';

$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> &middot; Restaurant Stock</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 3v7a3 3 0 0 0 6 0V3"/>
                <path d="M7 10v11"/>
                <path d="M17 3c-1.5 2-2 4-2 6 0 1.7.8 3 2 3.5V21"/>
            </svg>
        </span>
        <span class="brand-text">
            <strong>Kitchen Stock</strong>
            <small>Restaurant Inventory</small>
        </span>
    </div>

    <nav class="nav" aria-label="Main navigation">
        <?php foreach (nav_items() as $item): ?>
            <?php $isActive = (current_page() === $item['file']); ?>
            <a class="nav-link<?= $isActive ? ' is-active' : '' ?>"
               href="<?= e($item['file']) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true"><?= icon($item['icon']) ?></span>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <p class="foot-note">RDBMS Mini Project</p>
        <p class="foot-sub">MySQL &middot; PHP &middot; HTML</p>
    </div>
</aside>

<!-- Mobile sidebar toggle -->
<button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-expanded="false">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
</button>

<!-- ============ MAIN CONTENT ============ -->
<main class="main">
    <header class="topbar">
        <div>
            <h1 class="page-title"><?= e($pageTitle) ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
                <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
            <?php endif; ?>
        </div>
        <div class="topbar-date">
            <span class="dot-live" aria-hidden="true"></span>
            <?= date('D, j M Y') ?>
        </div>
    </header>

    <?php
    // Show any success / error messages
    $messages = flash_messages();
    if ($messages):
        foreach ($messages as $message):
            $isError = ($message['type'] === 'error');
    ?>
        <div class="alert alert-<?= $isError ? 'error' : 'success' ?>" role="status">
            <span class="alert-icon" aria-hidden="true">
                <?= $isError
                    ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>'
                    : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>' ?>
            </span>
            <span><?= e($message['message']) ?></span>
            <button class="alert-close" type="button" aria-label="Dismiss message" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endforeach; endif; ?>
