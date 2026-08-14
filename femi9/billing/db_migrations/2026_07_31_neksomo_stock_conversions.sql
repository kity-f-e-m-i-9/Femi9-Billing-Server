-- Tracks how much of a Neksomo piece/pack product's purchased-minus-sold pool
-- has been drawn down to top up a *mapped* company product's real `stock`
-- row at the NEKSOMO HYGIENE INDUSTRIES godown. Needed because Neksomo's own
-- purchases (neksomo_purchase_items) never touch the standard `stock` table
-- that internal transfers, invoices, OT sales, etc. all read/write via
-- StockService — without this, finance-side transactions against a
-- Neksomo-sourced product always saw 0 available stock.
--
-- One neksomo_product_id can map to several company pack-size SKUs sharing
-- the same physical pool (e.g. 330mm pieces -> 3pc/6pc/9pc packs), so this is
-- a ledger of draws against that shared pool, not a per-product balance.
-- Self-migrating via ensure_neksomo_stock_conversions_table() in
-- NeksomoStockBridge.php — this file exists for production deploys that run
-- migrations directly.
CREATE TABLE IF NOT EXISTS `neksomo_stock_conversions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `neksomo_product_id` INT NOT NULL,
  `company_product_id` INT NOT NULL,
  `qty_neksomo_unit` INT NOT NULL,
  `qty_company_packs` INT NOT NULL,
  `created_by` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_nsc_neksomo` (`neksomo_product_id`),
  KEY `idx_nsc_company` (`company_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
