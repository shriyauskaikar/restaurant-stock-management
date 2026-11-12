<?php
/**
 * low_stock.php  ->  LOW STOCK ALERTS
 * ---------------------------------------------------------------------
 * Lists every ingredient whose available quantity has fallen below its
 * reorder level, so the kitchen knows what to re-order.
 * ---------------------------------------------------------------------
 */

$pageTitle    = 'Low Stock Alerts';
$pageSubtitle = 'Ingredients that need re-ordering';

require_once __DIR__ . '/includes/header.php';

$pdo = db_connect();

/* --- Every ingredient with its stock level, so we can show the worst
   offenders first and the healthy ones underneath. ------------------ */
$allIngredients = $pdo->query(
    'SELECT i.*, COALESCE(SUM(p.quantity_purchased), 0) AS total_purchased
       FROM ingredients i
       LEFT JOIN purchases p ON p.ingredient_id = i.ingredient_id
      GROUP BY i.ingredient_id
      ORDER BY (i.quantity < i.reorder_level) DESC, i.ingredient_name ASC'
)->fetchAll();

$lowStock    = [];
$healthy     = [];
foreach ($allIngredients as $item) {
    if (is_low_stock($item)) {
        $lowStock[] = $item;
    } else {
        $healthy[] = $item;
    }
}

/* --- How much money would it cost to bring everything back up? ------
   SUM() over the shortage of each low-stock ingredient. */
$reorderCost = 0.0;
foreach ($lowStock as $item) {
    $shortage   = (float) $item['reorder_level'] - (float) $item['quantity'];
    $reorderCost += $shortage * (float) $item['price_per_unit'];
}
?>

<!-- ============ SUMMARY ============ -->
<section class="stat-grid" aria-label="Low stock summary">
    <article class="stat" style="--stat-accent: <?= count($lowStock) > 0 ? 'var(--critical)' : 'var(--good)' ?>;">
        <span class="stat-label"><span class="stat-dot"></span>Low stock items</span>
        <span class="stat-value"><?= count($lowStock) ?></span>
        <span class="stat-foot">below the reorder level</span>
    </article>

    <article class="stat" style="--stat-accent: var(--good);">
        <span class="stat-label"><span class="stat-dot"></span>Healthy items</span>
        <span class="stat-value"><?= count($healthy) ?></span>
        <span class="stat-foot">stock level is fine</span>
    </article>

    <article class="stat" style="--stat-accent: var(--warning);">
        <span class="stat-label"><span class="stat-dot"></span>Estimated reorder cost</span>
        <span class="stat-value"><?= money($reorderCost) ?></span>
        <span class="stat-foot">to bring every item back to its level</span>
    </article>

    <article class="stat" style="--stat-accent: var(--series-1);">
        <span class="stat-label"><span class="stat-dot"></span>Total ingredients</span>
        <span class="stat-value"><?= count($allIngredients) ?></span>
        <span class="stat-foot">tracked in the stock list</span>
    </article>
</section>

<!-- ============ LOW STOCK TABLE ============ -->
<section class="card" style="margin-top:20px;">
    <div class="card-head">
        <div>
            <h2 class="card-title">Items to re-order</h2>
            <p class="card-sub"><?= count($lowStock) ?> ingredient(s) have dropped below the reorder level</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="purchases.php">Record a purchase</a>
    </div>

    <?php if (empty($lowStock)): ?>
        <div class="empty">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
            </div>
            <h3>No low stock alerts</h3>
            <p>Every ingredient is currently at or above its reorder level. Nothing needs re-ordering right now.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th class="num">Available</th>
                        <th class="num">Reorder level</th>
                        <th class="num">Short by</th>
                        <th style="min-width:120px;">Stock level</th>
                        <th class="num">Est. cost</th>
                        <th class="actions-cell">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $item): ?>
                        <?php
                        $shortage   = (float) $item['reorder_level'] - (float) $item['quantity'];
                        $percent    = stock_percent((float) $item['quantity'], (float) $item['reorder_level']);
                        $itemCost   = $shortage * (float) $item['price_per_unit'];
                        ?>
                        <tr>
                            <td>
                                <div class="cell-primary"><?= e($item['ingredient_name']) ?></div>
                                <div class="cell-sub"><?= e($item['unit']) ?></div>
                            </td>
                            <td class="num">
                                <span class="badge badge-critical">
                                    <span class="badge-dot"></span>
                                    <?= qty($item['quantity']) ?> <?= e($item['unit']) ?>
                                </span>
                            </td>
                            <td class="num"><?= qty($item['reorder_level']) ?> <?= e($item['unit']) ?></td>
                            <td class="num cell-primary"><?= qty($shortage) ?> <?= e($item['unit']) ?></td>
                            <td>
                                <div class="meter">
                                    <div class="meter-fill is-low" style="width: <?= number_format($percent, 1) ?>%;"></div>
                                </div>
                            </td>
                            <td class="num"><?= money($itemCost) ?></td>
                            <td class="actions-cell">
                                <a class="btn btn-primary btn-sm" href="purchases.php">Re-order</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- ============ HEALTHY STOCK ============ -->
<?php if (!empty($healthy)): ?>
<section class="card" style="margin-top:20px;">
    <div class="card-head">
        <div>
            <h2 class="card-title">Healthy stock</h2>
            <p class="card-sub"><?= count($healthy) ?> ingredient(s) at or above the reorder level</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th class="num">Available</th>
                    <th class="num">Reorder level</th>
                    <th style="min-width:120px;">Stock level</th>
                    <th class="num">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($healthy as $item): ?>
                    <?php $percent = stock_percent((float) $item['quantity'], (float) $item['reorder_level']); ?>
                    <tr>
                        <td class="cell-primary"><?= e($item['ingredient_name']) ?></td>
                        <td class="num"><?= qty($item['quantity']) ?> <?= e($item['unit']) ?></td>
                        <td class="num"><?= qty($item['reorder_level']) ?> <?= e($item['unit']) ?></td>
                        <td>
                            <div class="meter">
                                <div class="meter-fill is-ok" style="width: <?= number_format($percent, 1) ?>%;"></div>
                            </div>
                        </td>
                        <td class="num">
                            <span class="badge badge-good"><span class="badge-dot"></span> In stock</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
