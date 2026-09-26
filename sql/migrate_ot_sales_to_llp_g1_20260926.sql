-- ============================================================================
-- Migrate all existing OT-channel (ot_sales) invoices to FEMI NAYAN LLP / G1
-- ============================================================================
-- Scope confirmed with user:
--   1. Rewrite historical ot_sales.godownid + ot_sales.warehouse_id to
--      FEMI NAYAN LLP / G1, so old records report against the new entity/warehouse.
--   2. Move the corresponding stock balances into the (company, LLP-godown, G1)
--      bucket, so current closing_qty lives under G1 going forward.
--
-- IMPORTANT — READ BEFORE RUNNING:
--   * This is a real data migration on `stock` (per-entity closing balances)
--     and `ot_sales` (historical transaction rows). Take a fresh mysqldump of
--     at least `stock`, `ot_sales`, `stock_ledger` before running anything below.
--   * Run the STEP 0 sanity-check SELECTs first and eyeball the row counts /
--     ids. Do not run STEP 2+ until STEP 0's resolved ids look right.
--   * This script assumes stock is keyed by (product_id, user_type='company',
--     user_id = <godownid as string>, warehouse_id) per StockService's
--     lockStockRow()/updateStockSnapshot() contract (see
--     femi9/billing/company/include/StockService.php).
--   * A product can have OT stock spread across multiple old (godown,
--     warehouse) rows, and/or an existing row may already sit at (LLP, G1).
--     STEP 1 handles this by aggregating ALL such rows per product (old
--     source rows + any pre-existing G1 row) into a temp table, deleting
--     them, then inserting exactly one merged row per product back at
--     (LLP, G1) — this avoids the uq_stock_entity_warehouse unique-key
--     collision a naive row-by-row UPDATE hits when two source rows for the
--     same product both resolve to the same (LLP, G1) target.
--   * `stock.user_id` is declared utf8mb4_general_ci while this connection's
--     default (and ot_sales/int-derived expressions) collate as
--     utf8mb4_0900_ai_ci, so every string comparison/assignment against
--     stock.user_id below explicitly appends `COLLATE utf8mb4_general_ci` to
--     avoid "Illegal mix of collations".
--   * JUDGMENT CALL, please confirm: when multiple stock rows merge into one
--     (LLP, G1) row, `sales_qty`/`input_qty`/`sent_qty`/`returnqty`/
--     `closing_qty`/`extra_pieces` are SUMmed (additive, matches how these
--     accumulate) and `opening_date` takes the earliest (MIN) of the merged
--     rows. `opening_qty` is also SUMmed, which is standard for a rollup but
--     is worth a second look if any of these old rows' "opening_qty" was
--     meant as a point-in-time balance rather than an additive lifetime
--     figure. Check STEP 1-TEMP's preview output (rows_merged > 1) against
--     the underlying source rows before committing if in doubt.
-- ============================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- STEP 0: Resolve the target ids. Run this first, on its own, and confirm
-- @llp_godown_id and @g1_warehouse_id are both non-NULL before going further.
-- ---------------------------------------------------------------------------
SET @llp_godown_id = (
    SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1
);
SET @g1_warehouse_id = (
    SELECT id FROM warehouses WHERE code = 'G1' LIMIT 1
);

SELECT @llp_godown_id AS llp_godown_id, @g1_warehouse_id AS g1_warehouse_id;
-- ^ If either is NULL, STOP — the gname/code doesn't match what's in this
--   database. Fix the lookup above before continuing.

-- ---------------------------------------------------------------------------
-- STEP 0B (informational): what you're about to move.
-- ---------------------------------------------------------------------------
-- How many ot_sales rows are NOT already on LLP/G1:
SELECT COUNT(*) AS ot_sales_rows_to_update
FROM ot_sales
WHERE godownid <> @llp_godown_id
   OR warehouse_id IS NULL
   OR warehouse_id <> @g1_warehouse_id;

-- Distinct (product_id, old godownid, old warehouse_id) combos currently
-- holding OT stock, i.e. what stock rows will need to move:
SELECT DISTINCT prid, godownid, warehouse_id
FROM ot_sales
WHERE godownid <> @llp_godown_id
   OR warehouse_id IS NULL
   OR warehouse_id <> @g1_warehouse_id;

-- ---------------------------------------------------------------------------
-- STEP 1: Move `stock` balances for every product that has OT-sale stock
-- under its old (godown, warehouse) into the (LLP, G1) bucket.
--
-- NOTE: a single product can have OT stock spread across MULTIPLE old
-- (godown, warehouse) combos, and/or a (LLP, G1) row may already exist for
-- it. A naive row-by-row UPDATE...JOIN can't detect a second source row
-- colliding with a target it just created earlier in the same statement, so
-- this is done as an explicit aggregate-then-merge, driven by one row per
-- affected product (STEP 1-TEMP), not by ot_sales rows directly.
-- ---------------------------------------------------------------------------

