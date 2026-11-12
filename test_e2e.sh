#!/bin/bash
# =====================================================================
#  END-TO-END TEST for the Restaurant Food Stock Management System
#  Runs against the real application over HTTP, exactly like a browser
#  would: it reads the CSRF token out of each page and posts real forms.
# =====================================================================
set -u

BASE="http://localhost/restaurant-stock-management"
JAR="$(mktemp)"
PASS=0
FAIL=0

pass() { echo "  [PASS] $1"; PASS=$((PASS+1)); }
fail() { echo "  [FAIL] $1"; FAIL=$((FAIL+1)); }

# Get a page and print its body
get() { curl -s -b "$JAR" -c "$JAR" "$BASE/$1"; }

# Extract the csrf token value from a page body
token() { grep -o 'name="csrf_token" value="[a-f0-9]*"' "$1" | head -1 | sed 's/.*value="//;s/"//'; }

# Extract the option value for a named item (HTML is flattened to one line first,
# because the <option> value and its label sit on different lines)
option_id() { tr '\n' ' ' < "$1" | grep -o "value=\"[0-9]*\"[^>]*>[[:space:]]*$2" | grep -o '[0-9]*' | head -1; }

# Reset the test data so the script can be run again and again
reset_data() {
  mysql -u root -D restaurant_stock_management -e \
    "DELETE FROM purchases; DELETE FROM ingredients; DELETE FROM suppliers;" 2>/dev/null
}

echo "=============================================="
echo " RESTAURANT STOCK SYSTEM - END TO END TEST"
echo "=============================================="
echo ""
reset_data

# ---------------------------------------------------------------
# 1. ADD A SUPPLIER
# ---------------------------------------------------------------
echo "[1] Add a supplier"
get "suppliers.php" > /tmp/t_sup.html
T=$(token /tmp/t_sup.html)
[ -n "$T" ] && pass "CSRF token present on suppliers page" || fail "no CSRF token found"

curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/suppliers.php" \
  -d "csrf_token=$T" -d "action=add_supplier" \
  -d "supplier_name=Green Valley Farms" -d "phone=9876543210" \
  -d "address=12 Market Road, Pune" -o /dev/null

get "suppliers.php" > /tmp/t_sup2.html
grep -q "Green Valley Farms" /tmp/t_sup2.html \
  && pass "supplier 'Green Valley Farms' was saved and is listed" \
  || fail "supplier not found after insert"

grep -q "was added successfully" /tmp/t_sup2.html \
  && pass "success message shown" || fail "no success message"

# ---------------------------------------------------------------
# 2. ADD AN INGREDIENT
# ---------------------------------------------------------------
echo ""
echo "[2] Add an ingredient"
get "ingredients.php" > /tmp/t_ing.html
T=$(token /tmp/t_ing.html)

curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/ingredients.php" \
  -d "csrf_token=$T" -d "action=add_ingredient" \
  -d "ingredient_name=Basmati Rice" -d "quantity=20" -d "unit=kg" \
  -d "price_per_unit=85.50" -d "reorder_level=10" -o /dev/null

get "ingredients.php" > /tmp/t_ing2.html
grep -q "Basmati Rice" /tmp/t_ing2.html \
  && pass "ingredient 'Basmati Rice' was saved and is listed" \
  || fail "ingredient not found after insert"

# ---------------------------------------------------------------
# 3. DUPLICATE INGREDIENT SHOULD BE REJECTED
# ---------------------------------------------------------------
echo ""
echo "[3] Duplicate ingredient name is rejected"
get "ingredients.php" > /tmp/t_ing3.html
T=$(token /tmp/t_ing3.html)
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/ingredients.php" \
  -d "csrf_token=$T" -d "action=add_ingredient" \
  -d "ingredient_name=Basmati Rice" -d "quantity=5" -d "unit=kg" \
  -d "price_per_unit=85.50" -d "reorder_level=10" -o /dev/null
get "ingredients.php" > /tmp/t_ing4.html
grep -q "already exists" /tmp/t_ing4.html \
  && pass "duplicate ingredient rejected with a clear message" \
  || fail "duplicate ingredient was NOT rejected"

# ---------------------------------------------------------------
# 4. RECORD A PURCHASE  ->  stock must increase by the quantity
# ---------------------------------------------------------------
echo ""
echo "[4] Record a purchase (stock must increase)"
STOCK_BEFORE=$(mysql -N -B -u root -D restaurant_stock_management \
  -e "SELECT quantity FROM ingredients WHERE ingredient_name='Basmati Rice';" 2>/dev/null)
echo "      stock before purchase = $STOCK_BEFORE"

get "purchases.php" > /tmp/t_pur.html
T=$(token /tmp/t_pur.html)
SUP_ID=$(option_id /tmp/t_pur.html "Green Valley Farms")
ING_ID=$(option_id /tmp/t_pur.html "Basmati Rice")
echo "      supplier_id=$SUP_ID  ingredient_id=$ING_ID"
[ -n "$SUP_ID" ] && [ -n "$ING_ID" ] \
  && pass "dropdowns loaded with the supplier and the ingredient" \
  || fail "could not read the ids from the dropdowns"

curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/purchases.php" \
  -d "csrf_token=$T" -d "action=add_purchase" \
  -d "supplier_id=$SUP_ID" -d "ingredient_id=$ING_ID" \
  -d "quantity_purchased=15" -d "purchase_date=2026-11-12" -o /dev/null

STOCK_AFTER=$(mysql -N -B -u root -D restaurant_stock_management \
  -e "SELECT quantity FROM ingredients WHERE ingredient_name='Basmati Rice';" 2>/dev/null)
echo "      stock after  purchase = $STOCK_AFTER"

