-- ============================================================
-- Add 'demofree' to stock_ledger.ref_type ENUM
-- Date: 2026-06-26
-- Fix: company/stockist/distributor/super_distributor/super-stockist
--      demofree_action.php all pass ref_type='demofree' to StockService
--      but stock_ledger.ref_type ENUM did not include 'demofree'.
--      This caused "Data truncated for column 'ref_type'" on every add.
-- ============================================================

ALTER TABLE `stock_ledger`
  MODIFY COLUMN `ref_type` ENUM(
    'invoice',
    'user_invoice',
    'return',
    'transfer',
    'ot_sale',
    'adjustment',
    'demofree'
  ) NOT NULL;
