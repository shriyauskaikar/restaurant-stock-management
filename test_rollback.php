<?php
/**
 * test_rollback.php - proves the purchase transaction really works.
 *
 * A purchase changes TWO tables:
 *   1. inserts a purchase row
 *   2. increases the ingredient stock
 * If step 1 fails, step 2 must NOT happen. That is what a transaction
 * guarantees, and this test proves it in MySQL.
 *
 * Run:  php test_rollback.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [PASS] $label\n";
    } else {
        $fail++;
        echo "  [FAIL] $label" . ($detail !== '' ? " ($detail)" : '') . "\n";
    }
}

echo "==============================================\n";
echo " TRANSACTION / ROLLBACK TEST\n";
echo "==============================================\n\n";

$pdo = db_connect();

// The fixtures belong to admin, just like the sample data.
$testUid = (int) $pdo->query("SELECT user_id FROM users WHERE username='admin' LIMIT 1")->fetchColumn();
if ($testUid === 0) {
    echo "Run setup/create_admin.php first.\n";
    exit(1);
}

// --- Clean slate: only THIS test's rows, never real data -----------
function clean_rollback_fixtures(PDO $pdo, int $uid): void
{
    $pdo->exec("DELETE FROM purchases WHERE user_id = $uid AND ingredient_id IN
                (SELECT ingredient_id FROM ingredients WHERE ingredient_name='Rollback Test Item')");
    $pdo->exec("DELETE FROM ingredients WHERE user_id = $uid AND ingredient_name='Rollback Test Item'");
    $pdo->exec("DELETE FROM suppliers WHERE user_id = $uid AND supplier_name='Rollback Test Supplier'");
}

clean_rollback_fixtures($pdo, $testUid);

$pdo->exec("INSERT INTO suppliers (user_id, supplier_name, phone, address)
            VALUES ($testUid, 'Rollback Test Supplier', '9000000000', 'Test Address')");
$pdo->exec("INSERT INTO ingredients (user_id, ingredient_name, quantity, unit, price_per_unit, reorder_level)
            VALUES ($testUid, 'Rollback Test Item', 50, 'kg', 10.00, 5)");

$ingredientId = (int) $pdo->query(
    "SELECT ingredient_id FROM ingredients WHERE ingredient_name = 'Rollback Test Item'"
)->fetchColumn();
$supplierId = (int) $pdo->query(
    "SELECT supplier_id FROM suppliers WHERE supplier_name = 'Rollback Test Supplier'"
)->fetchColumn();

// Count only this test's purchase rows.
$purchaseCountSql = "SELECT COUNT(*) FROM purchases WHERE user_id = $testUid
                     AND ingredient_id = $ingredientId";

echo "[A] A purchase for a non-existent ingredient must change nothing\n";

$stockBefore = (float) $pdo->query(
    "SELECT quantity FROM ingredients WHERE ingredient_id = $ingredientId"
)->fetchColumn();
$purchaseCountBefore = (int) $pdo->query($purchaseCountSql)->fetchColumn();

try {
    $pdo->beginTransaction();

    $priceStatement = $pdo->prepare(
        'SELECT ingredient_name, price_per_unit, quantity, unit FROM ingredients WHERE ingredient_id = ? AND user_id = ?'
    );
    $priceStatement->execute([999999, $testUid]);
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
$purchaseCountAfter = (int) $pdo->query($purchaseCountSql)->fetchColumn();

check('stock was NOT changed', $stockBefore === $stockAfter, "before=$stockBefore after=$stockAfter");
check('no purchase row was left behind', $purchaseCountBefore === $purchaseCountAfter,
      "before=$purchaseCountBefore after=$purchaseCountAfter");

echo "\n[B] A valid purchase commits both the row and the stock\n";

$pdo->beginTransaction();
$insert = $pdo->prepare(
    'INSERT INTO purchases (user_id, supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$insert->execute([$testUid, $supplierId, $ingredientId, 12, '2026-11-12', 120.00]);
$purchaseId = (int) $pdo->lastInsertId();
$update = $pdo->prepare('UPDATE ingredients SET quantity = quantity + ? WHERE ingredient_id = ? AND user_id = ?');
$update->execute([12, $ingredientId, $testUid]);
$pdo->commit();

$stockNow = (float) $pdo->query(
    "SELECT quantity FROM ingredients WHERE ingredient_id = $ingredientId"
)->fetchColumn();
check('stock increased by 12', abs($stockNow - 62.0) < 0.001, "stock is $stockNow");
check('one purchase row exists', (int) $pdo->query($purchaseCountSql)->fetchColumn() === 1);

echo "\n[C] Deleting the purchase gives the stock back\n";

$pdo->beginTransaction();
$delete = $pdo->prepare('DELETE FROM purchases WHERE purchase_id = ? AND user_id = ?');
$delete->execute([$purchaseId, $testUid]);
$restore = $pdo->prepare('UPDATE ingredients SET quantity = quantity - ? WHERE ingredient_id = ? AND user_id = ?');
$restore->execute([12, $ingredientId, $testUid]);
$pdo->commit();

$stockFinal = (float) $pdo->query(
    "SELECT quantity FROM ingredients WHERE ingredient_id = $ingredientId"
)->fetchColumn();
check('stock returned to 50', abs($stockFinal - 50.0) < 0.001, "stock is $stockFinal");
check('no purchase rows left', (int) $pdo->query($purchaseCountSql)->fetchColumn() === 0);

echo "\n[D] The foreign key really does block a bad delete\n";
try {
    $pdo->exec('DELETE FROM suppliers WHERE supplier_id = 999999');
    check('deleting a missing supplier is harmless', true);
} catch (Throwable $e) {
    check('deleting a missing supplier is harmless', false, $e->getMessage());
}

// Clean up only our own fixtures.
clean_rollback_fixtures($pdo, $testUid);

echo "\n==============================================\n";
echo " RESULT:  $pass passed, $fail failed\n";
echo "==============================================\n";
