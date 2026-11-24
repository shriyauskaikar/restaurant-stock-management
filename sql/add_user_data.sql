-- =====================================================================
--  Extra sample data supplied by the user (Goa based)
--  Run this in MySQL Workbench on the "root" connection.
--
--  This data belongs to the ADMIN account. A newly registered user
--  starts with an empty dashboard - every row is tied to a user_id.
--
--  NOTES:
--   * The original INSERT statements had a trailing comma after the
--     final VALUES row, which MySQL rejects. They are removed here.
--   * "Cheese" already existed for admin, so it is skipped to avoid
--     the UNIQUE (user_id, ingredient_name) constraint.
--   * Suppliers / ingredients get NEW ids (auto increment), so the
--     purchase rows below look those ids up with user variables.
-- =====================================================================

USE restaurant_stock_management;

SET @admin_id = (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1);

-- ---------------------------------------------------------------------
-- 1. SUPPLIERS
-- ---------------------------------------------------------------------
INSERT INTO suppliers (user_id, supplier_name, phone, address) VALUES
    (@admin_id, 'Fresh Farms',        '9876543210', 'Margao'),
    (@admin_id, 'Daily Dairy',        '9876543211', 'Fatorda'),
    (@admin_id, 'Goa Wholesale Mart', '9876543212', 'Panjim');

-- ---------------------------------------------------------------------
-- 2. INGREDIENTS  (Cheese skipped because admin already has it)
-- ---------------------------------------------------------------------
INSERT INTO ingredients (user_id, ingredient_name, quantity, unit, price_per_unit, reorder_level) VALUES
    (@admin_id, 'Rice',     0, 'kg',     60, 10),
    (@admin_id, 'Paneer',   0, 'kg',    320,  8),
    (@admin_id, 'Onions',   0, 'kg',     35,  5),
    (@admin_id, 'Potatoes', 0, 'kg',     30,  5),
    (@admin_id, 'Milk',     0, 'litres', 60,  5);

-- ---------------------------------------------------------------------
-- 3. PURCHASES - look up the ids we just created
-- ---------------------------------------------------------------------
SET @goa_mart    = (SELECT supplier_id FROM suppliers WHERE user_id=@admin_id AND supplier_name='Goa Wholesale Mart');
SET @daily_dairy = (SELECT supplier_id FROM suppliers WHERE user_id=@admin_id AND supplier_name='Daily Dairy');
SET @rice        = (SELECT ingredient_id FROM ingredients WHERE user_id=@admin_id AND ingredient_name='Rice');
SET @paneer_new  = (SELECT ingredient_id FROM ingredients WHERE user_id=@admin_id AND ingredient_name='Paneer' ORDER BY ingredient_id DESC LIMIT 1);

INSERT INTO purchases (user_id, supplier_id, ingredient_id, quantity_purchased, purchase_date, total_cost) VALUES
    (@admin_id, @goa_mart,    @rice,       10, '2026-11-10',  600),
    (@admin_id, @daily_dairy, @paneer_new,  5, '2026-11-10', 1600);

-- Keep stock in sync: every purchase increases available stock.
UPDATE ingredients SET quantity = quantity + 10 WHERE ingredient_id = @rice;
UPDATE ingredients SET quantity = quantity + 5  WHERE ingredient_id = @paneer_new;

SELECT 'User data added' AS status;
