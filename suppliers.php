<?php
/**
 * suppliers.php  ->  SUPPLIER MANAGEMENT
 * ---------------------------------------------------------------------
 * Add, view, edit and delete suppliers.
 * ---------------------------------------------------------------------
 */

$pageTitle    = 'Suppliers';
$pageSubtitle = 'Companies that supply your ingredients';

require_once __DIR__ . '/includes/header.php';

$pdo = db_connect();

/* --- Read the suppliers, with the number of purchases each has ----
   This is a LEFT JOIN with COUNT() - a supplier with no purchases
   still appears, with a count of 0. */
$search = trim($_GET['q'] ?? '');
$params = [];

$sql = 'SELECT s.*, COUNT(p.purchase_id) AS purchase_count
          FROM suppliers s
          LEFT JOIN purchases p ON p.supplier_id = s.supplier_id';

if ($search !== '') {
    $sql .= ' WHERE s.supplier_name LIKE ? OR s.phone LIKE ? OR s.address LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}

$sql .= ' GROUP BY s.supplier_id
          ORDER BY s.supplier_name ASC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$suppliers = $statement->fetchAll();

/* --- Remembered values, if the add form was sent back with an error */
$oldName    = old('supplier_name');
$oldPhone   = old('phone');
$oldAddress = old('address');
clear_old_input();
?>

<div class="split">

    <!-- ============ ADD SUPPLIER FORM ============ -->
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Add a supplier</h2>
                <p class="card-sub">A company you buy ingredients from</p>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="suppliers.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_supplier">

                <div class="form-grid form-grid-1">
                    <div class="field">
                        <label for="supplier_name">Supplier name <span class="req">*</span></label>
                        <input type="text" id="supplier_name" name="supplier_name" required
                               placeholder="e.g. Green Valley Farms" value="<?= e($oldName) ?>">
                    </div>

                    <div class="field">
                        <label for="phone">Phone number <span class="req">*</span></label>
                        <input type="tel" id="phone" name="phone" required
                               placeholder="e.g. 9876543210" value="<?= e($oldPhone) ?>">
                    </div>

                    <div class="field">
                        <label for="address">Address <span class="req">*</span></label>
                        <textarea id="address" name="address" required
                                  placeholder="e.g. 12 Market Road, Pune"><?= e($oldAddress) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Add supplier
                    </button>
                </div>
            </form>
        </div>
    </article>

    <!-- ============ SUPPLIER LIST ============ -->
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">All suppliers</h2>
                <p class="card-sub" id="searchCount"><?= count($suppliers) ?> record(s)</p>
            </div>
        </div>

        <div class="card-body">
            <!-- Search box -->
            <form class="search-bar" method="get" action="suppliers.php">
                <div class="search-wrap">
                    <span class="search-icon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/>
                             <path d="m20 20-3.2-3.2"/></svg>
                    </span>
                    <input type="search" id="tableSearch" name="q"
                           data-target="#supplierTable"
                           placeholder="Search by name, phone or address..."
                           value="<?= e($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-ghost">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="suppliers.php" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (empty($suppliers)): ?>
                <div class="empty" id="noResults" <?= $search !== '' ? '' : 'style="display:none"' ?>>
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 7h11v9H2z"/><path d="M13 10h4l4 3.5V16h-8z"/>
                            <circle cx="6.5" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg>
                    </div>
                    <h3><?= $search !== '' ? 'No supplier matches your search' : 'No suppliers yet' ?></h3>
                    <p><?= $search !== ''
                        ? 'Try a different name, phone number or address.'
                        : 'Use the form on the left to add your first supplier.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data" id="supplierTable">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th class="num">Purchases</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td>
                                        <div class="cell-primary"><?= e($supplier['supplier_name']) ?></div>
                                        <div class="cell-sub">ID #<?= (int) $supplier['supplier_id'] ?></div>
                                    </td>
                                    <td><?= e($supplier['phone']) ?></td>
                                    <td><?= e($supplier['address']) ?></td>
                                    <td class="num">
                                        <?php if ((int) $supplier['purchase_count'] > 0): ?>
                                            <span class="badge badge-neutral"><?= (int) $supplier['purchase_count'] ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-neutral">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="row-actions">
                                            <!-- Edit: opens the modal and fills it with this row's values -->
                                            <button type="button" class="btn-icon warning"
                                                    data-modal="editSupplierModal"
                                                    data-fill="editSupplierForm"
                                                    data-field-supplier_id="<?= (int) $supplier['supplier_id'] ?>"
                                                    data-field-supplier_name="<?= e($supplier['supplier_name']) ?>"
                                                    data-field-phone="<?= e($supplier['phone']) ?>"
                                                    data-field-address="<?= e($supplier['address']) ?>"
                                                    title="Edit supplier">
                                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
                                                     stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                            </button>

                                            <!-- Delete: only allowed when there are no purchases -->
                                            <form method="post" action="suppliers.php" style="display:inline;"
                                                  data-confirm="Delete supplier &quot;<?= e($supplier['supplier_name']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_supplier">
                                                <input type="hidden" name="supplier_id" value="<?= (int) $supplier['supplier_id'] ?>">
                                                <button type="submit" class="btn-icon danger"
                                                        title="<?= (int) $supplier['purchase_count'] > 0
                                                            ? 'Cannot delete: this supplier has purchases'
                                                            : 'Delete supplier' ?>"
                                                        <?= (int) $supplier['purchase_count'] > 0 ? 'disabled style="opacity:.4;cursor:not-allowed"' : '' ?>>
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

<!-- ============ EDIT SUPPLIER MODAL ============ -->
<div class="modal" id="editSupplierModal" role="dialog" aria-modal="true" aria-labelledby="editSupplierTitle">
    <div class="modal-panel">
        <div class="modal-head">
            <h2 class="modal-title" id="editSupplierTitle">Edit supplier</h2>
            <button type="button" class="modal-close" data-close-modal="editSupplierModal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="suppliers.php" id="editSupplierForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_supplier">
                <input type="hidden" name="supplier_id" id="edit_supplier_id">

                <div class="form-grid form-grid-1">
                    <div class="field">
                        <label for="edit_supplier_name">Supplier name <span class="req">*</span></label>
                        <input type="text" id="edit_supplier_name" name="supplier_name" required>
                    </div>
                    <div class="field">
                        <label for="edit_phone">Phone number <span class="req">*</span></label>
                        <input type="tel" id="edit_phone" name="phone" required>
                    </div>
                    <div class="field">
                        <label for="edit_address">Address <span class="req">*</span></label>
                        <textarea id="edit_address" name="address" required></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-ghost" data-close-modal="editSupplierModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
