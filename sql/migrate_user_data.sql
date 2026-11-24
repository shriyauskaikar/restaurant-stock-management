-- =====================================================================
--  MIGRATION : make every inventory row belong to a user
-- ---------------------------------------------------------------------
--  Run this ONCE in MySQL Workbench on an EXISTING database created by
--  the older version of schema.sql (the one without user_id).
--
--  WHAT IT DOES
--   1. Adds a user_id column to suppliers / ingredients / purchases.
--   2. Gives all existing rows to the "admin" account, so nothing you
--      already entered is lost.
--   3. Makes the columns NOT NULL and adds the foreign keys + indexes.
--
--  After this, new accounts start with EMPTY inventory, which is the
--  whole point: each user sees only their own dashboard/stock/purchases.
-- =====================================================================

USE restaurant_stock_management;

-- ---------------------------------------------------------------------
-- STEP 1 : add the new columns (nullable at first so existing rows work)
-- ---------------------------------------------------------------------
ALTER TABLE suppliers   ADD COLUMN user_id INT NULL AFTER supplier_id;
ALTER TABLE ingredients ADD COLUMN user_id INT NULL AFTER ingredient_id;
ALTER TABLE purchases   ADD COLUMN user_id INT NULL AFTER purchase_id;

-- ---------------------------------------------------------------------
-- STEP 2 : assign existing data to the admin account
-- ---------------------------------------------------------------------
SET @admin_id = (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1);

-- If there is no admin yet, create a temporary one so the FKs have a
-- target. Run setup/create_admin.php afterwards to set a real password.
INSERT INTO users (username, email, password)
SELECT 'admin', 'admin@restaurant.local', '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva'
WHERE @admin_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

SET @admin_id = (SELECT user_id FROM users WHERE username = 'admin' LIMIT 1);

UPDATE suppliers   SET user_id = @admin_id WHERE user_id IS NULL;
UPDATE ingredients SET user_id = @admin_id WHERE user_id IS NULL;
UPDATE purchases   SET user_id = @admin_id WHERE user_id IS NULL;

-- ---------------------------------------------------------------------
-- STEP 3 : enforce the rules
-- ---------------------------------------------------------------------
ALTER TABLE suppliers   MODIFY user_id INT NOT NULL;
ALTER TABLE ingredients MODIFY user_id INT NOT NULL;
ALTER TABLE purchases   MODIFY user_id INT NOT NULL;

-- The old UNIQUE on ingredient_name must go: two users may both have
-- "Rice". Replace it with a per-user unique key.
ALTER TABLE ingredients DROP INDEX ingredient_name;
ALTER TABLE ingredients ADD UNIQUE KEY uq_ingredient_per_user (user_id, ingredient_name);

ALTER TABLE suppliers
    ADD CONSTRAINT fk_supplier_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    ADD INDEX idx_suppliers_user (user_id);

ALTER TABLE ingredients
    ADD CONSTRAINT fk_ingredient_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE purchases
    ADD CONSTRAINT fk_purchase_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    ADD INDEX idx_purchases_user (user_id);

SELECT 'Migration complete - all existing data now belongs to admin' AS status;
SELECT user_id, username, email FROM users;
SELECT user_id, COUNT(*) AS suppliers FROM suppliers GROUP BY user_id;
SELECT user_id, COUNT(*) AS ingredients FROM ingredients GROUP BY user_id;
SELECT user_id, COUNT(*) AS purchases FROM purchases GROUP BY user_id;
