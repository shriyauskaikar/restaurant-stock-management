-- =====================================================================
--  SAMPLE DATA
--  -------------------------------------------------------------------
--  Optional: run this in MySQL Workbench AFTER sql/schema.sql to fill
--  the database with example records, so the dashboard has something
--  to show on the first day.
--
--  HOW TO RUN IT
--    1. Open MySQL Workbench and connect to "Local instance MySQL80".
--    2. File > New Query Tab, open this file, press the lightning bolt.
--
--  It clears only the ADMIN account's data first, so other users' stock
--  is never touched.
-- =====================================================================

USE restaurant_stock_management;

-- The sample data is given to the ADMIN account. Sign up another user
-- and their dashboard will start empty - that is the whole idea.
SET @admin_id = (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1);

-- ---------------------------------------------------------------------
-- Delete only the admin's old rows. Other users' data is untouched.
-- ---------------------------------------------------------------------
DELETE FROM purchases   WHERE user_id = @admin_id;
DELETE FROM ingredients WHERE user_id = @admin_id;
DELETE FROM suppliers   WHERE user_id = @admin_id;

ALTER TABLE purchases   AUTO_INCREMENT = 1;
ALTER TABLE ingredients AUTO_INCREMENT = 1;
ALTER TABLE suppliers   AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------
-- SUPPLIERS
-- ---------------------------------------------------------------------
INSERT INTO suppliers (user_id, supplier_name, phone, address) VALUES
    (@admin_id, 'Green Valley Farms',  '9822012345', 'Shop 12, Panjim Market, Goa'),
    (@admin_id, 'Coastal Seafood Co.', '9822056789', '44 Harbour Street, Vasco da Gama, Goa'),
    (@admin_id, 'Sunrise Dairy',       '9822078901', '8 Sunshine Lane, Margao, Goa'),
    (@admin_id, 'Spice Route Traders', '9822011122', '77 Spice Market, Panaji, Goa'),
    (@admin_id, 'Golden Bakery Supply','9822033344', '5 Baker Street, Mapusa, Goa');

-- ---------------------------------------------------------------------
-- INGREDIENTS
--   "quantity"      = the current stock
--   "reorder_level" = the level that triggers the low-stock alert
--   A quantity that is equal to OR below the reorder level is low.
-- ---------------------------------------------------------------------
INSERT INTO ingredients (user_id, ingredient_name, quantity, unit, price_per_unit, reorder_level) VALUES
    (@admin_id, 'Basmati Rice',      25.00, 'kg',     85.50, 10.00),
    (@admin_id, 'Wheat Flour',       40.00, 'kg',     42.00, 15.00),
    (@admin_id, 'Sunflower Oil',     18.00, 'litres', 145.00,  8.00),
    (@admin_id, 'Chicken Breast',     6.00, 'kg',    320.00, 10.00),   -- LOW STOCK
    (@admin_id, 'Tomatoes',           8.00, 'kg',     48.00,  5.00),
    (@admin_id, 'Onions',            15.00, 'kg',     36.00,  6.00),
    (@admin_id, 'Paneer',             4.00, 'kg',    280.00,  8.00),   -- LOW STOCK
    (@admin_id, 'Milk',              20.00, 'litres', 62.00, 10.00),
    (@admin_id, 'Black Pepper',       2.50, 'kg',    780.00,  1.00),
    (@admin_id, 'Sugar',             30.00, 'kg',     44.00, 10.00),
    (@admin_id, 'Eggs',              90.00, 'pieces',  7.50, 30.00),
    (@admin_id, 'Green Chillies',     3.00, 'kg',     95.00,  4.00),   -- LOW STOCK
    (@admin_id, 'Coconut Milk',       2.00, 'litres', 88.00,  6.00),   -- LOW STOCK
    (@admin_id, 'Ginger',             2.50, 'kg',    110.00,  1.50);

-- ---------------------------------------------------------------------
-- PURCHASES
--   total_cost = quantity_purchased x price_per_unit
--   The same ingredient appears more than once (Basmati Rice, Wheat
--   Flour, Chicken Breast). That is the 1:N relationship in action:
--   one ingredient can be bought many times, and each purchase is
--   stored as its own row - no duplicate ingredient is ever created.
-- ---------------------------------------------------------------------
INSERT INTO purchases (user_id, supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost) VALUES
    (@admin_id, 1, 1,  50.00, '2026-10-01', 4275.00),   -- Basmati Rice
    (@admin_id, 1, 2,  30.00, '2026-10-01', 1260.00),   -- Wheat Flour
    (@admin_id, 1, 3,  20.00, '2026-10-02', 2900.00),   -- Sunflower Oil
    (@admin_id, 2, 4,  12.00, '2026-10-02', 3840.00),   -- Chicken Breast
    (@admin_id, 2, 5,  18.00, '2026-10-03',  864.00),   -- Tomatoes
    (@admin_id, 3, 8,  25.00, '2026-10-03', 1550.00),   -- Milk
    (@admin_id, 3, 11, 120.00, '2026-10-04',  900.00),  -- Eggs
    (@admin_id, 4, 6,  22.00, '2026-10-05',  792.00),   -- Onions
    (@admin_id, 4, 9,   1.50, '2026-10-05', 1170.00),   -- Black Pepper
    (@admin_id, 4, 12,  5.00, '2026-10-06',  475.00),   -- Green Chillies
    (@admin_id, 1, 2,  15.00, '2026-10-07',  630.00),   -- Wheat Flour again (1:N)
    (@admin_id, 5, 10, 20.00, '2026-10-08',  880.00),   -- Sugar
    (@admin_id, 2, 4,   8.00, '2026-10-09', 2560.00),   -- Chicken Breast again (1:N)
    (@admin_id, 3, 7,   6.00, '2026-10-10', 1680.00),   -- Paneer
    (@admin_id, 1, 1,  10.00, '2026-10-11',  855.00),   -- Basmati Rice again (1:N)
    (@admin_id, 4, 14,  3.00, '2026-10-12',  330.00),   -- Ginger
    (@admin_id, 2, 13,  8.00, '2026-10-13',  704.00);   -- Coconut Milk

-- ---------------------------------------------------------------------
-- Check that everything landed
-- ---------------------------------------------------------------------
SELECT 'Sample data loaded' AS status;
SELECT COUNT(*) AS suppliers   FROM suppliers;
SELECT COUNT(*) AS ingredients FROM ingredients;
SELECT COUNT(*) AS purchases   FROM purchases;

-- The low-stock list, exactly as the application calculates it
SELECT ingredient_name, quantity, unit, reorder_level
  FROM ingredients
 WHERE quantity <= reorder_level
 ORDER BY quantity ASC;
