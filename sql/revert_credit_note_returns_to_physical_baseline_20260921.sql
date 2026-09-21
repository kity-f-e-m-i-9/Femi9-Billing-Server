-- ============================================================
-- Revert LLP closing_qty for 7 products back to their physical baseline,
-- undoing today's (2026-09-21) credit-note return activity entirely.
--
-- Background: cnote_delete.php reversed stock with a raw, un-logged
-- UPDATE even for return items that were never actually finished, and
-- separately the accounts team deleted a batch of already-finished
-- returns, corrupting closing_qty for these products (some went
-- negative). Per instruction, rather than trying to reconstruct which
-- return credits were legitimate, this reverts all 7 products straight
-- to their known-good physical-count baseline. The credit notes
-- themselves should be re-entered fresh through the app once
-- cnote_delete.php's soft-delete fix (see femi9/billing/company/
-- cnote_delete.php and cnote_del.php, migration
-- 2026_09_21_return_stock_soft_delete.sql) is deployed.
--
-- Targets (company godown 'FEMI NAYAN LLP' only):
--   product 4  (180mm 30pc)          -> 387
--   product 9  (330mm XL 9pc)        -> 2359
--   product 11 (290mm L 9pc)         -> 3083
--   product 14 (330mm XL 6pc)        -> 494
--   product 15 (330mm XL 3pc)        -> 506
--   product 16 (290mm L 6pc)         -> 506
--   product 17 (290mm L 3pc)         -> 506
--
-- What this does: sets stock.closing_qty directly to the physical count
-- for each product. Does NOT delete or alter any existing stock_ledger
-- rows -- every past write stays as a permanent audit trail. Adds ONE new
-- 'adjustment' ledger row per product actually changed, recording the
-- correction with real qty_before/qty_after.
--
-- SAFE TO RE-RUN: reads the CURRENT closing_qty live via subquery and
-- writes only the delta needed to reach each target, so running this
-- twice in a row is a no-op the second time for any product already at
-- target (delta becomes 0, no-op UPDATE, no ledger row).
-- ============================================================

-- ---- PRE-FLIGHT (read-only) ----
SELECT id AS godown_id, gname FROM company_godown WHERE gname = 'FEMI NAYAN LLP';

SELECT product_id, closing_qty AS current_closing_qty
FROM stock
WHERE product_id IN (4, 9, 11, 14, 15, 16, 17) AND user_type = 'company'
  AND user_id = (SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1)
  AND warehouse_id IS NULL
ORDER BY product_id;
-- Compare against targets: 4->387, 9->2359, 11->3083, 14->494, 15->506, 16->506, 17->506.

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
       'adjustment', 'revert_to_physical_baseline_20260921',
       'Revert to physical baseline (387); credit-note returns to be re-entered fresh',
       'cnote-delete-baseline-revert-20260921'
WHERE @p4_delta <> 0;

