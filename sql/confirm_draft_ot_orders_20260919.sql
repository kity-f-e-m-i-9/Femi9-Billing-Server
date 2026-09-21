-- ============================================================
-- Confirm every draft OT-channel order dated 2026-09-19
-- Date written: 2026-09-21
--
-- Purpose: ot_sales_invoice.status has no UI path to go from 'draft' to
-- 'confirmed' (see ot-sale-confirm-action.php, added this session — the
-- only place in the app that now does this). This script is the
-- production-side equivalent of clicking "Confirm" on every still-draft
-- OT order from 19 Sep, run as SQL because there may be more of them on
-- production than can practically be confirmed one at a time through the
-- UI, and because this needs to be auditable as a single batch.
--
-- For EACH draft order dated 2026-09-19, and EACH product line under it,
-- this exactly replicates StockService::otDeduct():
--   1. Lock the seller's stock row (product_id, user_type='company',
--      user_id=godownid, warehouse_id IS NULL — every 19-Sep OT line on
--      this DB has warehouse_id NULL; the pre-flight check below confirms
--      that still holds on production before anything runs).
--   2. Refuse (skip that whole order, roll back its lines) if the
--      resulting closing_qty would go negative -- never silently floors
--      to 0, matching StockService's own guard.
--   3. On success: increments sales_qty, decrements closing_qty, writes
--      one 'ot_deduct' stock_ledger row per line with correct
--      qty_before/qty_after read live at the moment of the write (never
--      a precomputed/stale number), ref_type='ot_sale', ref_id=tempid.
--   4. Sets ot_sales_invoice.status='confirmed' for that tempid, only
--      after every one of its lines deducted successfully.
--
-- Idempotent: an invoice already 'confirmed' is skipped entirely (no
-- lines re-deducted), so this can be safely re-run if it's interrupted
-- partway, or run again after fixing a reported shortfall.
--
-- Does NOT touch: internal_transfer / internal_transfer_invoice (this is
-- an OT sale confirmation, not an internal godown transfer -- no G/S
-- invoice numbers are generated here), Auto Transfer's
-- auto_transfer_skip_today (confirming via this path is a different
-- action from Auto Transfer's own skip-marking; a confirmed order simply
-- stops matching get_auto_transfer_requirements()'s
-- "WHERE status = 'draft'" clause on its own, no flag needed).
-- ============================================================


-- ---- PRE-FLIGHT (read-only) ----
-- Run this block first. Confirm every line below has warehouse_id NULL
-- and godownid pointing at a real company_godown row (should be LLP,
-- id=1 on this DB, but re-check on production rather than assume).
SELECT osi.tempid, osi.status, os.id AS line_id, os.prid, os.qty, os.godownid, os.warehouse_id
FROM ot_sales_invoice osi
JOIN ot_sales os ON os.tempid = osi.tempid
WHERE osi.status = 'draft' AND os.date = '2026-09-19'
ORDER BY osi.tempid, os.id;

-- If any row above has warehouse_id NOT NULL, STOP — this script assumes
-- NULL throughout (matching the getClosingQty()/lockStockRow() convention
-- for "no specific warehouse"); a non-NULL row needs its own handling and
-- isn't covered here.


-- ---- Pre-check: any line whose stock is already insufficient? ----
-- Read-only preview of what WILL be skipped, before committing to the
-- run — same insufficient-stock guard the procedure enforces below, just
-- surfaced up front so you're not surprised by the summary at the end.
SELECT os.tempid, os.prid, os.qty AS requested, s.closing_qty AS available,
       (s.closing_qty - os.qty) AS would_be_after
FROM ot_sales_invoice osi
JOIN ot_sales os ON os.tempid = osi.tempid
LEFT JOIN stock s
       ON s.product_id = os.prid AND s.user_type = 'company'
      AND s.user_id = os.godownid AND s.warehouse_id IS NULL
WHERE osi.status = 'draft' AND os.date = '2026-09-19'
  AND (s.closing_qty IS NULL OR s.closing_qty < os.qty)
ORDER BY os.tempid, os.prid;
-- Any row here means that ENTIRE invoice will be skipped (all-or-nothing
-- per invoice, see the procedure below) — review before proceeding.


