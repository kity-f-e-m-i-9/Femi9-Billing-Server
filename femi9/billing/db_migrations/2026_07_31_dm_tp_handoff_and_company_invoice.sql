-- DM -> TP order handoff, void/change-history, and company-direct-invoice
-- (Channel Partner / godown stock) features. Run once against production.
--
-- Safe to re-run: every statement checks existence first via INFORMATION_SCHEMA
-- (MySQL 8 / MariaDB 10.5+). If your server is older and IF NOT EXISTS on
-- ADD COLUMN / CREATE INDEX errors out, run each block individually and skip
-- any that fail with "duplicate column/key" (harmless, means already applied).

ALTER TABLE ms_orders
  ADD COLUMN IF NOT EXISTS tp_id INT NULL DEFAULT NULL AFTER ms_id;

ALTER TABLE shop
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS source_ms_shop_id INT NULL DEFAULT NULL;

ALTER TABLE tp_orders
  ADD COLUMN IF NOT EXISTS assigned_by_ms_id INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS invoiced_inv_id VARCHAR(60) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS voided_at DATETIME NULL DEFAULT NULL;

ALTER TABLE user_invoice
  ADD COLUMN IF NOT EXISTS voided_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS voided_by_user_type VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS voided_by_user_id VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS source_ms_order_id VARCHAR(255) NULL DEFAULT NULL AFTER inv_id;

-- status column should already exist as enum('draft','submitted','cancelled')
-- from earlier work; left untouched here.

CREATE TABLE IF NOT EXISTS shop_invoice_change_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inv_id VARCHAR(255) NOT NULL,
  pr_id INT NULL,
  change_type ENUM('initial','added','removed','qty_changed','voided') NOT NULL,
  qty_before INT NULL,
  qty_after INT NULL,
  changed_by_user_type VARCHAR(50) NOT NULL,
  changed_by_user_id VARCHAR(50) NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_id (inv_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Indexes (create only if missing).
SET @idx_exists := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE table_schema = DATABASE() AND table_name = 'tp_orders' AND index_name = 'idx_invoiced_inv_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_invoiced_inv_id ON tp_orders (invoiced_inv_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE table_schema = DATABASE() AND table_name = 'user_invoice' AND index_name = 'idx_source_ms_order_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_source_ms_order_id ON user_invoice (source_ms_order_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
