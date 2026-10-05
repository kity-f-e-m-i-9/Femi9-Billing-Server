-- ============================================================================
-- OT returns: sweep stock left in the NULL-warehouse bucket into FEMI NAYAN LLP / G1
-- ============================================================================
-- Background:
--   Before 2026-09-26 ot-sale-return.php / ot-sale-delete.php called otReverse()
--   without warehouse_id, and until the 2026-10-05 fix ot-return-delete.php
--   called otDeduct() without it too. Those calls hit the (LLP godown, NULL
--   warehouse) stock bucket instead of G1, which is where
--   migrate_ot_sales_to_llp_g1_20260926.sql moved all OT stock.
--
-- This script is SAFE TO RUN IN TWO PASSES:
--   Pass 1: run STEP 0 + STEP 1 only (read-only SELECTs). Send me the output
--           if anything looks odd. If STEP 1A returns 0 rows you are done.
--   Pass 2: run STEP 2 inside the transaction, check STEP 3, then COMMIT.
--
-- Take a fresh dump of `stock` first:  mysqldump <db> stock > stock_before_ot_sweep.sql
-- ============================================================================

-- STEP 0: resolve ids (both must be non-NULL)
SET @llp_godown_id   = (SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1);
SET @g1_warehouse_id = (SELECT id FROM warehouses WHERE code = 'G1' LIMIT 1);
SELECT @llp_godown_id AS llp_godown_id, @g1_warehouse_id AS g1_warehouse_id;

-- ---------------------------------------------------------------------------
-- STEP 1 (read-only diagnostics)
-- ---------------------------------------------------------------------------
-- 1A: stray NULL-warehouse stock rows at the LLP godown, for products that
--     have OT sales. These are what need sweeping into G1.
SELECT s.id AS stock_id, s.product_id, s.closing_qty, s.sales_qty, s.returnqty,
       g1.id AS g1_stock_id, g1.closing_qty AS g1_closing_qty
FROM stock s
LEFT JOIN stock g1
       ON g1.product_id = s.product_id
      AND g1.user_type  = 'company'
      AND g1.user_id    = s.user_id
      AND g1.warehouse_id = @g1_warehouse_id
WHERE s.user_type = 'company'
  AND s.user_id   = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
  AND s.warehouse_id IS NULL
  AND EXISTS (SELECT 1 FROM ot_sales o WHERE o.prid = s.product_id)
ORDER BY s.product_id;

-- 1B: OT ledger movements that landed in the NULL bucket (audit trail).
SELECT action, DATE(created_at) AS day, COUNT(*) AS entries, SUM(qty) AS total_qty
FROM stock_ledger
WHERE ref_type = 'ot_sale'
  AND action IN ('ot_reverse', 'ot_deduct')
  AND warehouse_id IS NULL
GROUP BY action, DATE(created_at)
ORDER BY day;

-- 1C: any OT-related stock row sitting anywhere other than LLP/G1 (expect none
--     besides what 1A shows).
SELECT s.product_id, s.user_id, s.warehouse_id, s.closing_qty
FROM stock s
WHERE s.user_type = 'company'
  AND EXISTS (SELECT 1 FROM ot_sales o WHERE o.prid = s.product_id)
  AND NOT (s.user_id = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
           AND s.warehouse_id = @g1_warehouse_id)
  AND s.warehouse_id IS NULL
  AND s.closing_qty <> 0;

-- ---------------------------------------------------------------------------
-- STEP 2: sweep (only if 1A returned rows)
-- ---------------------------------------------------------------------------
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_ot_stray;
CREATE TEMPORARY TABLE tmp_ot_stray AS
SELECT s.id AS stock_id, s.product_id, s.sales_qty, s.returnqty, s.closing_qty, s.extra_pieces
FROM stock s
WHERE s.user_type = 'company'
  AND s.user_id   = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
  AND s.warehouse_id IS NULL
  AND EXISTS (SELECT 1 FROM ot_sales o WHERE o.prid = s.product_id);

-- 2A: products that already have a G1 row -> add the stray quantities to it.
UPDATE stock g1
JOIN tmp_ot_stray t ON t.product_id = g1.product_id
SET g1.sales_qty    = g1.sales_qty    + t.sales_qty,
    g1.returnqty    = g1.returnqty    + t.returnqty,
    g1.closing_qty  = g1.closing_qty  + t.closing_qty,
    g1.extra_pieces = g1.extra_pieces + t.extra_pieces,
    g1.updated_at   = NOW()
WHERE g1.user_type = 'company'
  AND g1.user_id   = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
  AND g1.warehouse_id = @g1_warehouse_id;

-- 2B: delete the stray rows that were merged in 2A.
DELETE s FROM stock s
JOIN tmp_ot_stray t ON t.stock_id = s.id
WHERE EXISTS (
    SELECT 1 FROM (SELECT product_id FROM stock
                   WHERE user_type = 'company'
                     AND user_id = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
                     AND warehouse_id = @g1_warehouse_id) x
    WHERE x.product_id = t.product_id
);

-- 2C: products with no G1 row -> just re-point the stray row at G1.
UPDATE stock s
JOIN tmp_ot_stray t ON t.stock_id = s.id
SET s.warehouse_id = @g1_warehouse_id, s.updated_at = NOW();

-- ---------------------------------------------------------------------------
-- STEP 3: verify before committing
-- ---------------------------------------------------------------------------
-- Expect 0 rows:
SELECT s.product_id, s.closing_qty
FROM stock s
WHERE s.user_type = 'company'
  AND s.user_id = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
  AND s.warehouse_id IS NULL
  AND EXISTS (SELECT 1 FROM ot_sales o WHERE o.prid = s.product_id);

-- Expect one row per product, no negative closing_qty:
SELECT product_id, COUNT(*) AS rows_per_product, MIN(closing_qty) AS min_closing
FROM stock
WHERE user_type = 'company'
  AND user_id = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
  AND warehouse_id = @g1_warehouse_id
GROUP BY product_id
HAVING rows_per_product > 1 OR min_closing < 0;

-- If both checks are clean:
-- COMMIT;
-- Otherwise:
-- ROLLBACK;
