<?php
/**
 * includes/actions.php
 * ---------------------------------------------------------------------
 * All the write operations (INSERT / UPDATE / DELETE) live here.
 *
 * Every page posts its form to itself, including a hidden "action" value.
 * This file works out which action was requested and performs it.
 * ---------------------------------------------------------------------
 */

$pdo = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return; // nothing to do on a GET request
}

// --- Security check: the CSRF token must match, otherwise stop. -----
if (!csrf_valid()) {
    flash('error', 'Your session expired. Please try again.');
    redirect('index.php');
}

$action = $_POST['action'] ?? '';

/* ==================================================================
   SUPPLIER ACTIONS
   ================================================================== */

if ($action === 'add_supplier') {
    $name    = trim($_POST['supplier_name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '' || $phone === '' || $address === '') {
        flash('error', 'Please fill in the supplier name, phone and address.');
        remember_input($_POST);
        redirect('suppliers.php');
    }

    // INSERT with a prepared statement
    $statement = $pdo->prepare(
        'INSERT INTO suppliers (supplier_name, phone, address) VALUES (?, ?, ?)'
    );
    $statement->execute([$name, $phone, $address]);

    flash('success', 'Supplier "' . $name . '" was added successfully.');
    redirect('suppliers.php');
}

if ($action === 'update_supplier') {
    $id      = (int) ($_POST['supplier_id'] ?? 0);
    $name    = trim($_POST['supplier_name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($id <= 0 || $name === '' || $phone === '' || $address === '') {
        flash('error', 'Please fill in every field before saving.');
        redirect('suppliers.php');
    }

    // UPDATE with a prepared statement
    $statement = $pdo->prepare(
        'UPDATE suppliers SET supplier_name = ?, phone = ?, address = ? WHERE supplier_id = ?'
    );
    $statement->execute([$name, $phone, $address, $id]);

    flash('success', 'Supplier details were updated.');
    redirect('suppliers.php');
}

if ($action === 'delete_supplier') {
    $id = (int) ($_POST['supplier_id'] ?? 0);

    // Check that no purchase uses this supplier (referential integrity check)
    $check = $pdo->prepare('SELECT COUNT(*) FROM purchases WHERE supplier_id = ?');
    $check->execute([$id]);
    $purchaseCount = (int) $check->fetchColumn();

    if ($purchaseCount > 0) {
        flash('error', 'This supplier cannot be deleted because it has ' . $purchaseCount
            . ' purchase record(s). Delete the purchases first.');
        redirect('suppliers.php');
    }

    // DELETE with a prepared statement
    $statement = $pdo->prepare('DELETE FROM suppliers WHERE supplier_id = ?');
    $statement->execute([$id]);

    flash('success', 'Supplier was deleted.');
    redirect('suppliers.php');
}

/* ==================================================================
   INGREDIENT ACTIONS
   ================================================================== */

if ($action === 'add_ingredient') {
    $name         = trim($_POST['ingredient_name'] ?? '');
    $unit         = trim($_POST['unit'] ?? '');
    $quantity     = trim($_POST['quantity'] ?? '');
    $price        = trim($_POST['price_per_unit'] ?? '');
    $reorderLevel = trim($_POST['reorder_level'] ?? '0');

    if ($name === '' || $unit === '') {
        flash('error', 'Please enter the ingredient name and the unit.');
        remember_input($_POST);
        redirect('ingredients.php');
    }
    if (!is_number($quantity) || !is_number($price) || !is_number($reorderLevel)) {
        flash('error', 'Quantity, price and reorder level must be valid numbers (0 or more).');
        remember_input($_POST);
        redirect('ingredients.php');
    }

    try {
        $statement = $pdo->prepare(
            'INSERT INTO ingredients (ingredient_name, quantity, unit, price_per_unit, reorder_level)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$name, (float) $quantity, $unit, (float) $price, (float) $reorderLevel]);

        flash('success', 'Ingredient "' . $name . '" was added.');
        redirect('ingredients.php');
    } catch (PDOException $e) {
        // 23000 = integrity constraint violation (e.g. duplicate name)
        if ($e->getCode() === '23000') {
            flash('error', 'An ingredient called "' . $name . '" already exists.');
        } else {
            flash('error', 'Could not save the ingredient. Please try again.');
        }
        remember_input($_POST);
        redirect('ingredients.php');
    }
}

if ($action === 'update_ingredient') {
    $id           = (int) ($_POST['ingredient_id'] ?? 0);
    $name         = trim($_POST['ingredient_name'] ?? '');
    $unit         = trim($_POST['unit'] ?? '');
    $quantity     = trim($_POST['quantity'] ?? '');
    $price        = trim($_POST['price_per_unit'] ?? '');
    $reorderLevel = trim($_POST['reorder_level'] ?? '0');

    if ($id <= 0 || $name === '' || $unit === ''
        || !is_number($quantity) || !is_number($price) || !is_number($reorderLevel)) {
        flash('error', 'Please check every field before saving.');
        redirect('ingredients.php');
    }

    try {
        $statement = $pdo->prepare(
            'UPDATE ingredients
                SET ingredient_name = ?, quantity = ?, unit = ?, price_per_unit = ?, reorder_level = ?
              WHERE ingredient_id = ?'
        );
        $statement->execute([$name, (float) $quantity, $unit, (float) $price, (float) $reorderLevel, $id]);

        flash('success', 'Ingredient "' . $name . '" was updated.');
        redirect('ingredients.php');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('error', 'An ingredient called "' . $name . '" already exists.');
        } else {
            flash('error', 'Could not update the ingredient. Please try again.');
        }
        redirect('ingredients.php');
    }
}

if ($action === 'delete_ingredient') {
    $id = (int) ($_POST['ingredient_id'] ?? 0);

    // Check that no purchase uses this ingredient
    $check = $pdo->prepare('SELECT COUNT(*) FROM purchases WHERE ingredient_id = ?');
    $check->execute([$id]);
    $purchaseCount = (int) $check->fetchColumn();

    if ($purchaseCount > 0) {
        flash('error', 'This ingredient cannot be deleted because it has ' . $purchaseCount
            . ' purchase record(s). Delete the purchases first.');
        redirect('ingredients.php');
    }

    $statement = $pdo->prepare('DELETE FROM ingredients WHERE ingredient_id = ?');
    $statement->execute([$id]);

    flash('success', 'Ingredient was deleted.');
    redirect('ingredients.php');
}

/* ==================================================================
   PURCHASE ACTION  (the most important one)
   --------------------------------------------------------------------
   Recording a purchase touches TWO tables:
     1. purchases   -> insert the new purchase row
     2. ingredients -> increase the available stock

   Both must happen together. We therefore use a TRANSACTION:
     BEGIN  -> do the work
     COMMIT -> everything worked, save it
     ROLLBACK -> something failed, undo everything

   This is what stops the stock from being updated when the purchase
   row itself could not be saved.
   ================================================================== */
if ($action === 'add_purchase') {
    $supplierId   = (int) ($_POST['supplier_id'] ?? 0);
    $ingredientId = (int) ($_POST['ingredient_id'] ?? 0);
    $quantity     = trim($_POST['quantity_purchased'] ?? '');
    $purchaseDate = trim($_POST['purchase_date'] ?? '');

    // --- Validate the input first ---
    if ($supplierId <= 0 || $ingredientId <= 0) {
        flash('error', 'Please choose both a supplier and an ingredient.');
        redirect('purchases.php');
    }
    if (!is_positive_number($quantity)) {
        flash('error', 'Quantity purchased must be a number greater than zero.');
        redirect('purchases.php');
    }
    if ($purchaseDate === '') {
        flash('error', 'Please choose the purchase date.');
        redirect('purchases.php');
    }
    if (!is_valid_date($purchaseDate)) {
        flash('error', 'That purchase date is not valid. Please use the date picker.');
        redirect('purchases.php');
    }
    if (is_future_date($purchaseDate)) {
        flash('error', 'The purchase date cannot be in the future.');
        redirect('purchases.php');
    }

    $quantity = (float) $quantity;

    try {
        // Start the transaction
        $pdo->beginTransaction();

        /* --- STEP 1 : read the price of the ingredient -----------------
           The price is taken from the ingredients table so that the
           total cost is always worked out from one trusted place. */
        $priceStatement = $pdo->prepare(
            'SELECT ingredient_name, price_per_unit, quantity, unit FROM ingredients WHERE ingredient_id = ?'
        );
        $priceStatement->execute([$ingredientId]);
        $ingredient = $priceStatement->fetch();

        if (!$ingredient) {
            throw new RuntimeException('That ingredient no longer exists.');
        }

        /* --- STEP 1b : make sure the supplier still exists -------------
           The foreign key would stop this anyway, but a clear message
           is easier to understand than a database error. */
        $supplierStatement = $pdo->prepare(
            'SELECT supplier_name FROM suppliers WHERE supplier_id = ?'
        );
        $supplierStatement->execute([$supplierId]);
        if (!$supplierStatement->fetch()) {
            throw new RuntimeException('That supplier no longer exists.');
        }

        // --- STEP 2 : work out the total cost -------------------------
        $pricePerUnit = (float) $ingredient['price_per_unit'];
        $totalCost    = round($quantity * $pricePerUnit, 2);

        // --- STEP 3 : INSERT the purchase row -------------------------
        $insertPurchase = $pdo->prepare(
            'INSERT INTO purchases (supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insertPurchase->execute([$supplierId, $ingredientId, $quantity, $purchaseDate, $totalCost]);

        // --- STEP 4 : UPDATE the available stock ----------------------
        $updateStock = $pdo->prepare(
            'UPDATE ingredients SET quantity = quantity + ? WHERE ingredient_id = ?'
        );
        $updateStock->execute([$quantity, $ingredientId]);

        // --- STEP 5 : everything worked, save it ----------------------
        $pdo->commit();

        flash('success', 'Purchase recorded. Stock of "' . $ingredient['ingredient_name'] . '" increased by '
            . qty($quantity) . ' ' . $ingredient['unit'] . '.');
        redirect('purchases.php');

    } catch (Throwable $e) {
        // Something failed -> undo both steps
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'The purchase could not be saved, so the stock was not changed. (' . $e->getMessage() . ')');
        redirect('purchases.php');
    }
}

if ($action === 'delete_purchase') {
    $id = (int) ($_POST['purchase_id'] ?? 0);

    // Deleting a purchase should also remove the stock it added,
    // so we use a transaction here as well.
    try {
        $pdo->beginTransaction();

        $statement = $pdo->prepare(
            'SELECT ingredient_id, quantity_purchased FROM purchases WHERE purchase_id = ?'
        );
        $statement->execute([$id]);
        $purchase = $statement->fetch();

        if (!$purchase) {
            throw new RuntimeException('That purchase was already deleted.');
        }

        /* --- Read the current stock ---------------------------------
           We only take the quantity off the stock if there is enough
           of it to take. Without this check the stock could go
           negative if the ingredient was used in the kitchen after
           the purchase was made. */
        $stockStatement = $pdo->prepare(
            'SELECT ingredient_name, quantity FROM ingredients WHERE ingredient_id = ?'
        );
        $stockStatement->execute([(int) $purchase['ingredient_id']]);
        $stock = $stockStatement->fetch();

        $bought = (float) $purchase['quantity_purchased'];
        $current = $stock ? (float) $stock['quantity'] : 0.0;

        if ($current < $bought) {
            throw new RuntimeException(
                'This purchase cannot be deleted because only ' . qty($current) . ' of "'
                . $stock['ingredient_name'] . '" is left, but ' . qty($bought)
                . ' would have to be taken off the stock. The stock has already been used.'
            );
        }

        // Take the quantity back off the stock
        $restore = $pdo->prepare(
            'UPDATE ingredients SET quantity = quantity - ? WHERE ingredient_id = ?'
        );
        $restore->execute([$bought, (int) $purchase['ingredient_id']]);

        $delete = $pdo->prepare('DELETE FROM purchases WHERE purchase_id = ?');
        $delete->execute([$id]);

        $pdo->commit();

        flash('success', 'Purchase deleted and the stock was adjusted.');
        redirect('purchases.php');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'The purchase could not be deleted. (' . $e->getMessage() . ')');
        redirect('purchases.php');
    }
}
