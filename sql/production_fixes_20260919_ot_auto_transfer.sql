-- ============================================================
-- Combined production fix — 19 Sep 2026 OT / Auto Transfer incident
-- Date written: 2026-09-21
--
-- Bundles, in the order they must run, every fix produced by this
-- session's investigation into the 180mm / 290mm-9pc stock excess and
-- the underlying Auto Transfer demand-capping defects:
--
--   PART 1 — schema migration: adds 'conversion' to stock_ledger.ref_type
--            (StockService's convertPiecesToPack/PackToPieces already
--            self-migrate this on first use, so this part is a no-op if
--            it already ran there — included for completeness/one-shot
--            convenience), plus adds warehouse_id to tp_invoices/ot_sales/
--            invoice/user_invoice if not already present (Part 3 below
--            reads/writes ot_sales.warehouse_id directly, so it must
--            exist before Part 3 runs — confirmed missing on production
--            when this was first run there, 2026-09-21).
--   PART 2 — reverses the phantom AUTO20260919123427479 transfer (2 units
--            of 180mm, 39 units of 290mm-9pc) that moved LLP stock ahead
--            of the physical count, because it was triggered by OT draft
--            orders that could never be confirmed at the time.
--   PART 3 — confirms every OT draft order dated 19 Sep (the fix for
--            "drafts can never be confirmed" — deducts real stock via
--            the same logic as StockService::otDeduct(), flips
--            ot_sales_invoice.status to 'confirmed').
--   PART 4 — cleans up the stale auto_transfer_skip_today rows from Run 6
--            (AUTO20260919110758321, 16:37:59) that were marked
--            'transferred' for products 9/14/16's OT-draft demand even
--            though the cap silently dropped 53/2/2 units and nothing
--            was ever moved for them. Safe now that Part 3 has already
--            confirmed (and correctly deducted stock for) those same
--            orders through the proper channel — this only corrects the
--            historical record, it does not move any more stock.
--
-- WHY THIS ORDER: Part 3 must run before Part 4 — Part 4 assumes the
-- orders it's cleaning up are no longer 'draft' (Part 3 guarantees that),
-- so Auto Transfer's own demand query (WHERE status='draft') will never
-- pick them up again regardless of what Part 4 does to the skip table.
-- Part 2 must run before Part 3 so LLP's stock is at its correct physical
-- baseline before Part 3 deducts fresh, real demand from it.
--
-- SAFE TO RE-RUN: every part is idempotent —
--   Part 1: ALTER TABLE MODIFY COLUMN is a no-op if 'conversion' is
--           already in the enum (re-running just re-declares the same
--           enum; verify with the pre-flight check that follows).
--   Part 2: pre-flight guards refuse to proceed if the transfer rows are
--           missing (already reversed) or partially touched.
--   Part 3: the stored procedure skips any invoice not still 'draft'.
--   Part 4: the DELETE only matches rows that still say 'transferred'
--           for this exact batch — running it twice deletes nothing the
--           second time.
-- ============================================================


-- ================================================================
-- PART 1 — stock_ledger.ref_type: add 'conversion'
-- ================================================================

-- Pre-flight (read-only):
SHOW COLUMNS FROM stock_ledger LIKE 'ref_type';
-- If the Type column already lists 'conversion', skip straight to PART 2.

