<?php
/**
 * index.php  ->  DASHBOARD
 * ---------------------------------------------------------------------
 * Shows the summary cards (counts), a small chart of stock value and
 * the list of ingredients that are low on stock.
 * ---------------------------------------------------------------------
 */

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Overview of your restaurant stock';

require_once __DIR__ . '/includes/header.php';

$pdo = db_connect();

/* --- Summary counts (aggregate function COUNT) --------------------- */
$totalIngredients = (int) $pdo->query('SELECT COUNT(*) FROM ingredients')->fetchColumn();
$totalSuppliers  = (int) $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
$totalPurchases  = (int) $pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn();
$lowStockCount   = count_low_stock($pdo);

/* --- Total money spent on purchases (aggregate function SUM) ------ */
$totalSpend = (float) $pdo->query('SELECT COALESCE(SUM(total_cost), 0) FROM purchases')->fetchColumn();

/* --- Stock value: SUM(quantity * price_per_unit) ------------------- */
$stockValue = (float) $pdo->query(
    'SELECT COALESCE(SUM(quantity * price_per_unit), 0) FROM ingredients'
)->fetchColumn();

/* --- Top ingredients by value, for the bar chart ------------------- */
$topIngredients = $pdo->query(
    'SELECT ingredient_name, unit, quantity, price_per_unit,
            (quantity * price_per_unit) AS stock_value
       FROM ingredients
      WHERE quantity > 0
      ORDER BY stock_value DESC
      LIMIT 6'
)->fetchAll();

$maxValue = 0.0;
foreach ($topIngredients as $row) {
    $maxValue = max($maxValue, (float) $row['stock_value']);
}

/* --- Low stock list ------------------------------------------------ */
$lowStock = low_stock_ingredients($pdo);

/* --- Recent purchases (JOIN across all three tables) --------------- */
$recentPurchases = $pdo->query(
    'SELECT p.purchase_id, p.purchase_date, p.quantity_purchased, p.total_cost,
            s.supplier_name, i.ingredient_name, i.unit
       FROM purchases p
       JOIN suppliers   s ON p.supplier_id   = s.supplier_id
       JOIN ingredients i ON p.ingredient_id = i.ingredient_id
      ORDER BY p.purchase_date DESC, p.purchase_id DESC
      LIMIT 6'
)->fetchAll();
?>

<!-- ============ SUMMARY CARDS ============ -->
<section class="stat-grid" aria-label="Summary">
    <article class="stat" style="--stat-accent: var(--series-2);">
        <span class="stat-label"><span class="stat-dot"></span>Total ingredients</span>
        <span class="stat-value"><?= $totalIngredients ?></span>
        <span class="stat-foot">items in the stock list</span>
    </article>

    <article class="stat" style="--stat-accent: var(--series-1);">
        <span class="stat-label"><span class="stat-dot"></span>Total suppliers</span>
        <span class="stat-value"><?= $totalSuppliers ?></span>
        <span class="stat-foot">companies you buy from</span>
    </article>

    <article class="stat" style="--stat-accent: var(--text-muted);">
        <span class="stat-label"><span class="stat-dot"></span>Total purchases</span>
        <span class="stat-value"><?= $totalPurchases ?></span>
        <span class="stat-foot"><?= money($totalSpend) ?> spent so far</span>
    </article>

    <article class="stat" style="--stat-accent: <?= $lowStockCount > 0 ? 'var(--critical)' : 'var(--series-3)' ?>;">
        <span class="stat-label"><span class="stat-dot"></span>Low stock alerts</span>
        <span class="stat-value"><?= $lowStockCount ?></span>
        <span class="stat-foot"><?= $lowStockCount > 0 ? 'needs reordering' : 'everything is well stocked' ?></span>
    </article>
</section>

