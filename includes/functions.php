<?php
/**
 * includes/functions.php
 * ---------------------------------------------------------------------
 * Small helper functions used by every page.
 * Keeping them in one file keeps the rest of the code short and readable.
 * ---------------------------------------------------------------------
 */

/**
 * Escapes text before printing it inside HTML.
 * This stops any user input from turning into HTML / JavaScript (XSS).
 */
function e($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------
   FLASH MESSAGES
   A message that is shown once on the next page (after a redirect).
   ------------------------------------------------------------------ */

/** Store a message to show on the next page. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Return (and clear) all stored messages. */
function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ------------------------------------------------------------------
   REDIRECT
   After an INSERT / UPDATE / DELETE we redirect so the page is
   reloaded fresh. This is called the "Post/Redirect/Get" pattern and
   stops the form from being submitted twice if the user refreshes.
   ------------------------------------------------------------------ */
function redirect(string $page): void
{
    header('Location: ' . $page);
    exit;
}

/* ------------------------------------------------------------------
   CSRF TOKEN
   A hidden random value put in every form. If somebody posts to our
   forms from another website, the token will not match and the request
   is rejected. Good security practice, and easy to explain.
   ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden <input> to place inside every <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Check the token that came back with the POST data. */
function csrf_valid(): bool
{
    return !empty($_POST['csrf_token'])
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/* ------------------------------------------------------------------
   FORM HELPERS
   ------------------------------------------------------------------ */

/** Remember what the user typed so the form can be filled in again. */
function remember_input(array $data): void
{
    $_SESSION['old_input'] = $data;
}

/** Read a remembered value (used when a form is redisplayed after an error). */
function old(string $key, string $default = '')
{
    return $_SESSION['old_input'][$key] ?? $default;
}

/** Forget the remembered values once the form has been re-rendered. */
function clear_old_input(): void
{
    unset($_SESSION['old_input']);
}

/* ------------------------------------------------------------------
   NUMBER AND MONEY FORMATTING
   ------------------------------------------------------------------ */

/** Format a quantity, e.g. 12.50 -> "12.5" */
function qty($value): string
{
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
}

/** Format an amount of money, e.g. 1250.5 -> "₹1,250.50" */
function money($value): string
{
    return '₹' . number_format((float) $value, 2);
}

/** Format a date as "12 Nov 2026" */
function nice_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('j M Y', $timestamp) : e($date);
}

/* ------------------------------------------------------------------
   VALIDATION HELPERS
   ------------------------------------------------------------------ */

/** Is this a valid non-negative number? */
function is_number(string $value): bool
{
    return $value !== '' && is_numeric($value) && (float) $value >= 0;
}

/** Is this a valid number greater than zero? */
function is_positive_number(string $value): bool
{
    return $value !== '' && is_numeric($value) && (float) $value > 0;
}

/**
 * Is this a real date in YYYY-MM-DD form?
 * The server checks this too, so a hand-crafted request cannot store a
 * nonsense date in the purchases table.
 */
function is_valid_date(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/** Is this date in the future? Purchases cannot be dated ahead of today. */
function is_future_date(string $value): bool
{
    return $value > date('Y-m-d');
}

/* ------------------------------------------------------------------
   STOCK HELPERS
   ------------------------------------------------------------------ */

/**
 * An ingredient is "low stock" when its available quantity has dropped
 * below the reorder level that we set for it.
 */
function is_low_stock(array $ingredient): bool
{
    return (float) $ingredient['quantity'] < (float) $ingredient['reorder_level'];
}

/** The list of ingredients that are below their reorder level. */
function low_stock_ingredients(PDO $pdo): array
{
    $statement = $pdo->prepare(
        'SELECT * FROM ingredients WHERE quantity < reorder_level ORDER BY quantity ASC, ingredient_name ASC'
    );
    $statement->execute();
    return $statement->fetchAll();
}

/** How many ingredients are currently low on stock? */
function count_low_stock(PDO $pdo): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM ingredients WHERE quantity < reorder_level'
    );
    $statement->execute();
    return (int) $statement->fetchColumn();
}

/* ------------------------------------------------------------------
   ICONS
   Small inline SVG icons so the page needs no icon font or image file.
   ------------------------------------------------------------------ */
function icon(string $name): string
{
    $icons = [
        'grid'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'truck' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h11v9H2z"/><path d="M13 10h4l4 3.5V16h-8z"/><circle cx="6.5" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg>',
        'box'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v8l-9 5-9-5V8l9-5 9 5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>',
        'cart'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h3l2.4 12.2a1.5 1.5 0 0 0 1.5 1.3h8.8a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/></svg>',
        'alert' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 16.5v.01"/></svg>',
        'plus'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
        'edit'  => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>',
        'search'=> '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>',
    ];

    return $icons[$name] ?? '';
}

/* ------------------------------------------------------------------
   LAYOUT HELPERS
   ------------------------------------------------------------------ */

/** The list of pages shown in the sidebar. */
function nav_items(): array
{
    return [
        ['file' => 'index.php',         'label' => 'Dashboard',  'icon' => 'grid'],
        ['file' => 'suppliers.php',     'label' => 'Suppliers',  'icon' => 'truck'],
        ['file' => 'ingredients.php',   'label' => 'Ingredients','icon' => 'box'],
        ['file' => 'purchases.php',     'label' => 'Purchases',  'icon' => 'cart'],
        ['file' => 'low_stock.php',     'label' => 'Low Stock',  'icon' => 'alert'],
    ];
}

/** The page name of the file currently being viewed. */
function current_page(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
}

/**
 * Percentage of stock left compared with the reorder level.
 * Used to draw the small stock meter bars.
 */
function stock_percent(float $quantity, float $reorder_level): float
{
    if ($reorder_level <= 0) {
        // No reorder level set - show the meter relative to the current stock.
        return $quantity > 0 ? 100.0 : 0.0;
    }
    $percent = ($quantity / $reorder_level) * 100;
    return max(0.0, min(100.0, $percent));
}