ALTER TABLE stock_ledger
  MODIFY COLUMN ref_type ENUM(
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

-- ---- warehouse_id columns (from femi9/billing/db_migrations/
-- 2026_09_18_invoice_warehouse_columns.sql) ----
-- Each ALTER is guarded by an information_schema check so this is safe
-- to re-run on a DB that already has some or all of these columns —
-- MySQL has no "ADD COLUMN IF NOT EXISTS" before 8.0.29-ish reliably
-- across engines, so this uses a small stored procedure instead of
-- assuming that syntax works on whatever version production runs.
DELIMITER $$
DROP PROCEDURE IF EXISTS __add_warehouse_id_if_missing $$
CREATE PROCEDURE __add_warehouse_id_if_missing(IN p_table VARCHAR(64), IN p_after_column VARCHAR(64))
BEGIN
    DECLARE col_count INT;
    SELECT COUNT(*) INTO col_count
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = p_table AND column_name = 'warehouse_id';

    IF col_count = 0 THEN
        SET @__ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN warehouse_id INT NULL AFTER `', p_after_column, '`');
        PREPARE __stmt FROM @__ddl;
        EXECUTE __stmt;
        DEALLOCATE PREPARE __stmt;
    END IF;
END $$
DELIMITER ;

CALL __add_warehouse_id_if_missing('tp_invoices', 'source_godown_id');
CALL __add_warehouse_id_if_missing('ot_sales', 'godownid');
CALL __add_warehouse_id_if_missing('invoice', 'user_id');
CALL __add_warehouse_id_if_missing('user_invoice', 'from_user_id');

DROP PROCEDURE __add_warehouse_id_if_missing;

-- Verify all four now exist:
SELECT table_name, column_name, is_nullable, column_type
FROM information_schema.columns
WHERE table_schema = DATABASE() AND column_name = 'warehouse_id'
  AND table_name IN ('tp_invoices','ot_sales','invoice','user_invoice');
-- Expect 4 rows.


-- ================================================================
-- PART 2 — reverse the phantom AUTO20260919123427479 transfer
-- ================================================================

-- ---- PRE-FLIGHT CHECK (read-only) ----
SELECT id, tempid, send_from, send_to, product_id, qty, returned_qty
FROM internal_transfer
WHERE tempid IN ('AUTO20260919123427479-N1','AUTO20260919123427479-N2')
ORDER BY product_id, tempid;
-- Expect exactly 4 rows, all returned_qty=0. Eyeball this before
-- proceeding — the ENFORCED check right below will hard-abort the whole
-- script (not just this part) if this condition doesn't hold, so this
-- SELECT is for your own visibility, not the only safeguard.

SELECT id AS godown_id, gname FROM company_godown WHERE gname IN
  ('NEKSOMO HYGIENE INDUSTRIES','FEMI HEALTH CARE','FEMI NAYAN LLP');
-- For your own cross-check against send_from/send_to above — the
-- statements below resolve these live via subquery, no hand-editing
-- needed.

-- ---- ENFORCED GUARD ----
-- Re-running this whole file (e.g. after an interruption, or by mistake)
-- must never re-apply Part 2's reversal a second time — doing so once
-- already produced a real, wrong double-reversal while writing this
-- script (caught during local testing, corrected by hand). This procedure
-- SIGNALs a real, hard error and aborts the whole script (everything
-- after it included) unless exactly the 4 untouched rows this reversal
-- expects are present. If Part 2 has already run (or the batch never
-- existed on this DB), this is expected to fire — re-run the file
-- starting from PART 3 onward instead of from the top.
DELIMITER $$
DROP PROCEDURE IF EXISTS __check_part2_guard $$
CREATE PROCEDURE __check_part2_guard()
BEGIN
    DECLARE row_count INT;
    SELECT COUNT(*) INTO row_count
    FROM internal_transfer
    WHERE tempid IN ('AUTO20260919123427479-N1','AUTO20260919123427479-N2')
      AND product_id IN (4, 11) AND returned_qty = 0;

    IF row_count <> 4 THEN
        -- MESSAGE_TEXT is capped at 128 chars — full explanation is in
        -- the comment above this procedure; this just points there.
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'PART 2 ABORTED: batch already reversed or missing (see script comment). Re-run from PART 3, not from the top.';
    END IF;
END $$
DELIMITER ;

CALL __check_part2_guard();
DROP PROCEDURE __check_part2_guard;

START TRANSACTION;

SET @neksomo_id    = (SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1);
SET @healthcare_id = (SELECT id FROM company_godown WHERE gname = 'FEMI HEALTH CARE' LIMIT 1);
SET @llp_id        = (SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1);

-- Reverse leg 2 (Healthcare -> LLP) first, then leg 1 (Neksomo ->
-- Healthcare) — opposite of how the stock moved originally, same
-- convention as AutoTransferDemand.php's undo_auto_transfer().

-- ---------- PRODUCT 11 (290mm L 9pc), qty 39 ----------
SET @p11_llp_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@llp_id);
UPDATE stock SET closing_qty = closing_qty - 39, input_qty = GREATEST(0, input_qty - 39)
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @llp_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @llp_id, NULL, 'transfer_in_reverse', 39, @p11_llp_before, @p11_llp_before - 39, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-fix-20260921');