<!-- ============ CHART + LOW STOCK ============ -->
<section class="split-wide">

    <!-- Stock value by ingredient (single series, so no legend needed) -->
    <article class="card chart-card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Stock value by ingredient</h2>
                <p class="card-sub">Quantity &times; price per unit &mdash; total <?= money($stockValue) ?></p>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($topIngredients)): ?>
                <div class="empty">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                    </div>
                    <h3>No stock to chart yet</h3>
                    <p>Add a few ingredients, then record a purchase to see their stock value here.</p>
                </div>
            <?php else: ?>
                <div class="chart-bars">
                    <?php foreach ($topIngredients as $row): ?>
                        <?php
                        $value  = (float) $row['stock_value'];
                        $width  = $maxValue > 0 ? ($value / $maxValue) * 100 : 0;
                        // A low-stock ingredient is drawn in the critical colour.
                        $isLow  = (float) $row['quantity'] < (float) ($row['reorder_level'] ?? 0);
                        $color  = $isLow ? 'var(--critical)' : 'var(--series-1)';
                        ?>
                        <div class="chart-row">
                            <span class="chart-label" title="<?= e($row['ingredient_name']) ?>">
                                <?= e($row['ingredient_name']) ?>
                            </span>
                            <div class="chart-track">
                                <div class="chart-bar" style="width: <?= number_format($width, 1) ?>%; --bar-color: <?= $color ?>;"></div>
                            </div>
                            <span class="chart-value"><?= money($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-axis">
                    <span>₹0</span>
                    <span>Highest: <?= money($maxValue) ?></span>
                </div>
                <p class="chart-legend" style="margin-top:10px">
                    <span class="legend-item">
                        <span class="legend-swatch" style="background: var(--series-1)"></span> Stocked normally
                    </span>
                    <span class="legend-item">
                        <span class="legend-swatch" style="background: var(--critical)"></span> Below reorder level
                    </span>
                </p>
            <?php endif; ?>
        </div>
    </article>

    <!-- Low stock panel -->
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Low stock ingredients</h2>
                <p class="card-sub"><?= $lowStockCount ?> item(s) below the reorder level</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="low_stock.php">View all</a>
        </div>
        <div class="card-body">
            <?php if (empty($lowStock)): ?>
                <div class="empty">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                    </div>
                    <h3>Everything is well stocked</h3>
                    <p>No ingredient has dropped below its reorder level.</p>
                </div>
            <?php else: ?>
                <div class="lowstock-list">
                    <?php foreach ($lowStock as $row): ?>
                        <div class="lowstock-item">
                            <div style="min-width:0; flex:1;">
                                <div class="lowstock-name"><?= e($row['ingredient_name']) ?></div>
                                <div class="lowstock-meta">
                                    Reorder level: <?= qty($row['reorder_level']) ?> <?= e($row['unit']) ?>
                                </div>
                            </div>
                            <div class="lowstock-qty">
                                <div class="badge badge-critical">
                                    <span class="badge-dot"></span>
                                    <?= qty($row['quantity']) ?> <?= e($row['unit']) ?> left
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
</section>

<!-- ============ RECENT PURCHASES ============ -->
<section class="card" style="margin-top:20px;">
    <div class="card-head">
        <div>
            <h2 class="card-title">Recent purchases</h2>
            <p class="card-sub">The last <?= count($recentPurchases) ?> purchase record(s)</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="purchases.php">View all</a>
    </div>

    <?php if (empty($recentPurchases)): ?>
        <div class="empty">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/>
                    <path d="M2 3h3l2.4 12.2a1.5 1.5 0 0 0 1.5 1.3h8.8a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/></svg>
            </div>
            <h3>No purchases recorded yet</h3>
            <p>Record your first purchase and the stock levels will update automatically.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Ingredient</th>
                        <th class="num">Quantity</th>
                        <th class="num">Total cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPurchases as $row): ?>
                        <tr>
                            <td><?= nice_date($row['purchase_date']) ?></td>
                            <td class="cell-primary"><?= e($row['supplier_name']) ?></td>
                            <td><?= e($row['ingredient_name']) ?></td>
                            <td class="num"><?= qty($row['quantity_purchased']) ?> <?= e($row['unit']) ?></td>
                            <td class="num cell-primary"><?= money($row['total_cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
