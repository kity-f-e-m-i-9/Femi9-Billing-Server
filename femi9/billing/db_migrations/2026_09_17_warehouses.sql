-- Physical storage-location tagging for stock (H1, G1, G2...), independent of
-- which company entity (LLP/Healthcare/Neksomo) the stock belongs to. A single
-- warehouse can hold stock from any/all company entities.
--
-- Named "warehouse" rather than "godown" to avoid colliding with two existing,
-- unrelated uses of that word already in the schema: company_godown (the
-- LLP/Healthcare/Neksomo company entities themselves) and the stockist/
-- super_distributor internal-transfer "godown" workflow. The company UI and
-- the new read-only login may still label this "Godown" to users.
-- Applied: 2026-09-17

CREATE TABLE warehouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_warehouses_code (code)
);

-- Nullable: existing stock rows are unassigned (NULL) until tagged, so this
-- adds a new optional dimension without disturbing current stock reports.
ALTER TABLE stock
  ADD COLUMN warehouse_id INT NULL AFTER user_id,
  ADD INDEX idx_stock_warehouse (warehouse_id, product_id);

-- Login table for the new read-only warehouse-stock-viewer account type,
-- following the same shape as track_users (mobile+password, central-login
-- compatible via getUserConfig()/getCentralLoginTypes()).
CREATE TABLE warehouse_users (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  password VARCHAR(255) NOT NULL,
  account_status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_warehouse_users_mobile (mobile)
);