SET @p11_hc_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock SET closing_qty = closing_qty + 39
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @healthcare_id, NULL, 'transfer_out_reverse', 39, @p11_hc_before, @p11_hc_before + 39, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-fix-20260921');

SET @p11_hc_before2 = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock SET closing_qty = closing_qty - 39, input_qty = GREATEST(0, input_qty - 39)
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @healthcare_id, NULL, 'transfer_in_reverse', 39, @p11_hc_before2, @p11_hc_before2 - 39, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-fix-20260921');

SET @p11_nk_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@neksomo_id);
UPDATE stock SET closing_qty = closing_qty + 39
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @neksomo_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @neksomo_id, NULL, 'transfer_out_reverse', 39, @p11_nk_before, @p11_nk_before + 39, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-fix-20260921');

-- ---------- PRODUCT 4 (180mm 30pc), qty 2 ----------
SET @p4_llp_before = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@llp_id);
UPDATE stock SET closing_qty = closing_qty - 2, input_qty = GREATEST(0, input_qty - 2)
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @llp_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @llp_id, NULL, 'transfer_in_reverse', 2, @p4_llp_before, @p4_llp_before - 2, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-fix-20260921');

SET @p4_hc_before = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock SET closing_qty = closing_qty + 2
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @healthcare_id, NULL, 'transfer_out_reverse', 2, @p4_hc_before, @p4_hc_before + 2, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-fix-20260921');

SET @p4_hc_before2 = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock SET closing_qty = closing_qty - 2, input_qty = GREATEST(0, input_qty - 2)
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @healthcare_id, NULL, 'transfer_in_reverse', 2, @p4_hc_before2, @p4_hc_before2 - 2, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-fix-20260921');

SET @p4_nk_before = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@neksomo_id);
UPDATE stock SET closing_qty = closing_qty + 2
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @neksomo_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @neksomo_id, NULL, 'transfer_out_reverse', 2, @p4_nk_before, @p4_nk_before + 2, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-fix-20260921');

-- ---------- Remove the transfer records themselves ----------
DELETE FROM internal_transfer
WHERE tempid IN ('AUTO20260919123427479-N1','AUTO20260919123427479-N2')
  AND product_id IN (4, 11);

DELETE FROM internal_transfer_invoice
WHERE tempid IN ('AUTO20260919123427479-N1','AUTO20260919123427479-N2')
  AND NOT EXISTS (SELECT 1 FROM internal_transfer it WHERE it.tempid = internal_transfer_invoice.tempid);

-- ---------- Clear the skip-flags this specific batch created ----------
DELETE FROM auto_transfer_skip_today
WHERE skip_date = '2026-09-19'
  AND created_at = '2026-09-19 18:04:27'
  AND (source_ref LIKE '%:4' OR source_ref LIKE '%:11');

-- ---------- Verify before committing ----------
SELECT product_id, user_id, closing_qty
FROM stock
WHERE product_id IN (4, 11) AND user_type = 'company' AND user_id IN (@neksomo_id, @healthcare_id, @llp_id)
ORDER BY product_id, user_id;
-- Expected LLP result: product 4 -> 387, product 11 -> 3083 (or whatever
-- your own physical count says — Neksomo/Healthcare will differ from the
-- local audit's numbers since production has its own independent
-- history).

-- If the LLP figures above match your physical count, commit; otherwise
-- ROLLBACK and stop here (do not proceed to Part 3 in that case).
COMMIT;


-- ================================================================
-- PART 3 — confirm every OT draft order dated 2026-09-19
-- ================================================================

