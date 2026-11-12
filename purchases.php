<?php
/**
 * purchases.php  ->  PURCHASE MANAGEMENT
 * ---------------------------------------------------------------------
 * Record a purchase (the stock is updated automatically) and see the
 * full purchase history.
 * ---------------------------------------------------------------------
 */

$pageTitle    = 'Purchases';
$pageSubtitle = 'Record what you buy and track your stock';

require_once __DIR__ . '/includes/header.php';

$pdo = db_connect();

/* --- Lists for the two dropdowns ---------------------------------- */
$suppliers = $pdo->query('SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name')->fetchAll();
$ingredients = $pdo->query(
    'SELECT ingredient_id, ingredient_name, unit, price_per_unit, quantity
       FROM ingredients ORDER BY ingredient_name'
)->fetchAll();

/* --- Purchase history, joining all three tables --------------------
   JOIN suppliers + JOIN ingredients shows the supplier name and the
   ingredient name instead of just the id numbers. */
$search = trim($_GET['q'] ?? '');
$params = [];

$sql = 'SELECT p.*, s.supplier_name, i.ingredient_name, i.unit
          FROM purchases p
          JOIN suppliers   s ON p.supplier_id   = s.supplier_id
          JOIN ingredients i ON p.ingredient_id = i.ingredient_id';

if ($search !== '') {
    $sql .= ' WHERE s.supplier_name LIKE ? OR i.ingredient_name LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like];
}

$sql .= ' ORDER BY p.purchase_date DESC, p.purchase_id DESC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$purchases = $statement->fetchAll();

/* --- Totals for the summary line ---------------------------------- */
$totalSpent = 0.0;
$totalQty   = 0.0;
foreach ($purchases as $purchase) {
    $totalSpent += (float) $purchase['total_cost'];
    $totalQty   += (float) $purchase['quantity_purchased'];
}
?>

<div class="split-wide">

    <!-- ============ RECORD A PURCHASE ============ -->
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Record a purchase</h2>
                <p class="card-sub">The stock is updated automatically</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($suppliers) || empty($ingredients)): ?>
                <div class="empty">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                            <path d="M12 9v4M12 16.5v.01"/></svg>
                    </div>
                    <h3>Add a supplier and an ingredient first</h3>
                    <p>A purchase needs both a supplier and an ingredient before it can be recorded.</p>
                    <div style="margin-top:16px; display:flex; gap:8px; justify-content:center;">
                        <a class="btn btn-ghost btn-sm" href="suppliers.php">Go to suppliers</a>
                        <a class="btn btn-ghost btn-sm" href="ingredients.php">Go to ingredients</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="post" action="purchases.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_purchase">

                    <div class="form-grid form-grid-1">
                        <div class="field">
                            <label for="supplier_id">Supplier <span class="req">*</span></label>
                            <select id="supplier_id" name="supplier_id" required>
                                <option value="">-- choose a supplier --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= (int) $supplier['supplier_id'] ?>">
                                        <?= e($supplier['supplier_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="ingredient_id">Ingredient <span class="req">*</span></label>
                            <select id="ingredient_id" name="ingredient_id" required>
                                <option value="">-- choose an ingredient --</option>
                                <?php foreach ($ingredients as $item): ?>
                                    <option value="<?= (int) $item['ingredient_id'] ?>"
                                            data-price="<?= qty($item['price_per_unit']) ?>">
                                        <?= e($item['ingredient_name']) ?>
                                        (<?= e($item['unit']) ?>, stock: <?= qty($item['quantity']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint" id="priceNote">Choose an ingredient to see its price per unit.</span>
                        </div>

                        <div class="field">
                            <label for="quantity_purchased">Quantity purchased <span class="req">*</span></label>
                            <input type="number" id="quantity_purchased" name="quantity_purchased"
                                   step="0.01" min="0.01" required placeholder="e.g. 25">
                        </div>

                        <div class="field">
                            <label for="purchase_date">Purchase date <span class="req">*</span></label>
                            <input type="date" id="purchase_date" name="purchase_date" required
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="field">
                            <label>Total cost (auto-calculated)</label>
                            <div class="readout is-highlight" id="totalCost">â‚¹0.00</div>
                            <span class="field-hint">Quantity &times; price per unit</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5"/></svg>
                            Save purchase
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </article>

    <!-- ============ PURCHASE HISTORY ============ -->
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Purchase history</h2>
                <p class="card-sub" id="searchCount">
                    <?= count($purchases) ?> record(s) &middot; <?= money($totalSpent) ?> total
                </p>
            </div>
        </div>

        <div class="card-body">
            <form class="search-bar" method="get" action="purchases.php">
                <div class="search-wrap">
                    <span class="search-icon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/>
                             <path d="m20 20-3.2-3.2"/></svg>
                    </span>
                    <input type="search" id="tableSearch" name="q"
                           data-target="#purchaseTable"
                           placeholder="Search by supplier or ingredient..."
                           value="<?= e($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-ghost">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="purchases.php" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (empty($purchases)): ?>
                <div class="empty" id="noResults" <?= $search !== '' ? '' : 'style="display:none"' ?>>
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/>
                            <path d="M2 3h3l2.4 12.2a1.5 1.5 0 0 0 1.5 1.3h8.8a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/></svg>
                    </div>
                    <h3><?= $search !== '' ? 'No purchase matches your search' : 'No purchases yet' ?></h3>
                    <p><?= $search !== ''
                        ? 'Try a different supplier or ingredient name.'
                        : 'Record your first purchase using the form on the left.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data" id="purchaseTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Ingredient</th>
                                <th class="num">Quantity</th>
                                <th class="num">Total cost</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td class="cell-sub"><?= (int) $purchase['purchase_id'] ?></td>
                                    <td><?= nice_date($purchase['purchase_date']) ?></td>
                                    <td class="cell-primary"><?= e($purchase['supplier_name']) ?></td>
                                    <td><?= e($purchase['ingredient_name']) ?></td>
                                    <td class="num"><?= qty($purchase['quantity_purchased']) ?> <?= e($purchase['unit']) ?></td>
                                    <td class="num cell-primary"><?= money($purchase['total_cost']) ?></td>
                                    <td class="actions-cell">
                                        <div class="row-actions">
                                            <form method="post" action="purchases.php" style="display:inline;"
                                                  data-confirm="Delete this purchase? The stock will be reduced again.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_purchase">
                                                <input type="hidden" name="purchase_id" value="<?= (int) $purchase['purchase_id'] ?>">
                                                <button type="submit" class="btn-icon danger" title="Delete purchase">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                                                         stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </article>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