-- STEP 1-TEMP: build one row per affected product, aggregating qty across
-- every old (godown, warehouse) source row AND any pre-existing (LLP, G1)
-- target row for that product, so nothing is double counted or dropped.
DROP TEMPORARY TABLE IF EXISTS tmp_ot_stock_migration;
CREATE TEMPORARY TABLE tmp_ot_stock_migration AS
SELECT
    s.product_id,
    SUM(s.opening_qty)  AS sum_opening_qty,
    SUM(s.input_qty)    AS sum_input_qty,
    SUM(s.sales_qty)    AS sum_sales_qty,
    SUM(s.sent_qty)     AS sum_sent_qty,
    SUM(s.returnqty)    AS sum_returnqty,
    SUM(s.closing_qty)  AS sum_closing_qty,
    SUM(s.extra_pieces) AS sum_extra_pieces,
    MIN(s.opening_date) AS min_opening_date,
    COUNT(*)            AS rows_merged
FROM stock s
WHERE s.user_type = 'company'
  AND (
        -- rows sitting at an old OT (godown, warehouse) combo
        EXISTS (
            SELECT 1 FROM ot_sales o
            WHERE o.prid = s.product_id
              AND o.godownid = CAST(s.user_id AS UNSIGNED)
              AND ( (o.warehouse_id IS NULL AND s.warehouse_id IS NULL)
                    OR o.warehouse_id = s.warehouse_id )
              AND ( o.godownid <> @llp_godown_id
                    OR o.warehouse_id IS NULL
                    OR o.warehouse_id <> @g1_warehouse_id )
        )
        -- OR the row already sitting at the (LLP, G1) target for a product
        -- that has at least one old-combo source row above
        OR (
            s.user_id = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
            AND s.warehouse_id = @g1_warehouse_id
            AND EXISTS (
                SELECT 1 FROM ot_sales o2
                WHERE o2.prid = s.product_id
                  AND ( o2.godownid <> @llp_godown_id
                        OR o2.warehouse_id IS NULL
                        OR o2.warehouse_id <> @g1_warehouse_id )
            )
        )
      )
GROUP BY s.product_id;

-- Review the merge plan before continuing — rows_merged > 1 means multiple
-- stock rows (old combos and/or an existing G1 row) are being combined for
-- that product; eyeball that the summed quantities look sane.
SELECT * FROM tmp_ot_stock_migration ORDER BY rows_merged DESC, product_id;

-- STEP 1A: delete every OT-related source/target stock row being merged...
DELETE s FROM stock s
JOIN tmp_ot_stock_migration tmp ON tmp.product_id = s.product_id
WHERE s.user_type = 'company'
  AND (
        EXISTS (
            SELECT 1 FROM ot_sales o
            WHERE o.prid = s.product_id
              AND o.godownid = CAST(s.user_id AS UNSIGNED)
              AND ( (o.warehouse_id IS NULL AND s.warehouse_id IS NULL)
                    OR o.warehouse_id = s.warehouse_id )
              AND ( o.godownid <> @llp_godown_id
                    OR o.warehouse_id IS NULL
                    OR o.warehouse_id <> @g1_warehouse_id )
        )
        OR (
            s.user_id = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
            AND s.warehouse_id = @g1_warehouse_id
        )
      );

-- STEP 1B: ...then re-insert exactly one merged row per product at (LLP, G1).
INSERT INTO stock
    (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty,
     returnqty, closing_qty, extra_pieces, user_type, user_id, warehouse_id, updated_at)
SELECT
    tmp.product_id,
    tmp.sum_opening_qty,
    tmp.min_opening_date,
    tmp.sum_input_qty,
    tmp.sum_sales_qty,
    tmp.sum_sent_qty,
    tmp.sum_returnqty,
    tmp.sum_closing_qty,
    tmp.sum_extra_pieces,
    'company',
    CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci,
    @g1_warehouse_id,
    NOW()
FROM tmp_ot_stock_migration tmp;

-- Confirm exactly one row per affected product now sits at (LLP, G1), with
-- closing_qty matching the sum shown in the STEP 1-TEMP review above.
SELECT product_id, user_id, warehouse_id, closing_qty
FROM stock
WHERE user_type = 'company'
  AND user_id = CAST(@llp_godown_id AS CHAR) COLLATE utf8mb4_general_ci
  AND warehouse_id = @g1_warehouse_id
ORDER BY product_id;

-- ---------------------------------------------------------------------------
-- STEP 2: Rewrite the historical ot_sales rows themselves.
-- ---------------------------------------------------------------------------
UPDATE ot_sales
SET godownid    = @llp_godown_id,
    warehouse_id = @g1_warehouse_id
WHERE godownid <> @llp_godown_id
   OR warehouse_id IS NULL
   OR warehouse_id <> @g1_warehouse_id;

-- ---------------------------------------------------------------------------
-- STEP 3: Sanity check before committing.
-- ---------------------------------------------------------------------------
SELECT COUNT(*) AS remaining_non_llp_g1_ot_sales
FROM ot_sales
WHERE godownid <> @llp_godown_id
   OR warehouse_id IS NULL
   OR warehouse_id <> @g1_warehouse_id;
-- Expect 0.

-- Review everything above. If it looks correct:
-- COMMIT;
-- Otherwise:
-- ROLLBACK;
