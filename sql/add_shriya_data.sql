-- =====================================================================
--  Data for SHRIYA's account (user_id = 3)
-- ---------------------------------------------------------------------
--  Run in MySQL Workbench on the "root" connection.
--
--  The original INSERT statements had a trailing comma after the last
--  VALUES row in each group, which MySQL rejects. They are removed here.
--  Each row also carries user_id = 3, so this data appears ONLY in
--  Shriya's dashboard. Other accounts are unaffected.
--
--  Purchases use variable lookups because auto-increment ids are not
--  guaranteed to start at 1. Stock is increased to match each purchase.
-- =====================================================================

USE restaurant_stock_management;

SET @uid = (SELECT user_id FROM users WHERE username = 'Shriya' LIMIT 1);

-- ---------------------------------------------------------------------
-- 1. SUPPLIERS
-- ---------------------------------------------------------------------
INSERT INTO suppliers (user_id, supplier_name, phone, address) VALUES
    (@uid, 'Fresh Farms',        '9876543210', 'Margao'),
    (@uid, 'Daily Dairy',        '9876543211', 'Fatorda'),
    (@uid, 'Goa Wholesale Mart', '9876543212', 'Panjim');

-- ---------------------------------------------------------------------
-- 2. INGREDIENTS
-- ---------------------------------------------------------------------
INSERT INTO ingredients (user_id, ingredient_name, quantity, unit, price_per_unit, reorder_level) VALUES
    (@uid, 'Rice',     0, 'kg',     60, 10),
    (@uid, 'Paneer',   0, 'kg',    320,  8),
    (@uid, 'Onions',   0, 'kg',     35,  5),
    (@uid, 'Potatoes', 0, 'kg',     30,  5),
    (@uid, 'Milk',     0, 'litres', 60,  5),
    (@uid, 'Cheese',   0, 'kg',    400,  4);

-- ---------------------------------------------------------------------
-- 3. PURCHASES - look up the ids we just created for Shriya
-- ---------------------------------------------------------------------
SET @goa_mart   = (SELECT supplier_id FROM suppliers WHERE user_id=@uid AND supplier_name='Goa Wholesale Mart');
SET @daily      = (SELECT supplier_id FROM suppliers WHERE user_id=@uid AND supplier_name='Daily Dairy');
SET @rice       = (SELECT ingredient_id FROM ingredients WHERE user_id=@uid AND ingredient_name='Rice');
SET @paneer     = (SELECT ingredient_id FROM ingredients WHERE user_id=@uid AND ingredient_name='Paneer');

INSERT INTO purchases (user_id, supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost) VALUES
    (@uid, @goa_mart, @rice,   10, '2026-11-10',  600),
    (@uid, @daily,    @paneer,  5, '2026-11-10', 1600);

-- Keep stock in sync: a purchase increases available stock.
UPDATE ingredients SET quantity = quantity + 10 WHERE ingredient_id = @rice;
UPDATE ingredients SET quantity = quantity + 5  WHERE ingredient_id = @paneer;

SELECT 'Shriya data added' AS status;
SELECT supplier_name, phone, address FROM suppliers WHERE user_id = @uid;
SELECT ingredient_name, quantity, unit, price_per_unit, reorder_level FROM ingredients WHERE user_id = @uid;
SELECT purchase_id, quantity_purchased, purchase_date, total_cost FROM purchases WHERE user_id = @uid;