EXPECTED=$(awk "BEGIN{printf \"%.2f\", $STOCK_BEFORE + 15}")
[ "$STOCK_AFTER" = "$EXPECTED" ] \
  && pass "stock updated correctly: $STOCK_BEFORE + 15 = $STOCK_AFTER" \
  || fail "stock is $STOCK_AFTER but should be $EXPECTED"

# ---------------------------------------------------------------
# 5. TOTAL COST = quantity x price per unit
# ---------------------------------------------------------------
echo ""
echo "[5] Total cost is calculated automatically"
COST=$(mysql -N -B -u root -D restaurant_stock_management \
  -e "SELECT total_cost FROM purchases ORDER BY purchase_id DESC LIMIT 1;" 2>/dev/null)
echo "      total_cost stored = $COST (expected 15 x 85.50 = 1282.50)"
[ "$COST" = "1282.50" ] \
  && pass "total cost correct: $COST" \
  || fail "total cost is $COST, expected 1282.50"

# ---------------------------------------------------------------
# 6. LOW STOCK ALERT
# ---------------------------------------------------------------
echo ""
echo "[6] Low stock alert appears when stock falls below reorder level"
mysql -u root -D restaurant_stock_management -e \
  "UPDATE ingredients SET quantity=3 WHERE ingredient_name='Basmati Rice';" 2>/dev/null
get "low_stock.php" > /tmp/t_low.html
grep -q "Basmati Rice" /tmp/t_low.html \
  && pass "low stock page lists the ingredient" \
  || fail "low stock page did not list the ingredient"
grep -q "Low stock items" /tmp/t_low.html \
  && pass "low stock summary card present" || fail "no low stock summary"

# ---------------------------------------------------------------
# 7. SUPPLIER WITH PURCHASES CANNOT BE DELETED
# ---------------------------------------------------------------
echo ""
echo "[7] Referential integrity blocks deleting a used supplier"
get "suppliers.php" > /tmp/t_supd.html
T=$(token /tmp/t_supd.html)
SUP_ID=$(mysql -N -B -u root -D restaurant_stock_management \
  -e "SELECT supplier_id FROM suppliers WHERE supplier_name='Green Valley Farms';" 2>/dev/null)
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/suppliers.php" \
  -d "csrf_token=$T" -d "action=delete_supplier" -d "supplier_id=$SUP_ID" -o /dev/null
get "suppliers.php" > /tmp/t_supd2.html
grep -q "cannot be deleted" /tmp/t_supd2.html \
  && pass "deletion blocked with a clear error message" \
  || fail "supplier with purchases WAS deleted - integrity broken!"
STILL=$(mysql -N -B -u root -D restaurant_stock_management \
  -e "SELECT COUNT(*) FROM suppliers WHERE supplier_name='Green Valley Farms';" 2>/dev/null)
[ "$STILL" = "1" ] && pass "supplier row still present in the database" || fail "supplier row was deleted"

# ---------------------------------------------------------------
# 8. SQL INJECTION ATTEMPT IS BLOCKED
# ---------------------------------------------------------------
echo ""
echo "[8] SQL injection attempt is harmless"
get "suppliers.php" > /tmp/t_sqli.html
T=$(token /tmp/t_sqli.html)
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/suppliers.php" \
  --data-urlencode "csrf_token=$T" \
  --data-urlencode "action=add_supplier" \
  --data-urlencode "supplier_name=x'; DROP TABLE suppliers; --" \
  --data-urlencode "phone=0000000000" \
  --data-urlencode "address=test" -o /dev/null
TABLES=$(mysql -N -B -u root -D restaurant_stock_management -e "SHOW TABLES;" 2>/dev/null | wc -l)
[ "$TABLES" = "3" ] \
  && pass "all 3 tables still exist - injection had no effect" \
  || fail "tables are missing - injection may have worked!"

# ---------------------------------------------------------------
# 9. DASHBOARD COUNTS
# ---------------------------------------------------------------
echo ""
echo "[9] Dashboard shows the summary counts"
get "" > /tmp/t_dash.html
grep -q "Total ingredients" /tmp/t_dash.html && pass "dashboard ingredients card" || fail "no ingredients card"
grep -q "Total suppliers"  /tmp/t_dash.html && pass "dashboard suppliers card"  || fail "no suppliers card"
grep -q "Total purchases"  /tmp/t_dash.html && pass "dashboard purchases card"  || fail "no purchases card"
grep -q "Low stock alerts" /tmp/t_dash.html && pass "dashboard low stock card" || fail "no low stock card"

# ---------------------------------------------------------------
# 10. SEARCH WORKS
# ---------------------------------------------------------------
echo ""
echo "[10] Search filters the table"
get "suppliers.php?q=Green" > /tmp/t_search.html
grep -q "Green Valley Farms" /tmp/t_search.html && pass "search finds the supplier" || fail "search did not find it"

# ---------------------------------------------------------------
# 11. CSRF REJECTION
# ---------------------------------------------------------------
echo ""
echo "[11] A request with a bad CSRF token is rejected"
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/suppliers.php" \
  -d "csrf_token=totallywrongtoken" -d "action=add_supplier" \
  -d "supplier_name=Hacker Ltd" -d "phone=1" -d "address=x" -o /dev/null
get "suppliers.php" > /tmp/t_csrf.html
grep -q "Hacker Ltd" /tmp/t_csrf.html \
  && fail "injected supplier was saved - CSRF broken!" \
  || pass "request without a valid CSRF token was rejected"

echo ""
echo "=============================================="
echo " RESULT:  $PASS passed, $FAIL failed"
echo "=============================================="
rm -f "$JAR"
[ "$FAIL" -eq 0 ]
