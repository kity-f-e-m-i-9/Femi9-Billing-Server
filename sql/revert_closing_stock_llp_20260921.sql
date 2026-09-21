-- ============================================================
-- Revert LLP closing_qty for 4 products back to their physical baseline
-- Date written: 2026-09-21
--
-- Covers:
--   product 4  (180mm 30pc)      -> target 387
--   product 11 (290mm L 9pc)     -> target 3083
--   product 9  (330mm XL 9pc)    -> target 2359
--   product 16 (290mm L 6pc)     -> target 506
-- (Product 14 (330mm XL 6pc) was checked and already reads correctly at
-- 494 -- no correction needed, so it's intentionally left out of this
-- script rather than touched for the sake of completeness.)
--
-- What this does: sets stock.closing_qty directly to the known-correct
-- physical count for each of these four products at FEMI NAYAN LLP only.
-- Per explicit instruction, this does NOT delete or alter any existing
-- stock_ledger rows -- every past write (the phantom-transfer reversal,
-- the confirm-draft deductions, and whatever separate stock-adding
-- event(s) happened afterward) stays in the ledger exactly as-is, as a
-- permanent audit trail. This script only adds ONE new 'adjustment'
-- ledger row per product, recording the correction itself with the real
-- qty_before/qty_after, so the ledger still reads as a complete, honest
-- history -- "the count didn't match, so it was corrected here" -- rather
-- than silently rewriting the past.
--
-- Scope: LLP (company_godown 'FEMI NAYAN LLP') only, these four products
-- only. Neksomo and Healthcare's stock rows are NOT touched -- if
-- whatever separate event also affected their books, that's a distinct
-- correction to make deliberately, not a side effect of this one.
--
-- SAFE TO RE-RUN: reads the CURRENT closing_qty live via subquery and
-- writes only the delta needed to reach each target, so running this
-- twice in a row is a no-op the second time (delta becomes 0 for every
-- product, no-op UPDATE, no ledger row for a zero-qty adjustment).
-- ============================================================

-- ---- PRE-FLIGHT (read-only) ----
SELECT id AS godown_id, gname FROM company_godown WHERE gname = 'FEMI NAYAN LLP';

SELECT product_id, closing_qty AS current_closing_qty
FROM stock
WHERE product_id IN (4, 9, 11, 16) AND user_type = 'company'
  AND user_id = (SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1)
  AND warehouse_id IS NULL;
-- Compare against target: product 4 -> 387, product 9 -> 2359,
-- product 11 -> 3083, product 16 -> 506.

START TRANSACTION;

SET @llp_id = (SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1);

-- ---------- PRODUCT 4 (180mm 30pc) -> target 387 ----------
SET @p4_before = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p4_target = 387;
SET @p4_delta  = @p4_target - @p4_before;

UPDATE stock SET closing_qty = @p4_target
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 4, 'company', @llp_id, NULL,
       IF(@p4_delta >= 0, 'credit', 'deduct'), ABS(@p4_delta), @p4_before, @p4_target,
       'adjustment', 'closing_stock_correction_20260921',
       'Manual correction: closing_qty reset to physical count (387). No prior ledger rows altered or deleted.',
       'closing-stock-revert-20260921'
WHERE @p4_delta <> 0;

-- ---------- PRODUCT 11 (290mm L 9pc) -> target 3083 ----------
SET @p11_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p11_target = 3083;
SET @p11_delta  = @p11_target - @p11_before;

UPDATE stock SET closing_qty = @p11_target
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 11, 'company', @llp_id, NULL,
       IF(@p11_delta >= 0, 'credit', 'deduct'), ABS(@p11_delta), @p11_before, @p11_target,
       'adjustment', 'closing_stock_correction_20260921',
       'Manual correction: closing_qty reset to physical count (3083). No prior ledger rows altered or deleted.',
       'closing-stock-revert-20260921'
WHERE @p11_delta <> 0;

-- ---------- PRODUCT 9 (330mm XL 9pc) -> target 2359 ----------
SET @p9_before = (SELECT closing_qty FROM stock WHERE product_id=9 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p9_target = 2359;
SET @p9_delta  = @p9_target - @p9_before;

UPDATE stock SET closing_qty = @p9_target
 WHERE product_id = 9 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 9, 'company', @llp_id, NULL,
       IF(@p9_delta >= 0, 'credit', 'deduct'), ABS(@p9_delta), @p9_before, @p9_target,
       'adjustment', 'closing_stock_correction_20260921',
       'Manual correction: closing_qty reset to physical count (2359). No prior ledger rows altered or deleted.',
       'closing-stock-revert-20260921'
WHERE @p9_delta <> 0;

-- ---------- PRODUCT 16 (290mm L 6pc) -> target 506 ----------
SET @p16_before = (SELECT closing_qty FROM stock WHERE product_id=16 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p16_target = 506;
SET @p16_delta  = @p16_target - @p16_before;

UPDATE stock SET closing_qty = @p16_target
 WHERE product_id = 16 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 16, 'company', @llp_id, NULL,
       IF(@p16_delta >= 0, 'credit', 'deduct'), ABS(@p16_delta), @p16_before, @p16_target,
       'adjustment', 'closing_stock_correction_20260921',
       'Manual correction: closing_qty reset to physical count (506). No prior ledger rows altered or deleted.',
       'closing-stock-revert-20260921'
WHERE @p16_delta <> 0;

-- ---- VERIFY before committing ----
SELECT product_id, closing_qty FROM stock
WHERE product_id IN (4, 9, 11, 16) AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;
-- Expect: product 4 -> 387, product 9 -> 2359, product 11 -> 3083, product 16 -> 506.

COMMIT;
-- If the verification above doesn't match, run ROLLBACK; instead.