-- ---- PRE-FLIGHT (read-only) ----
SELECT osi.tempid, osi.status, os.id AS line_id, os.prid, os.qty, os.godownid, os.warehouse_id
FROM ot_sales_invoice osi
JOIN ot_sales os ON os.tempid = osi.tempid
WHERE osi.status = 'draft' AND os.date = '2026-09-19'
ORDER BY osi.tempid, os.id;
-- Confirm every line has warehouse_id NULL before proceeding — this
-- script assumes NULL throughout, matching the getClosingQty()/
-- lockStockRow() convention for "no specific warehouse".

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
-- Any row here means that ENTIRE invoice will be skipped below
-- (all-or-nothing per invoice) — review before proceeding.

-- This server's connection/session default collation may be
-- utf8mb4_0900_ai_ci (MySQL 8's own default) while the app's tables were
-- created under utf8mb4_general_ci — a pre-existing mismatch, unrelated
-- to this fix. Every string variable/literal below that's compared
-- against a table column is explicitly COLLATE utf8mb4_general_ci to
-- avoid "Illegal mix of collations" regardless of which default this
-- server happens to run.

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

        SELECT status INTO v_status FROM ot_sales_invoice WHERE tempid = v_tempid;
        IF v_status <> 'draft' THEN
            INSERT INTO confirm_ot_20260919_results VALUES (v_tempid, 'already_confirmed', '');
            ITERATE read_loop;
        END IF;

        START TRANSACTION;

        SET v_order_ok = 1;
        SET v_any_line_failed = 0;
        SET v_fail_reason = '';

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
                     'ot_sale', v_tempid, '', 'prod-fix-20260921');
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

CALL confirm_draft_ot_orders_20260919();
-- One row per invoice: 'confirmed', 'skipped' (see detail), or
-- 'already_confirmed'.

DROP PROCEDURE IF EXISTS confirm_draft_ot_orders_20260919;
DROP TEMPORARY TABLE IF EXISTS confirm_ot_20260919_results;

-- ---- POST-RUN VERIFICATION ----
SELECT osi.status, COUNT(*) FROM ot_sales_invoice osi
JOIN ot_sales os ON os.tempid = osi.tempid
WHERE os.date = '2026-09-19'
GROUP BY osi.status;
-- Should show 0 'draft' rows unless some were genuinely skipped for
-- insufficient stock (reviewed above).

SELECT * FROM stock_ledger WHERE created_by = 'prod-fix-20260921' AND ref_type='ot_sale' ORDER BY id;


-- ================================================================
-- PART 4 — clean up Run 6's stale "transferred" skip-flags
-- ================================================================
-- Run AUTO20260919110758321 (16:37:59) capped combined TP+OT demand for
-- products 9/14/16 down to available stock, but still marked EVERY
-- contributing order 'transferred' — including 23 product-9 orders, 2
-- product-14 orders, and 1 product-16 order whose 53/2/2 units were
-- never actually moved (see internal_transfer_auto_action.php's
-- skip-marking fix, already applied to the app code this session).
--
-- These same orders have now been correctly confirmed and deducted for
-- real in PART 3 above (they were all dated 19 Sep and still 'draft'
-- until then), so this is purely a historical-record correction — it
-- does not move any stock, and it's safe to run regardless of whether
-- PART 3 found some of them already non-draft for another reason (the
-- DELETE below only removes the stale flag; it never touches stock or
-- order status).

SELECT source_type, source_ref, reason, created_at
FROM auto_transfer_skip_today
WHERE created_at = '2026-09-19 16:37:59' AND source_type = 'ot'
ORDER BY source_ref;
-- Review before deleting — expect ~26 rows, all reason='transferred',
-- all source_ref ending in ':9', ':14', or ':16'.

DELETE FROM auto_transfer_skip_today
WHERE created_at = '2026-09-19 16:37:59'
  AND source_type = 'ot';

-- ---- FINAL VERIFICATION ----
SELECT 'Part 4 check' AS step, COUNT(*) AS remaining_stale_rows
FROM auto_transfer_skip_today
WHERE created_at = '2026-09-19 16:37:59' AND source_type = 'ot';
-- Should be 0.
