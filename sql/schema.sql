-- =====================================================================
--  RESTAURANT FOOD STOCK MANAGEMENT SYSTEM
--  Database creation script
--
--  How to run:
--    1. Open MySQL Workbench and connect to your MySQL Server.
--    2. File > New Query Tab, open this file (or paste its contents).
--    3. Click the "lightning bolt" (Execute) button.
--
--  Note: the DROP TABLE lines let you re-run this script from scratch.
--        They delete any data already stored in the three tables.
-- =====================================================================

-- Create the database (utf8mb4 so Indian names / units store correctly)
CREATE DATABASE IF NOT EXISTS restaurant_stock_management
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE restaurant_stock_management;

-- Drop the child table first so the foreign keys can be rebuilt cleanly
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS suppliers;

-- ---------------------------------------------------------------------
-- TABLE 1 : SUPPLIERS
-- The company that sells us ingredients.
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
    supplier_id   INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    address       VARCHAR(255) NOT NULL,
    INDEX idx_suppliers_name (supplier_name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ---------------------------------------------------------------------
-- TABLE 2 : INGREDIENTS
-- One row per ingredient. "quantity" IS the current available stock.
-- "reorder_level" is the minimum stock we want to keep (low-stock alert).
-- ---------------------------------------------------------------------
CREATE TABLE ingredients (
    ingredient_id   INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_name VARCHAR(100) NOT NULL UNIQUE,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit            VARCHAR(20)  NOT NULL DEFAULT 'kg',
    price_per_unit  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reorder_level   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    INDEX idx_ingredients_stock (quantity, reorder_level)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ---------------------------------------------------------------------
-- TABLE 3 : PURCHASES
-- Every purchase record.  One supplier + one ingredient per row.
-- This table joins suppliers and ingredients (the M:N relationship).
-- ON DELETE RESTRICT protects referential integrity: a supplier or an
-- ingredient that is used in a purchase cannot be deleted.
-- ---------------------------------------------------------------------
CREATE TABLE purchases (
    purchase_id        INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id        INT          NOT NULL,
    ingredient_id      INT          NOT NULL,
    quantity_purchased DECIMAL(10,2) NOT NULL,
    purchase_date      DATE         NOT NULL,
    total_cost         DECIMAL(12,2) NOT NULL DEFAULT 0.00,

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
-- Sanity check: confirm the three tables were created.
-- ---------------------------------------------------------------------
SELECT 'Database created successfully' AS status;
SHOW TABLES;
