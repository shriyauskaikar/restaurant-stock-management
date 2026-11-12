<?php
/**
 * ingredients.php  ->  INGREDIENT MANAGEMENT
 * ---------------------------------------------------------------------
 * Add, view, edit and delete ingredients and see the current stock.
 * ---------------------------------------------------------------------
 */

$pageTitle    = 'Ingredients';
$pageSubtitle = 'Your raw materials and the stock you have left';

require_once __DIR__ . '/includes/header.php';

$pdo = db_connect();

/* --- Read the ingredients, with how many purchases each has --------
   LEFT JOIN + COUNT(): an ingredient with no purchases still shows. */
$search = trim($_GET['q'] ?? '');
$filter = $_GET['stock'] ?? 'all';   // all | low
$params = [];

$sql = 'SELECT i.*, COUNT(p.purchase_id) AS purchase_count
          FROM ingredients i
          LEFT JOIN purchases p ON p.ingredient_id = i.ingredient_id';

$where = [];
if ($search !== '') {
    $where[] = '(i.ingredient_name LIKE ? OR i.unit LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like];
}
if ($filter === 'low') {
    $where[] = 'i.quantity < i.reorder_level';
}
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY i.ingredient_id
          ORDER BY i.ingredient_name ASC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$ingredients = $statement->fetchAll();

/* --- Remembered values after a failed add ------------------------- */
$oldName    = old('ingredient_name');
$oldUnit    = old('unit');
$oldQty     = old('quantity');
$oldPrice   = old('price_per_unit');
$oldReorder = old('reorder_level');
clear_old_input();

/* --- Units offered in the dropdown -------------------------------- */
$units = ['kg', 'g', 'litres', 'ml', 'pieces', 'packs', 'dozen'];
?>

