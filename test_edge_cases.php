<?php
/**
 * test_edge_cases.php
 * ---------------------------------------------------------------------
 * Checks the validation rules that protect the database from bad input.
 * Run from the command line:  php test_edge_cases.php
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db_connect();
$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        echo "  [PASS] $label\n";
        $pass++;
    } else {
        echo "  [FAIL] $label" . ($detail ? "  -> $detail" : '') . "\n";
        $fail++;
    }
}

echo "==============================================\n";
echo " VALIDATION / EDGE CASE TEST\n";
echo "==============================================\n\n";

// --- Date validation helpers -------------------------------------
echo "[A] Date validation\n";
check('a real date is accepted', is_valid_date('2026-11-12'));
check('an impossible date is rejected', !is_valid_date('2026-02-31'));
check('a wrong format is rejected', !is_valid_date('12/11/2026'));
check('a nonsense string is rejected', !is_valid_date('not-a-date'));
check('an empty string is rejected', !is_valid_date(''));
check('a future date is detected', is_future_date('2099-01-01'));
check('today is not a future date', !is_future_date(date('Y-m-d')));

echo "\n[B] Number validation\n";
check('zero is allowed', is_number('0'));
check('a decimal is allowed', is_number('12.5'));
check('a negative number is rejected', !is_number('-5'));
check('text is rejected', !is_number('abc'));
check('an empty string is rejected', !is_number(''));
check('zero is not a positive number', !is_positive_number('0'));
check('a positive decimal is allowed', is_positive_number('0.01'));

// --- Set up a clean test scenario -------------------------------
$pdo->exec('DELETE FROM purchases');
$pdo->exec('DELETE FROM ingredients');
$pdo->exec('DELETE FROM suppliers');

$pdo->exec("INSERT INTO suppliers (supplier_name, phone, address)
            VALUES ('Edge Test Supplier','9000000000','Edge Address')");
$pdo->exec("INSERT INTO ingredients (ingredient_name, quantity, unit, price_per_unit, reorder_level)
            VALUES ('Edge Test Item', 10, 'kg', 20.00, 5)");
$ingredientId = (int) $pdo->query("SELECT ingredient_id FROM ingredients WHERE ingredient_name='Edge Test Item'")->fetchColumn();
$supplierId  = (int) $pdo->query("SELECT supplier_id FROM suppliers WHERE supplier_name='Edge Test Supplier'")->fetchColumn();

echo "\n[C] Deleting a purchase that would make the stock negative\n";
$pdo->exec("INSERT INTO purchases (supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost)
            VALUES ($supplierId, $ingredientId, 8, '2026-11-01', 160.00)");
$purchaseId = (int) $pdo->lastInsertId();
// The kitchen then used 5 of the 10 kg, leaving 5.
// The purchase bought 8, so deleting it would need 8 but only 5 exist.
$pdo->exec("UPDATE ingredients SET quantity = 5 WHERE ingredient_id = $ingredientId");

// Reproduce the delete logic from actions.php
$blocked = false;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT ingredient_id, quantity_purchased FROM purchases WHERE purchase_id = ?');
    $stmt->execute([$purchaseId]);
    $purchase = $stmt->fetch();
    $stockStmt = $pdo->prepare('SELECT ingredient_name, quantity FROM ingredients WHERE ingredient_id = ?');
    $stockStmt->execute([(int) $purchase['ingredient_id']]);
    $stock = $stockStmt->fetch();
    $bought = (float) $purchase['quantity_purchased'];
    $current = (float) $stock['quantity'];
    if ($current < $bought) {
        throw new RuntimeException('not enough stock to remove');
    }
    $pdo->commit();
} catch (Throwable $e) {
    $blocked = true;
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
}
check('the delete is blocked when there is not enough stock', $blocked);
check('the purchase row still exists', (int) $pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn() === 1);
check('the stock was not changed', (float) $pdo->query("SELECT quantity FROM ingredients WHERE ingredient_id=$ingredientId")->fetchColumn() === 5.0);

echo "\n[D] Deleting a purchase when there IS enough stock\n";
$pdo->exec("UPDATE ingredients SET quantity = 20 WHERE ingredient_id = $ingredientId");
$pdo->beginTransaction();
$stmt = $pdo->prepare('SELECT ingredient_id, quantity_purchased FROM purchases WHERE purchase_id = ?');
$stmt->execute([$purchaseId]);
$purchase = $stmt->fetch();
$restore = $pdo->prepare('UPDATE ingredients SET quantity = quantity - ? WHERE ingredient_id = ?');
$restore->execute([(float) $purchase['quantity_purchased'], (int) $purchase['ingredient_id']]);
$pdo->prepare('DELETE FROM purchases WHERE purchase_id = ?')->execute([$purchaseId]);
$pdo->commit();
check('the stock was reduced by the quantity bought',
      (float) $pdo->query("SELECT quantity FROM ingredients WHERE ingredient_id=$ingredientId")->fetchColumn() === 12.0);
check('the purchase row was deleted', (int) $pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn() === 0);

echo "\n[E] The price used for the cost comes from the ingredients table\n";
$pdo->exec("UPDATE ingredients SET price_per_unit = 33.50 WHERE ingredient_id = $ingredientId");
$pdo->beginTransaction();
$priceStmt = $pdo->prepare('SELECT price_per_unit FROM ingredients WHERE ingredient_id = ?');
$priceStmt->execute([$ingredientId]);
$price = (float) $priceStmt->fetchColumn();
$total = round(4 * $price, 2);
$pdo->prepare('INSERT INTO purchases (supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost)
               VALUES (?,?,?,?,?)')->execute([$supplierId, $ingredientId, 4, '2026-11-12', $total]);
$pdo->commit();
check('total cost = 4 x 33.50 = 134.00', $total === 134.00, 'got ' . $total);

echo "\n[F] The foreign key stops an orphaned purchase\n";
try {
    $pdo->exec('INSERT INTO purchases (supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost)
                VALUES (999999, 999999, 1, "2026-11-12", 1.00)');
    check('an orphaned purchase is rejected', false, 'the insert succeeded!');
} catch (PDOException $e) {
    check('an orphaned purchase is rejected', true);
}

echo "\n[G] A duplicate ingredient name is rejected by the database\n";
try {
    $pdo->exec("INSERT INTO ingredients (ingredient_name, quantity, unit, price_per_unit, reorder_level)
                VALUES ('Edge Test Item', 1, 'kg', 1.00, 1.00)");
    check('a duplicate ingredient name is rejected', false, 'the insert succeeded!');
} catch (PDOException $e) {
    check('a duplicate ingredient name is rejected', true);
}

// Clean up
$pdo->exec('DELETE FROM purchases');
$pdo->exec('DELETE FROM ingredients');
$pdo->exec('DELETE FROM suppliers');

echo "\n==============================================\n";
echo " RESULT:  $pass passed, $fail failed\n";
echo "==============================================\n";

exit($fail === 0 ? 0 : 1);
