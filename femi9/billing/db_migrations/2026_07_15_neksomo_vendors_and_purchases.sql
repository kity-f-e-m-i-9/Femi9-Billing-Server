-- Neksomo "Purchase from Manufacturer": add a reusable vendor master
-- (name, address, GSTIN, phone, email — add once, select many times) and
-- restructure purchases from one-row-per-product into a header
-- (neksomo_manufacturer_purchases) + line items (neksomo_purchase_items),
-- with a manually typed, globally-unique invoice number on the header.
--
-- This is a single-tenant flow (only the neksomo/admin logins ever touch
-- this table), unlike the Super Stockist TP invoice case, so invoice_number
-- just needs a plain global UNIQUE constraint — no per-account scoping.
--
-- Old per-row columns on neksomo_manufacturer_purchases (product_id,
-- quantity_packs, cost_per_piece, total_cost, stock_ledger_id) are
-- deliberately NOT dropped — they're superseded by neksomo_purchase_items
-- but kept in place to avoid irreversible data loss if anything about this
-- migration needs to be revisited. Safe to drop later once the new flow is
-- confirmed stable.
--
-- Idempotent — safe to run multiple times (mirrors the guard pattern in
-- db_migrations/2026_07_02_tp_invoice_independent_sequence.sql).

CREATE TABLE IF NOT EXISTS `neksomo_vendors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_name` VARCHAR(255) NOT NULL,
  `address` TEXT,
  `gstin` VARCHAR(20) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vendor_name` (`vendor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP PROCEDURE IF EXISTS _neksomo_purchases_header_migration;
DELIMITER //
CREATE PROCEDURE _neksomo_purchases_header_migration()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'neksomo_manufacturer_purchases'
      AND COLUMN_NAME  = 'vendor_id'
  ) THEN
    ALTER TABLE `neksomo_manufacturer_purchases`
      ADD COLUMN `vendor_id` INT UNSIGNED NULL AFTER `id`,
      ADD COLUMN `invoice_number` VARCHAR(50) NULL AFTER `vendor_id`,
      ADD COLUMN `total_amount` DECIMAL(12,2) NULL AFTER `purchase_date`;

    -- Backfill a vendor per distinct historical manufacturer_name
    INSERT IGNORE INTO `neksomo_vendors` (`vendor_name`)
      SELECT DISTINCT TRIM(`manufacturer_name`) FROM `neksomo_manufacturer_purchases`;

    UPDATE `neksomo_manufacturer_purchases` mp
      JOIN `neksomo_vendors` v ON v.`vendor_name` = TRIM(mp.`manufacturer_name`)
      SET mp.`vendor_id` = v.`id`
      WHERE mp.`vendor_id` IS NULL;

    -- Legacy rows had no invoice number — mark them clearly so they can
    -- never collide with a real typed number going forward.
    UPDATE `neksomo_manufacturer_purchases`
      SET `invoice_number` = CONCAT('MP-LEGACY-', `id`)
      WHERE `invoice_number` IS NULL;

    UPDATE `neksomo_manufacturer_purchases`
      SET `total_amount` = `total_cost`
      WHERE `total_amount` IS NULL;
  END IF;
END //
DELIMITER ;
CALL _neksomo_purchases_header_migration();
DROP PROCEDURE IF EXISTS _neksomo_purchases_header_migration;

CREATE TABLE IF NOT EXISTS `neksomo_purchase_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` INT UNSIGNED NOT NULL,
  `product_id` INT NOT NULL,
  `quantity_packs` INT UNSIGNED NOT NULL,
  `cost_per_piece` DECIMAL(10,2) NOT NULL,
  `total_cost` DECIMAL(12,2) NOT NULL,
  `stock_ledger_id` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_npi_purchase` (`purchase_id`),
  KEY `idx_npi_product` (`product_id`),
  CONSTRAINT `fk_npi_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `neksomo_manufacturer_purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Move each existing header row's single product line into the new items
-- table (only for headers that don't already have items — safe to re-run).
INSERT INTO `neksomo_purchase_items`
  (`purchase_id`, `product_id`, `quantity_packs`, `cost_per_piece`, `total_cost`, `stock_ledger_id`)
  SELECT mp.`id`, mp.`product_id`, mp.`quantity_packs`, mp.`cost_per_piece`, mp.`total_cost`, mp.`stock_ledger_id`
  FROM `neksomo_manufacturer_purchases` mp
  WHERE mp.`product_id` IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM `neksomo_purchase_items` npi WHERE npi.`purchase_id` = mp.`id`);

-- Enforce the new constraints only once every row is guaranteed populated.
DROP PROCEDURE IF EXISTS _neksomo_purchases_finalize_columns;
DELIMITER //
CREATE PROCEDURE _neksomo_purchases_finalize_columns()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'neksomo_manufacturer_purchases'
      AND INDEX_NAME   = 'uk_invoice_number'
  ) THEN
    IF NOT EXISTS (SELECT 1 FROM `neksomo_manufacturer_purchases` WHERE `vendor_id` IS NULL OR `invoice_number` IS NULL OR `total_amount` IS NULL) THEN
      ALTER TABLE `neksomo_manufacturer_purchases`
        MODIFY COLUMN `vendor_id` INT UNSIGNED NOT NULL,
        MODIFY COLUMN `invoice_number` VARCHAR(50) NOT NULL,
        MODIFY COLUMN `total_amount` DECIMAL(12,2) NOT NULL,
        ADD UNIQUE KEY `uk_invoice_number` (`invoice_number`),
        ADD KEY `idx_mp_vendor` (`vendor_id`);
    END IF;
  END IF;
END //
DELIMITER ;
CALL _neksomo_purchases_finalize_columns();
DROP PROCEDURE IF EXISTS _neksomo_purchases_finalize_columns;
