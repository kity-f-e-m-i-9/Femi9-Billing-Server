-- ============================================================
-- Schema changes — SS→TP transfer, advance log, stock gate
-- Date: 2026-06-24
-- Apply to any environment that doesn't have these yet
-- ============================================================

-- 1. Extend territory_partner_stock_ledger.action enum
ALTER TABLE `territory_partner_stock_ledger`
  MODIFY COLUMN `action` ENUM(
    'opening',
    'credit',
    'deduct',
    'adjustment',
    'return',
    'internal_transfer_in',
    'internal_transfer_in_reverse'
  ) NOT NULL;

-- 2. Extend territory_partner_stock_ledger.ref_type enum
ALTER TABLE `territory_partner_stock_ledger`
  MODIFY COLUMN `ref_type` ENUM(
    'tp_invoice',
    'adjustment',
    'opening',
    'manual_input',
    'demofree',
    'credit_note',
    'internal_transfer'
  ) NOT NULL;

-- 3. Extend stock_ledger.action enum
ALTER TABLE `stock_ledger`
  MODIFY COLUMN `action` ENUM(
    'deduct',
    'credit',
    'reverse_deduct',
    'reverse_credit',
    'transfer_out',
    'transfer_in',
    'transfer_out_reverse',
    'transfer_in_reverse',
    'return_accept',
    'return_reject',
    'ot_deduct',
    'ot_reverse'
  ) NOT NULL;

-- 4. Create advance payment log table
CREATE TABLE IF NOT EXISTS `tp_invoice_advance_log` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tp_invoice_id`     INT UNSIGNED  NOT NULL,
  `tp_invoice_number` VARCHAR(100)  NOT NULL,
  `tp_advance_id`     INT UNSIGNED  NOT NULL,
  `deducted_amount`   DECIMAL(12,2) NOT NULL,
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_invoice` (`tp_invoice_id`),
  KEY `idx_log_advance` (`tp_advance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add stock_initialized flag to territory_partners (safe — skips if column exists)
DROP PROCEDURE IF EXISTS _add_stock_initialized;
DELIMITER //
CREATE PROCEDURE _add_stock_initialized()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'territory_partners'
      AND COLUMN_NAME  = 'stock_initialized'
  ) THEN
    ALTER TABLE `territory_partners`
      ADD COLUMN `stock_initialized` TINYINT(1) NOT NULL DEFAULT 0;
  END IF;
END //
DELIMITER ;
CALL _add_stock_initialized();
DROP PROCEDURE IF EXISTS _add_stock_initialized;

-- 6. Backfill: mark TPs that already have manual_input ledger entries as initialized
UPDATE `territory_partners` tp
SET `stock_initialized` = 1
WHERE EXISTS (
  SELECT 1 FROM `territory_partner_stock_ledger` tpsl
  WHERE tpsl.territory_partner_id = tp.id
    AND tpsl.ref_type = 'manual_input'
);
