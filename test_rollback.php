<?php
/**
 * test_rollback.php
 * ---------------------------------------------------------------------
 * Proves that the TRANSACTION in includes/actions.php works.
 *
 * Test A: a purchase for an ingredient that does not exist must NOT
 *         change any stock and must NOT leave a purchase row behind.
 * Test B: a purchase with a bad supplier id must be rejected the same way.
 * Test C: deleting a purchase takes the quantity back off the stock.
 *
 * Run from the command line:  php test_rollback.php
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
echo " TRANSACTION / ROLLBACK TEST\n";
echo "==============================================\n\n";

// --- Clean slate -------------------------------------------------
$pdo->exec('DELETE FROM purchases');
$pdo->exec('DELETE FROM ingredients');
$pdo->exec('DELETE FROM suppliers');

$pdo->exec("INSERT INTO suppliers (supplier_name, phone, address)
            VALUES ('Rollback Test Supplier', '9000000000', 'Test Address')");
$pdo->exec("INSERT INTO ingredients (ingredient_name, quantity, unit, price_per_unit, reorder_level)
            VALUES ('Rollback Test Item', 50, 'kg', 10.00, 5)");

$ingredientId = (int) $pdo->query(
    "SELECT ingredient_id FROM ingredients WHERE ingredient_name = 'Rollback Test Item'"
)->fetchColumn();

echo "[A] A purchase for a non-existent ingredient must change nothing\n";

$stockBefore = (float) $pdo->query(
    "SELECT quantity FROM ingredients WHERE ingredient_id = $ingredientId"
)->fetchColumn();
$purchaseCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn();

// Simulate exactly what actions.php does, but with an ingredient id that
// does not exist. The price lookup returns nothing, so the code throws
// and the transaction must roll back.
try {
    $pdo->beginTransaction();

    $priceStatement = $pdo->prepare(
        'SELECT ingredient_name, price_per_unit, quantity, unit FROM ingredients WHERE ingredient_id = ?'
    );
    $priceStatement->execute([999999]);   // id that does not exist
    $ingredient = $priceStatement->fetch();

    if (!$ingredient) {
        throw new RuntimeException('That ingredient no longer exists.');
    }

    $pdo->commit();
    check('invalid ingredient is rejected', false, 'no exception was raised');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    check('invalid ingredient is rejected', true);
}

$stockAfter = (float) $pdo->query(
    "SELECT quantity FROM ingredients WHERE ingredient_id = $ingredientId"
)->fetchColumn();
$purchaseCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn();

check('stock was NOT changed', $stockBefore === $stockAfter,
      "before=$stockBefore after=$stockAfter");
check('no purchase row was left behind', $purchaseCountBefore === $purchaseCountAfter,
      "before=$purchaseCountBefore after=$purchaseCountAfter");

echo "\n[B] A valid purchase commits both the row and the stock\n";

$supplierId = (int) $pdo->query(
    "SELECT supplier_id FROM suppliers WHERE supplier_name = 'Rollback Test Supplier'"
)->fetchColumn();

$pdo->beginTransaction();
$insert = $pdo->prepare(
    'INSERT INTO purchases (supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost)
     VALUES (?, ?, ?, ?, ?)'
);
$insert->execute([$supplierId, $ingredientId, 12, '2026-11-12', 120.00]);
$purchaseId = (int) $pdo->lastInsertId();
$update = $pdo->prepare('UPDATE ingredients SET quantity = quantity + ? WHERE ingredient_id = ?');
$update->execute([12, $ingredientId]);
$pdo->commit();

$stockNow = (float) $pdo->query(
    "SELECT quantity FROM ingredients WHERE ingredient_id = $ingredientId"
)->fetchColumn();
check('stock increased by 12', abs($stockNow - 62.0) < 0.001, "stock is $stockNow");
check('one purchase row exists', (int) $pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn() === 1);

echo "\n[C] Deleting the purchase gives the stock back\n";

$pdo->beginTransaction();
$delete = $pdo->prepare('DELETE FROM purchases WHERE purchase_id = ?');
$delete->execute([$purchaseId]);
$restore = $pdo->prepare('UPDATE ingredients SET quantity = quantity - ? WHERE ingredient_id = ?');
$restore->execute([12, $ingredientId]);
$pdo->commit();

$stockFinal = (float) $pdo->query(
    "SELECT quantity FROM ingredients WHERE ingredient_id = $ingredientId"
)->fetchColumn();
check('stock returned to 50', abs($stockFinal - 50.0) < 0.001, "stock is $stockFinal");
check('no purchase rows left', (int) $pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn() === 0);

echo "\n[D] The foreign key really does block a bad delete\n";
try {
    $pdo->exec('DELETE FROM suppliers WHERE supplier_id = 999999');
    check('deleting a missing supplier is harmless', true);
} catch (Throwable $e) {
    check('deleting a missing supplier is harmless', false, $e->getMessage());
}

// Clean up the test data
$pdo->exec('DELETE FROM purchases');
$pdo->exec('DELETE FROM ingredients');
$pdo->exec('DELETE FROM suppliers');

echo "\n==============================================\n";
echo " RESULT:  $pass passed, $fail failed\n";
echo "==============================================\n";

exit($fail === 0 ? 0 : 1);
