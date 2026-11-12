-- =====================================================================
--  SAMPLE DATA
--  Optional: run this in MySQL Workbench AFTER sql/schema.sql to fill
--  the database with example records so the dashboard looks alive.
--  It clears the three tables first, so it also resets your data.
-- =====================================================================

USE restaurant_stock_management;

-- Delete the old rows, then reset the auto-increment counters so the
-- ids below match the id numbers used in the purchases table.
DELETE FROM purchases;
DELETE FROM ingredients;
DELETE FROM suppliers;

ALTER TABLE purchases   AUTO_INCREMENT = 1;
ALTER TABLE ingredients AUTO_INCREMENT = 1;
ALTER TABLE suppliers   AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------
-- SUPPLIERS
-- ---------------------------------------------------------------------
INSERT INTO suppliers (supplier_name, phone, address) VALUES
    ('Green Valley Farms',   '9876543210', '12 Market Road, Pune, Maharashtra'),
    ('Coastal Seafood Co.',   '9823456701', '44 Harbour Street, Mumbai, Maharashtra'),
    ('Sunrise Dairy',         '9934567802', '8 Sunshine Lane, Nashik, Maharashtra'),
    ('Spice Route Traders',   '9123456789', '77 Spice Market, Nagpur, Maharashtra'),
    ('Golden Bakery Supply',  '9012345678', '5 Baker Street, Pune, Maharashtra');

-- ---------------------------------------------------------------------
-- INGREDIENTS
-- "quantity" is the current stock. "reorder_level" triggers the
-- low stock alert when the quantity drops below it.
-- ---------------------------------------------------------------------
INSERT INTO ingredients (ingredient_name, quantity, unit, price_per_unit, reorder_level) VALUES
    ('Basmati Rice',      25.00, 'kg',    85.50,  10.00),
    ('Wheat Flour',       40.00, 'kg',    42.00,  15.00),
    ('Sunflower Oil',     18.00, 'litres', 145.00, 8.00),
    ('Chicken Breast',     6.00, 'kg',    320.00, 10.00),   -- low stock
    ('Tomatoes',           8.00, 'kg',    48.00,  5.00),
    ('Onions',            15.00, 'kg',    36.00,  6.00),
    ('Paneer',             4.00, 'kg',    280.00, 8.00),    -- low stock
    ('Milk',              20.00, 'litres', 62.00, 10.00),
    ('Black Pepper',       2.50, 'kg',    780.00, 1.00),
    ('Sugar',             30.00, 'kg',    44.00,  10.00),
    ('Eggs',              90.00, 'pieces', 7.50,  30.00),
    ('Green Chillies',     3.00, 'kg',    95.00,  4.00);    -- low stock

-- ---------------------------------------------------------------------
-- PURCHASES
-- total_cost = quantity_purchased * price_per_unit
-- ---------------------------------------------------------------------
INSERT INTO purchases (supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost) VALUES
    (1, 1,  50.00, '2026-11-01', 4275.00),   -- Basmati Rice
    (1, 2,  30.00, '2026-11-01', 1260.00),   -- Wheat Flour
    (1, 3,  20.00, '2026-11-02', 2900.00),   -- Sunflower Oil
    (2, 4,  12.00, '2026-11-02', 3840.00),   -- Chicken Breast
    (2, 5,  18.00, '2026-11-03',  864.00),   -- Tomatoes
    (3, 8,  25.00, '2026-11-03', 1550.00),   -- Milk
    (3, 11, 120.00, '2026-11-04',  900.00),   -- Eggs
    (4, 6,  22.00, '2026-11-05',  792.00),   -- Onions
    (4, 9,   1.50, '2026-11-05', 1170.00),   -- Black Pepper
    (4, 12,   5.00, '2026-11-06',  475.00),   -- Green Chillies
    (1, 2,  15.00, '2026-11-07',  630.00),   -- Wheat Flour again (same ingredient, 1:N)
    (5, 10, 20.00, '2026-11-08',  880.00),   -- Sugar
    (2, 4,   8.00, '2026-11-09', 2560.00),   -- Chicken Breast again (1:N)
    (3, 7,   6.00, '2026-11-10', 1680.00),   -- Paneer
    (1, 1,  10.00, '2026-11-11',  855.00);   -- Basmati Rice again (1:N)

SELECT 'Sample data loaded' AS status;
SELECT * FROM suppliers;
SELECT * FROM ingredients;
SELECT * FROM purchases;