-- ============================================================
-- THE PROCEDURE — mirrors StockService::otDeduct() line for line
-- ============================================================
-- This server's connection/session default collation is utf8mb4_0900_ai_ci
-- (MySQL 8's own default), while the app's tables were created under the
-- older utf8mb4_general_ci — a pre-existing mismatch, unrelated to this
-- script. Comparing a plain string literal against one of these columns
-- throws "Illegal mix of collations", so every literal compared against
-- user_type/tempid/ref_type/etc. below is explicitly cast with
-- COLLATE utf8mb4_general_ci to match the column it's compared to.

DELIMITER $$

DROP PROCEDURE IF EXISTS confirm_draft_ot_orders_20260919 $$
CREATE PROCEDURE confirm_draft_ot_orders_20260919()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_tempid VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

    DECLARE v_line_id INT;
    DECLARE v_prid INT;
    DECLARE v_qty INT;
    DECLARE v_godownid INT;
    DECLARE v_before INT;
    DECLARE v_after INT;
    DECLARE v_order_ok INT;
    DECLARE v_any_line_failed INT;
    DECLARE v_fail_reason VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    DECLARE v_status VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

    -- One cursor per invoice tempid still in draft, dated 19 Sep.
    DECLARE tempid_cur CURSOR FOR
        SELECT DISTINCT osi.tempid
        FROM ot_sales_invoice osi
        JOIN ot_sales os ON os.tempid = osi.tempid
        WHERE osi.status = 'draft' AND os.date = '2026-09-19'
        ORDER BY osi.tempid;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DROP TEMPORARY TABLE IF EXISTS confirm_ot_20260919_results;
    CREATE TEMPORARY TABLE confirm_ot_20260919_results (
        tempid VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
        outcome VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
        detail VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
    );

    OPEN tempid_cur;

    read_loop: LOOP
        FETCH tempid_cur INTO v_tempid;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Idempotency: skip (not an error) if somehow already confirmed
        -- between the cursor's SELECT and now.
        SELECT status INTO v_status FROM ot_sales_invoice WHERE tempid = v_tempid;
        IF v_status <> 'draft' THEN
            INSERT INTO confirm_ot_20260919_results VALUES (v_tempid, 'already_confirmed', '');
            ITERATE read_loop;
        END IF;

        START TRANSACTION;

        SET v_order_ok = 1;
        SET v_any_line_failed = 0;
        SET v_fail_reason = '';

        -- Walk this invoice's lines one at a time (a plain, bounded loop
        -- over a small per-tempid line count — never more than a handful
        -- per real OT order — via a nested cursor).
        BEGIN
            DECLARE line_done INT DEFAULT 0;
            DECLARE line_cur CURSOR FOR
                SELECT id, prid, qty, godownid FROM ot_sales WHERE tempid = v_tempid;
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET line_done = 1;

            OPEN line_cur;
            line_loop: LOOP
                FETCH line_cur INTO v_line_id, v_prid, v_qty, v_godownid;
                IF line_done THEN
                    LEAVE line_loop;
                END IF;
                IF v_qty <= 0 THEN
                    ITERATE line_loop;
                END IF;

                -- Lock the stock row exactly as lockStockRow() does.
                SELECT closing_qty INTO v_before
                FROM stock
                WHERE product_id = v_prid AND user_type = 'company'
                  AND user_id = v_godownid AND warehouse_id IS NULL
                FOR UPDATE;

                IF v_before IS NULL THEN
                    SET v_order_ok = 0;
                    SET v_any_line_failed = 1;
                    SET v_fail_reason = CONCAT('No stock row for product=', v_prid, ' godown=', v_godownid);
                    LEAVE line_loop;
                END IF;

                SET v_after = v_before - v_qty;
                IF v_after < 0 THEN
                    SET v_order_ok = 0;
                    SET v_any_line_failed = 1;
                    SET v_fail_reason = CONCAT('Insufficient stock for product=', v_prid,
                                               ' available=', v_before, ' requested=', v_qty);
                    LEAVE line_loop;
                END IF;

                UPDATE stock
                   SET sales_qty   = sales_qty + v_qty,
                       closing_qty = v_after
                 WHERE product_id = v_prid AND user_type = 'company'
                   AND user_id = v_godownid AND warehouse_id IS NULL;

                INSERT INTO stock_ledger
                    (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after,
                     ref_type, ref_id, note, created_by)
                VALUES
                    (v_prid, 'company', v_godownid, NULL, 'ot_deduct', v_qty, v_before, v_after,
                     'ot_sale', v_tempid, '', 'confirm-draft-ot-20260919-batch');
            END LOOP;
            CLOSE line_cur;
        END;

        IF v_order_ok = 1 THEN
            UPDATE ot_sales_invoice SET status = 'confirmed' WHERE tempid = v_tempid;
            COMMIT;
            INSERT INTO confirm_ot_20260919_results VALUES (v_tempid, 'confirmed', '');
        ELSE
            ROLLBACK;
            INSERT INTO confirm_ot_20260919_results VALUES (v_tempid, 'skipped', v_fail_reason);
        END IF;

    END LOOP;

    CLOSE tempid_cur;

    SELECT * FROM confirm_ot_20260919_results ORDER BY tempid;
END $$

DELIMITER ;

-- ---- RUN IT ----
CALL confirm_draft_ot_orders_20260919();
-- Prints one row per invoice: 'confirmed' (stock deducted, status
-- flipped), 'skipped' (insufficient stock or missing stock row — nothing
-- touched for that invoice, see `detail`), or 'already_confirmed'
-- (no-op, safe re-run).

-- ---- Cleanup (safe to run any time after reviewing the results) ----
DROP PROCEDURE IF EXISTS confirm_draft_ot_orders_20260919;
DROP TEMPORARY TABLE IF EXISTS confirm_ot_20260919_results;


-- ---- POST-RUN VERIFICATION ----
-- Re-run to confirm nothing dated 19 Sep is still draft except genuinely
-- skipped (insufficient-stock) ones you've reviewed above.
SELECT osi.tempid, osi.status
FROM ot_sales_invoice osi
JOIN ot_sales os ON os.tempid = osi.tempid
WHERE os.date = '2026-09-19'
GROUP BY osi.tempid, osi.status
ORDER BY osi.status, osi.tempid;

-- Spot-check the ledger entries this batch created:
SELECT * FROM stock_ledger WHERE created_by = 'confirm-draft-ot-20260919-batch' ORDER BY id;
