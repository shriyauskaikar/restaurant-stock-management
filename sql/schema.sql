-- =====================================================================
--  RESTAURANT FOOD STOCK MANAGEMENT SYSTEM
--  Database creation script  (MySQL Server 8.0)
--
--  HOW TO RUN THIS FILE
--  -------------------------------------------------------------------
--  1. Open MySQL Workbench.
--  2. Click the "Local instance MySQL80" connection in the home screen.
--     (If you cannot see it, see section 11 of README.md.)
--  3. Click "File > New Query Tab" (or press Ctrl+T).
--  4. Click "Open" in the query tab, choose this file
--     (sql/schema.sql), and press the LIGHTNING BOLT (Execute).
--  5. The Output pane should say "Database created successfully".
--
--  NOTE
--  ----
--  This script starts with DROP TABLE IF EXISTS, so it can be re-run
--  from scratch. Re-running it DELETES the data in those tables.
--  =====================================================================

-- ---------------------------------------------------------------------
-- STEP 1 : create the database
-- utf8mb4 stores every character correctly, including Indian names
-- and the rupee sign.
-- ---------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS restaurant_stock_management
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE restaurant_stock_management;

-- ---------------------------------------------------------------------
-- STEP 2 : drop the old tables
-- The child table (purchases) is dropped first, because it holds the
-- foreign keys that point at the other two tables. You cannot drop a
-- table while another table still refers to it.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;

-- =====================================================================
-- TABLE 1 : USERS
-- ---------------------------------------------------------------------
-- This table stores the login accounts of the application.
--
-- The password column NEVER stores the plain password. It stores a
-- hash produced by PHP's password_hash() function (bcrypt). A hash is
-- a one-way value: you can check a typed password against it with
-- password_verify(), but you cannot turn the hash back into the
-- original password. That is why a stolen database does not reveal
-- anybody's password.
--
-- The initial admin account is NOT inserted here. It is created by
-- the setup script (see the bottom of this file and README.md).
-- =====================================================================
CREATE TABLE users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(120) NOT NULL UNIQUE,
    -- 255 characters is enough room for a bcrypt hash (it is 60 chars).
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =====================================================================
-- TABLE 2 : SUPPLIERS
-- ---------------------------------------------------------------------
-- The companies that sell us ingredients.
--
-- EVERY table below has a user_id column. That is what makes the data
-- private: when user A logs in, only rows where user_id = A are shown.
-- The FK below links each supplier to the account that owns it.
-- =====================================================================
CREATE TABLE suppliers (
    supplier_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    supplier_name VARCHAR(100) NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    address       VARCHAR(255) NOT NULL,
    CONSTRAINT fk_supplier_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_suppliers_user (user_id),
    INDEX idx_suppliers_name (supplier_name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =====================================================================
-- TABLE 3 : INGREDIENTS
-- ---------------------------------------------------------------------
-- One row per ingredient.
--   quantity       = the CURRENT AVAILABLE STOCK
--   reorder_level  = the minimum stock we want to keep.
--                    When quantity <= reorder_level the ingredient is
--                    flagged as LOW STOCK and appears on the alerts page.
--   price_per_unit = the price of one unit, used to work out the cost
--                    of a purchase.
-- =====================================================================
CREATE TABLE ingredients (
    ingredient_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    -- UNIQUE is now on (user_id, ingredient_name): two different users
    -- may both track "Rice", but one user cannot add it twice.
    ingredient_name VARCHAR(100) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit            VARCHAR(20)  NOT NULL DEFAULT 'kg',
    price_per_unit  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reorder_level   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_ingredient_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_ingredient_per_user (user_id, ingredient_name),
    INDEX idx_ingredients_stock (quantity, reorder_level)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =====================================================================
-- TABLE 4 : PURCHASES
-- ---------------------------------------------------------------------
-- Every purchase record. One supplier + one ingredient per row.
--
-- This table is the JOIN between suppliers and ingredients, which is
-- where their many-to-many (M:N) relationship is stored.
--
-- ON DELETE RESTRICT protects the data: a supplier or an ingredient
-- that is used in a purchase cannot be deleted, so we never end up
-- with a purchase pointing at something that no longer exists.
-- =====================================================================
CREATE TABLE purchases (
    purchase_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT          NOT NULL,
    supplier_id        INT          NOT NULL,
    ingredient_id      INT          NOT NULL,
    quantity_purchased DECIMAL(10,2) NOT NULL,
    purchase_date      DATE         NOT NULL,
    total_cost         DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    -- The purchase also belongs to the same user as its supplier and
    -- ingredient. ON DELETE CASCADE means deleting an account removes
    -- all of that user's inventory data with it.
    CONSTRAINT fk_purchase_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_purchase_supplier
        FOREIGN KEY (supplier_id)   REFERENCES suppliers(supplier_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_purchase_ingredient
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    INDEX idx_purchases_supplier   (supplier_id),
    INDEX idx_purchases_ingredient (ingredient_id),
    INDEX idx_purchases_date       (purchase_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ---------------------------------------------------------------------
-- STEP 3 : create the first admin account
-- ---------------------------------------------------------------------
-- The admin account is NOT created here on purpose.
--
-- The password has to be stored as a bcrypt HASH, and the only correct
-- way to produce that hash is PHP's password_hash() function. So the
-- account is created by a small PHP script instead of by this SQL file:
--
--     php setup/create_admin.php
--
-- That script hashes the password with password_hash() and inserts the
-- row, so the plain password is never written to the database.
--
-- See README.md section 3 for the full walkthrough.

-- ---------------------------------------------------------------------
-- STEP 4 : sanity check
-- ---------------------------------------------------------------------
SELECT 'Database created successfully' AS status;
SHOW TABLES;
