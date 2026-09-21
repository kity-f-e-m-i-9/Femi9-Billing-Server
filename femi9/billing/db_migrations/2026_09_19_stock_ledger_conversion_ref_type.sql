-- ============================================================
-- Add 'conversion' to stock_ledger.ref_type ENUM
-- Date: 2026-09-19
-- Fix: StockService::convertPiecesToPack()/convertPackToPieces() (used by
--      neksomo-piece-pack-convert-action.php, both the original single-
--      product path and the newer raw-pool-mapped-product path) pass
--      ref_type='conversion' but stock_ledger.ref_type ENUM did not
--      include it. This caused "Data truncated for column 'ref_type'"
--      on every Convert Pieces <-> Packs submission.
-- ============================================================

ALTER TABLE `stock_ledger`
  MODIFY COLUMN `ref_type` ENUM(
    'invoice',
    'user_invoice',
    'return',
    'transfer',
    'ot_sale',
    'adjustment',
    'demofree',
    'tp_invoice',
    'conversion'
  ) NOT NULL;