<div class="split-wide">

    <!-- ============ ADD INGREDIENT FORM ============ -->
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Add an ingredient</h2>
                <p class="card-sub">One record per ingredient</p>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="ingredients.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_ingredient">

                <div class="form-grid form-grid-2">
                    <div class="field field-full">
                        <label for="ingredient_name">Ingredient name <span class="req">*</span></label>
                        <input type="text" id="ingredient_name" name="ingredient_name" required
                               placeholder="e.g. Basmati Rice" value="<?= e($oldName) ?>">
                    </div>

                    <div class="field">
                        <label for="quantity">Current stock <span class="req">*</span></label>
                        <input type="number" id="quantity" name="quantity" step="0.01" min="0" required
                               placeholder="0" value="<?= e($oldQty) ?>">
                    </div>

                    <div class="field">
                        <label for="unit">Unit <span class="req">*</span></label>
                        <select id="unit" name="unit" required>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= e($unit) ?>" <?= $oldUnit === $unit ? 'selected' : '' ?>>
                                    <?= e($unit) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="price_per_unit">Price per unit <span class="req">*</span></label>
                        <input type="number" id="price_per_unit" name="price_per_unit" step="0.01" min="0" required
                               placeholder="0.00" value="<?= e($oldPrice) ?>">
                    </div>

                    <div class="field">
                        <label for="reorder_level">Reorder level <span class="req">*</span></label>
                        <input type="number" id="reorder_level" name="reorder_level" step="0.01" min="0" required
                               placeholder="0.00" value="<?= e($oldReorder) !== '' ? e($oldReorder) : '0' ?>">
                        <span class="field-hint">Alert when stock drops below this</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Add ingredient
                    </button>
                </div>
            </form>
        </div>
    </article>

    <!-- ============ INGREDIENT LIST ============ -->
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">All ingredients</h2>
                <p class="card-sub" id="searchCount"><?= count($ingredients) ?> record(s)</p>
            </div>
        </div>

        <div class="card-body">
            <!-- Search + filter -->
            <form class="search-bar" method="get" action="ingredients.php">
                <div class="search-wrap">
                    <span class="search-icon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/>
                             <path d="m20 20-3.2-3.2"/></svg>
                    </span>
                    <input type="search" id="tableSearch" name="q"
                           data-target="#ingredientTable"
                           placeholder="Search by ingredient or unit..."
                           value="<?= e($search) ?>" autocomplete="off">
                </div>

                <select name="stock" aria-label="Filter by stock level" style="width:auto;">
                    <option value="all"   <?= $filter === 'all' ? 'selected' : '' ?>>All stock levels</option>
                    <option value="low"   <?= $filter === 'low' ? 'selected' : '' ?>>Low stock only</option>
                </select>

                <button type="submit" class="btn btn-ghost">Search</button>
                <?php if ($search !== '' || $filter !== 'all'): ?>
                    <a href="ingredients.php" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (empty($ingredients)): ?>
                <div class="empty" id="noResults" <?= ($search !== '' || $filter !== 'all') ? '' : 'style="display:none"' ?>>
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 8v8l-9 5-9-5V8l9-5 9 5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
                    </div>
                    <h3>No ingredients found</h3>
                    <p><?= ($search !== '' || $filter !== 'all')
                        ? 'Try a different search, or clear the filters.'
                        : 'Use the form on the left to add your first ingredient.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data" id="ingredientTable">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th class="num">Stock</th>
                                <th style="min-width:120px;">Level</th>
                                <th class="num">Price/unit</th>
                                <th class="num">Reorder at</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingredients as $item): ?>
                                <?php
                                $low     = is_low_stock($item);
                                $percent = stock_percent((float) $item['quantity'], (float) $item['reorder_level']);
                                $meterClass = $low ? 'is-low' : ($percent < 150 ? 'is-near' : 'is-ok');
                                ?>
                                <tr>
                                    <td>
                                        <div class="cell-primary"><?= e($item['ingredient_name']) ?></div>
                                        <div class="cell-sub">
                                            ID #<?= (int) $item['ingredient_id'] ?>
                                            &middot; <?= (int) $item['purchase_count'] ?> purchase(s)
                                        </div>
                                    </td>
                                    <td class="num">
                                        <span class="cell-primary"><?= qty($item['quantity']) ?></span>
                                        <span class="cell-sub" style="text-align:right;"><?= e($item['unit']) ?></span>
                                    </td>
                                    <td>
                                        <div class="meter" title="<?= qty($item['quantity']) ?> <?= e($item['unit']) ?> of <?= qty($item['reorder_level']) ?>">
                                            <div class="meter-fill <?= $meterClass ?>" style="width: <?= number_format($percent, 1) ?>%;"></div>
                                        </div>
                                        <?php if ($low): ?>
                                            <span class="badge badge-critical" style="margin-top:5px;">
                                                <span class="badge-dot"></span> Low stock
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-good" style="margin-top:5px;">
                                                <span class="badge-dot"></span> In stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num"><?= money($item['price_per_unit']) ?></td>
                                    <td class="num"><?= qty($item['reorder_level']) ?> <?= e($item['unit']) ?></td>
                                    <td class="actions-cell">
                                        <div class="row-actions">
                                            <button type="button" class="btn-icon warning"
                                                    data-modal="editIngredientModal"
                                                    data-fill="editIngredientForm"
                                                    data-field-ingredient_id="<?= (int) $item['ingredient_id'] ?>"
                                                    data-field-ingredient_name="<?= e($item['ingredient_name']) ?>"
                                                    data-field-quantity="<?= qty($item['quantity']) ?>"
                                                    data-field-unit="<?= e($item['unit']) ?>"
                                                    data-field-price_per_unit="<?= qty($item['price_per_unit']) ?>"
                                                    data-field-reorder_level="<?= qty($item['reorder_level']) ?>"
                                                    title="Edit ingredient">
                                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                                                     stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                            </button>

                                            <form method="post" action="ingredients.php" style="display:inline;"
                                                  data-confirm="Delete ingredient &quot;<?= e($item['ingredient_name']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_ingredient">
                                                <input type="hidden" name="ingredient_id" value="<?= (int) $item['ingredient_id'] ?>">
                                                <button type="submit" class="btn-icon danger"
                                                        title="<?= (int) $item['purchase_count'] > 0
                                                            ? 'Cannot delete: this ingredient has purchases'
                                                            : 'Delete ingredient' ?>"
                                                        <?= (int) $item['purchase_count'] > 0 ? 'disabled style="opacity:.4;cursor:not-allowed"' : '' ?>>
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

<!-- ============ EDIT INGREDIENT MODAL ============ -->
<div class="modal" id="editIngredientModal" role="dialog" aria-modal="true" aria-labelledby="editIngredientTitle">
    <div class="modal-panel">
        <div class="modal-head">
            <h2 class="modal-title" id="editIngredientTitle">Edit ingredient</h2>
            <button type="button" class="modal-close" data-close-modal="editIngredientModal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="ingredients.php" id="editIngredientForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_ingredient">
                <input type="hidden" name="ingredient_id" id="edit_ingredient_id">

                <div class="form-grid form-grid-2">
                    <div class="field field-full">
                        <label for="edit_ingredient_name">Ingredient name <span class="req">*</span></label>
                        <input type="text" id="edit_ingredient_name" name="ingredient_name" required>
                    </div>
                    <div class="field">
                        <label for="edit_quantity">Current stock <span class="req">*</span></label>
                        <input type="number" id="edit_quantity" name="quantity" step="0.01" min="0" required>
                    </div>
                    <div class="field">
                        <label for="edit_unit">Unit <span class="req">*</span></label>
                        <select id="edit_unit" name="unit" required>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= e($unit) ?>"><?= e($unit) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="edit_price_per_unit">Price per unit <span class="req">*</span></label>
                        <input type="number" id="edit_price_per_unit" name="price_per_unit" step="0.01" min="0" required>
                    </div>
                    <div class="field">
                        <label for="edit_reorder_level">Reorder level <span class="req">*</span></label>
                        <input type="number" id="edit_reorder_level" name="reorder_level" step="0.01" min="0" required>
                        <span class="field-hint">Alert when stock drops below this</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-ghost" data-close-modal="editIngredientModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