-- ---------- PRODUCT 9 (330mm XL 9pc) -> target 2359 ----------
SET @p9_before = (SELECT closing_qty FROM stock WHERE product_id=9 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p9_target = 2359;
SET @p9_delta  = @p9_target - @p9_before;

UPDATE stock SET closing_qty = @p9_target
 WHERE product_id = 9 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 9, 'company', @llp_id, NULL,
       IF(@p9_delta >= 0, 'credit', 'deduct'), ABS(@p9_delta), @p9_before, @p9_target,
       'adjustment', 'revert_to_physical_baseline_20260921',
       'Revert to physical baseline (2359); credit-note returns to be re-entered fresh',
       'cnote-delete-baseline-revert-20260921'
WHERE @p9_delta <> 0;

-- ---------- PRODUCT 11 (290mm L 9pc) -> target 3083 ----------
SET @p11_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p11_target = 3083;
SET @p11_delta  = @p11_target - @p11_before;

UPDATE stock SET closing_qty = @p11_target
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 11, 'company', @llp_id, NULL,
       IF(@p11_delta >= 0, 'credit', 'deduct'), ABS(@p11_delta), @p11_before, @p11_target,
       'adjustment', 'revert_to_physical_baseline_20260921',
       'Revert to physical baseline (3083); credit-note returns to be re-entered fresh',
       'cnote-delete-baseline-revert-20260921'
WHERE @p11_delta <> 0;

-- ---------- PRODUCT 14 (330mm XL 6pc) -> target 494 ----------
SET @p14_before = (SELECT closing_qty FROM stock WHERE product_id=14 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p14_target = 494;
SET @p14_delta  = @p14_target - @p14_before;

UPDATE stock SET closing_qty = @p14_target
 WHERE product_id = 14 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 14, 'company', @llp_id, NULL,
       IF(@p14_delta >= 0, 'credit', 'deduct'), ABS(@p14_delta), @p14_before, @p14_target,
       'adjustment', 'revert_to_physical_baseline_20260921',
       'Revert to physical baseline (494); credit-note returns to be re-entered fresh',
       'cnote-delete-baseline-revert-20260921'
WHERE @p14_delta <> 0;

-- ---------- PRODUCT 15 (330mm XL 3pc) -> target 506 ----------
SET @p15_before = (SELECT closing_qty FROM stock WHERE product_id=15 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p15_target = 506;
SET @p15_delta  = @p15_target - @p15_before;

UPDATE stock SET closing_qty = @p15_target
 WHERE product_id = 15 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 15, 'company', @llp_id, NULL,
       IF(@p15_delta >= 0, 'credit', 'deduct'), ABS(@p15_delta), @p15_before, @p15_target,
       'adjustment', 'revert_to_physical_baseline_20260921',
       'Revert to physical baseline (506); credit-note returns to be re-entered fresh',
       'cnote-delete-baseline-revert-20260921'
WHERE @p15_delta <> 0;

-- ---------- PRODUCT 16 (290mm L 6pc) -> target 506 ----------
SET @p16_before = (SELECT closing_qty FROM stock WHERE product_id=16 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p16_target = 506;
SET @p16_delta  = @p16_target - @p16_before;

UPDATE stock SET closing_qty = @p16_target
 WHERE product_id = 16 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 16, 'company', @llp_id, NULL,
       IF(@p16_delta >= 0, 'credit', 'deduct'), ABS(@p16_delta), @p16_before, @p16_target,
       'adjustment', 'revert_to_physical_baseline_20260921',
       'Revert to physical baseline (506); credit-note returns to be re-entered fresh',
       'cnote-delete-baseline-revert-20260921'
WHERE @p16_delta <> 0;

-- ---------- PRODUCT 17 (290mm L 3pc) -> target 506 ----------
SET @p17_before = (SELECT closing_qty FROM stock WHERE product_id=17 AND user_type='company' AND user_id=@llp_id AND warehouse_id IS NULL);
SET @p17_target = 506;
SET @p17_delta  = @p17_target - @p17_before;

UPDATE stock SET closing_qty = @p17_target
 WHERE product_id = 17 AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL;

INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
SELECT 17, 'company', @llp_id, NULL,
       IF(@p17_delta >= 0, 'credit', 'deduct'), ABS(@p17_delta), @p17_before, @p17_target,
       'adjustment', 'revert_to_physical_baseline_20260921',
       'Revert to physical baseline (506); credit-note returns to be re-entered fresh',
       'cnote-delete-baseline-revert-20260921'
WHERE @p17_delta <> 0;

-- ---- VERIFY before committing ----
SELECT product_id, closing_qty FROM stock
WHERE product_id IN (4, 9, 11, 14, 15, 16, 17) AND user_type = 'company' AND user_id = @llp_id AND warehouse_id IS NULL
ORDER BY product_id;
-- Expect: 4->387, 9->2359, 11->3083, 14->494, 15->506, 16->506, 17->506.

COMMIT;
-- If the verification above doesn't match, run ROLLBACK; instead.
