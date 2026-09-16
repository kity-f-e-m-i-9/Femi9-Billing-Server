-- ============================================================================
-- Neksomo FIFO Gross Profit — Phase 1 — Production migration
-- Run this ONCE against production, in this exact order (both statements
-- below already run top-to-bottom as written; do not reorder or split).
--
-- What this does:
--   1. Creates stock_lots + stock_ledger_lot_consumption (new tables only —
--      no existing table is altered or has rows changed).
--   2. Seeds one "opening lot" per company-side product/godown with stock
--      currently on hand, so FIFO costing has something to draw from
--      immediately after deploy instead of waiting for the next purchase.
--
-- Safe to run on a live production database: no ALTER TABLE, no data in any
-- existing table is modified, and CREATE TABLE will simply fail loudly
-- (rather than corrupt anything) if a table of that name already exists —
-- which is the correct behavior if this is accidentally run twice.
--
-- Verified locally: applied cleanly, seeded 63 opening lots (2 pre-existing
-- `stock` rows referencing already-deleted products were correctly skipped —
-- expected, harmless).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Step 1: new tables
-- ---------------------------------------------------------------------------

CREATE TABLE stock_lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_type VARCHAR(32) NOT NULL,
  user_id VARCHAR(32) NOT NULL,
  rate DECIMAL(12,6) NOT NULL,
  qty_purchased INT NOT NULL,
  qty_remaining INT NOT NULL,
  purchase_date DATE NOT NULL,
  ref_type ENUM('llp_rate_entry','neksomo_purchase','transfer_in','opening_balance') NOT NULL,
  ref_id VARCHAR(64) NULL,
  created_by VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fifo_lookup (product_id, user_type, user_id, purchase_date, id),
  INDEX idx_qty_remaining (product_id, user_type, user_id, qty_remaining)
);

-- Which lot(s) a single stock_ledger deduct row actually drew from. A
-- stock_lot_id of NULL means the fallback rate-lookup was used because no
-- lot covered that portion of the sale (e.g. pre-migration stock, or a sale
-- source like TP invoices that never calls StockService at all).
CREATE TABLE stock_ledger_lot_consumption (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_ledger_id INT NOT NULL,
  stock_lot_id INT NULL,
  qty_taken INT NOT NULL,
  rate DECIMAL(12,6) NOT NULL,
  INDEX idx_ledger (stock_ledger_id),
  INDEX idx_lot (stock_lot_id)
);

-- ---------------------------------------------------------------------------
-- Step 2: opening-balance backfill
-- ---------------------------------------------------------------------------

-- Seed one opening lot per (product, holder) with existing stock, so
-- FIFO consumption has something to draw from immediately after this
-- migration instead of falling back to the blended rate for all
-- pre-cutover stock. Rate is today's "latest effective_date <= now" lookup
-- — the same fallback formula StockService::fallbackRate() uses, applied
-- once here for existing stock. Uses only company-side stock (StockLots
-- is only wired into the company StockService copy in this phase).
INSERT INTO stock_lots (product_id, user_type, user_id, rate, qty_purchased, qty_remaining, purchase_date, ref_type, created_by)
SELECT
    s.product_id, s.user_type, s.user_id,
    COALESCE(
        (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM neksomo_llp_piece_rates r
         WHERE r.product_id = p.id AND r.effective_date <= CURDATE()
         ORDER BY r.effective_date DESC LIMIT 1),
        (SELECT CASE WHEN fr.gst_type = 'inclusive' THEN fr.rate_per_piece / (1 + fr.gst_rate/100) ELSE fr.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM femi9_llp_sale_rates fr
         WHERE fr.product_id = p.id AND fr.effective_date <= CURDATE()
         ORDER BY fr.effective_date DESC LIMIT 1),
        0
    ) AS rate,
    s.closing_qty, s.closing_qty, CURDATE(), 'opening_balance', 'migration'
FROM stock s
JOIN products p ON p.id = s.product_id
WHERE s.closing_qty > 0
  AND s.user_type = 'company';
