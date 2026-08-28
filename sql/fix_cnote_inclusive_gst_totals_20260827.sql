-- ============================================================================
-- Backfill: correct inflated credit-note / stock-return totals for
-- inclusive-GST products (e.g. Lumi9 diapers).
-- ============================================================================
-- Background
-- ----------
-- cnote_action.php (the "Add Return Item" handler behind cnote_new.php, reached
-- from the finance / neksomo / stockist / super-stockist / super_distributor /
-- distributor / channel-partner / c-and-f logins) fetched only products.gst and
-- ALWAYS added GST on top of the returned line amount:
--
--     subtotal        = invoice_items.amount * returnqty
--     gstamount_total  = subtotal * gst%          -- <-- wrong for 'inclusive'
--     total            = subtotal + gstamount_total
--
-- For a product flagged products.gst_type = 'inclusive', the invoiced rate
-- (invoice_items.amount / user_invoice_items.amount) already has GST baked in,
-- so the tax must be CARVED OUT of subtotal, not added again:
--
--     total            = subtotal
--     gstamount_total  = subtotal - subtotal * 100 / (100 + gst%)
--
-- The code is now fixed (branches on gst_type, same convention as
-- user-invoice-action.php / internal_transfer_action.php). This script corrects
-- the rows written before the fix.
--
-- Blast radius of the bug:
--   * user_return_stock_items  -- line rows: subtotal ok, gstamount_total and
--                                 total inflated for inclusive products.
--   * user_return_stock        -- header subtotal/total is SUM(items.total)
--                                 - discount (see cnote_finish.php /
--                                 stock_return_finish.php), so it inherited the
--                                 inflation for FINISHED returns. Draft returns
--                                 still hold 0/0/0 and self-correct on finish.
--   * advance_payments         -- for super-stockist / stockist returns,
--                                 cnote_action.php's final-submit path books a
--                                 'credit_note' advance credit for
--                                 SUM(user_return_stock_items.total), keyed
--                                 reference_number = CONCAT('CN-', returnid).
--                                 That credit was over-stated by the same gap.
--
-- Selection is by BUG SIGNATURE, not by date:
--   - products.gst_type = 'inclusive' AND products.gst > 0
--   - ABS(item.total - item.subtotal) > 0.5      (GST added on top instead of
--     carved out -- the exact fingerprint; leaves correct exclusive rows and
--     already-fixed rows untouched, so re-running is safe)
--
-- Requires MySQL 8.0+. Run on a backup / off-peak. Wrapped in a transaction:
-- inspect the PREVIEW SELECTs, then COMMIT (or ROLLBACK). Backup tables of
-- every touched row are created first and left in place for manual rollback.
-- ============================================================================

START TRANSACTION;

-- ############################################################################
-- PART A -- return line items  (user_return_stock_items)
-- ############################################################################

-- A0) Backup -----------------------------------------------------------------
DROP TABLE IF EXISTS backup_20260827_urs_items_gst_fix;
CREATE TABLE backup_20260827_urs_items_gst_fix AS
SELECT ursi.*
FROM user_return_stock_items ursi
JOIN products p ON p.id = ursi.prid
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(ursi.total - ursi.subtotal) > 0.5;

-- A1) Recompute affected line items ----------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_ursi_fix;
CREATE TEMPORARY TABLE tmp_ursi_fix AS
SELECT
    ursi.id,
    ursi.returnid,
    ursi.subtotal        AS old_subtotal,
    ursi.gstamount_total AS old_gstamount_total,
    ursi.total           AS old_total,
    ursi.subtotal        AS new_subtotal,                                        -- subtotal unaffected
    ROUND(ursi.subtotal - (ursi.subtotal * 100 / (100 + p.gst)), 2) AS new_gstamount_total,
    ursi.subtotal        AS new_total                                            -- total collapses to subtotal
FROM user_return_stock_items ursi
JOIN products p ON p.id = ursi.prid
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(ursi.total - ursi.subtotal) > 0.5;

CREATE INDEX idx_tmp_ursi_fix     ON tmp_ursi_fix (id);
CREATE INDEX idx_tmp_ursi_fix_rid ON tmp_ursi_fix (returnid);

-- A2) PREVIEW -------------------------------------------------------------
SELECT 'PART A: return line items' AS section,
       COUNT(*)                    AS line_items_to_fix,
       COUNT(DISTINCT returnid)    AS returns_affected,
       ROUND(SUM(old_total - new_total), 2) AS total_overstatement_removed
FROM tmp_ursi_fix;
-- SELECT * FROM tmp_ursi_fix ORDER BY returnid;   -- uncomment for detail

-- A3) APPLY -- line items ------------------------------------------------
UPDATE user_return_stock_items ursi
JOIN tmp_ursi_fix f ON f.id = ursi.id
SET ursi.subtotal        = f.new_subtotal,
    ursi.gstamount_total = f.new_gstamount_total,
    ursi.total           = f.new_total;


-- ############################################################################
-- PART B -- return headers  (user_return_stock)
--          Re-derive subtotal/total from the corrected line items, matching
--          cnote_finish.php: subtotal = SUM(items.total), total = subtotal
--          - discount. Only touch headers that were already finalised
--          (status NOT IN draft/pending) and currently carry a non-zero total
--          -- a still-pending return holds 0/0/0 and recomputes itself when
--          it is finished, off the now-corrected items.
-- ############################################################################

-- B0) Backup ---------------------------------------------------------------
DROP TABLE IF EXISTS backup_20260827_urs_header_gst_fix;
CREATE TABLE backup_20260827_urs_header_gst_fix AS
SELECT urs.*
FROM user_return_stock urs
WHERE urs.returnid IN (SELECT DISTINCT returnid FROM tmp_ursi_fix)
  AND urs.total <> 0;

-- B1) Recompute headers --------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_urs_fix;
CREATE TEMPORARY TABLE tmp_urs_fix AS
SELECT
    urs.returnid,
    urs.subtotal AS old_subtotal,
    urs.total    AS old_total,
    ROUND(SUM(ursi.total), 2)                              AS new_subtotal,
    ROUND(SUM(ursi.total) - COALESCE(urs.discount, 0), 2)  AS new_total
FROM user_return_stock urs
JOIN user_return_stock_items ursi ON ursi.returnid = urs.returnid
WHERE urs.returnid IN (SELECT DISTINCT returnid FROM tmp_ursi_fix)
  AND urs.total <> 0
GROUP BY urs.returnid, urs.subtotal, urs.total, urs.discount;

CREATE INDEX idx_tmp_urs_fix ON tmp_urs_fix (returnid);

-- PREVIEW -- header changes:
SELECT * FROM tmp_urs_fix ORDER BY returnid;

-- B2) APPLY -- headers -------------------------------------------------
UPDATE user_return_stock urs
JOIN tmp_urs_fix f ON f.returnid = urs.returnid
SET urs.subtotal = f.new_subtotal,
    urs.total     = f.new_total;


-- ############################################################################
-- PART C -- advance-payment credits booked from these returns
--          (advance_payments, payment_mode = 'credit_note',
--           reference_number = CONCAT('CN-', returnid))
--
--  C-1  UNTOUCHED credits (balance_amount = amount, nothing adjusted yet):
--       safe to reduce amount + balance_amount by the exact overstatement.
--  C-2  PARTIALLY CONSUMED credits (balance_amount <> amount): NOT auto-fixed
--       -- lowering `amount` could push it below what has already been
--       adjusted/consumed. Listed for manual review instead.
-- ############################################################################

-- C0) Per-return overstatement -----------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_cn_delta;
CREATE TEMPORARY TABLE tmp_cn_delta AS
SELECT returnid,
       ROUND(SUM(old_total - new_total), 2) AS overstated_by
FROM tmp_ursi_fix
GROUP BY returnid
HAVING overstated_by > 0.5;
CREATE INDEX idx_tmp_cn_delta ON tmp_cn_delta (returnid);

-- C1) Backup every matching advance_payments row ---------------------
DROP TABLE IF EXISTS backup_20260827_advance_payments_cn_fix;
CREATE TABLE backup_20260827_advance_payments_cn_fix AS
SELECT ap.*
FROM advance_payments ap
JOIN tmp_cn_delta d ON ap.reference_number = CONCAT('CN-', d.returnid)
WHERE ap.payment_mode = 'credit_note';

-- C2) PREVIEW -- what will change vs. what needs manual review ------
SELECT
    CASE WHEN ABS(ap.balance_amount - ap.amount) < 0.01
         THEN 'AUTO-FIX (untouched credit)'
         ELSE 'MANUAL REVIEW (partly consumed)' END          AS disposition,
    ap.id,
    ap.reference_number,
    ap.from_user_type, ap.from_user_id,
    ap.amount            AS old_amount,
    ap.balance_amount    AS old_balance_amount,
    d.overstated_by,
    ROUND(ap.amount - d.overstated_by, 2)         AS new_amount,
    ROUND(ap.balance_amount - d.overstated_by, 2) AS new_balance_amount
FROM advance_payments ap
JOIN tmp_cn_delta d ON ap.reference_number = CONCAT('CN-', d.returnid)
WHERE ap.payment_mode = 'credit_note'
ORDER BY disposition, ap.id;

-- C3) APPLY -- only the untouched credits ---------------------------
UPDATE advance_payments ap
JOIN tmp_cn_delta d ON ap.reference_number = CONCAT('CN-', d.returnid)
SET ap.amount         = ROUND(ap.amount - d.overstated_by, 2),
    ap.balance_amount = ROUND(ap.balance_amount - d.overstated_by, 2),
    ap.remarks        = CONCAT(COALESCE(ap.remarks, ''),
                               ' | GST-incl correction 2026-08-27: -Rs.',
                               FORMAT(d.overstated_by, 2))
WHERE ap.payment_mode = 'credit_note'
  AND ABS(ap.balance_amount - ap.amount) < 0.01     -- untouched only
  AND ap.amount - d.overstated_by >= 0;             -- never drive negative


-- ############################################################################
-- PART D -- return_credit running balances  (DIAGNOSTIC ONLY, no UPDATE)
--
-- stockist / super_distributor / distributor logins accumulate a single
-- running credit balance per user in return_credit(usertype, userid,
-- credit_amount), bumped by stock_return_finish.php with the (inflated)
-- return total and drawn down later by invoice submit / return delete. There
-- is NO per-return link, so this script will not touch it. The query below
-- quantifies, per user, how much of that balance came from the inclusive-GST
-- overstatement so it can be reconciled by hand.
-- ############################################################################
SELECT urs.from_usertype, urs.from_userid,
       ROUND(SUM(f.old_total - f.new_total), 2) AS return_credit_overstated_by
FROM tmp_ursi_fix f
JOIN user_return_stock urs ON urs.returnid = f.returnid
WHERE urs.from_usertype IN ('stockiest','super_distributor','distributor')
  AND urs.status NOT IN ('pending','reject')
GROUP BY urs.from_usertype, urs.from_userid
HAVING return_credit_overstated_by > 0.5
ORDER BY return_credit_overstated_by DESC;


-- ############################################################################
-- VERIFY -- all should read 0 (or list nothing) after the fix.
-- ############################################################################
SELECT 'return items still inflated' AS check_name, COUNT(*) AS n
FROM user_return_stock_items ursi JOIN products p ON p.id = ursi.prid
WHERE p.gst_type = 'inclusive' AND p.gst > 0 AND ABS(ursi.total - ursi.subtotal) > 0.5;

-- Header vs. line-item reconciliation (finished returns). Expect 0 rows.
SELECT 'return header mismatch' AS check_name, urs.returnid,
       urs.total AS header_total,
       ROUND(SUM(ursi.total) - COALESCE(urs.discount,0), 2) AS recomputed
FROM user_return_stock urs
JOIN user_return_stock_items ursi ON ursi.returnid = urs.returnid
WHERE urs.total <> 0
GROUP BY urs.returnid, urs.total, urs.discount
HAVING ABS(urs.total - (SUM(ursi.total) - COALESCE(urs.discount,0))) > 1
LIMIT 50;

-- Credits still needing a human (partly consumed, left as-is by C3):
SELECT 'advance credit needs manual review' AS check_name,
       ap.id, ap.reference_number, ap.amount, ap.balance_amount, d.overstated_by
FROM advance_payments ap
JOIN tmp_cn_delta d ON ap.reference_number = CONCAT('CN-', d.returnid)
WHERE ap.payment_mode = 'credit_note'
  AND ABS(ap.balance_amount - ap.amount) >= 0.01;

-- ----------------------------------------------------------------------------
-- Review every SELECT above. If the "still inflated" count is 0, the header
-- changes look right, and you have handled (or accepted) any "manual review"
-- advance credits:
COMMIT;
-- Otherwise:  ROLLBACK;
-- ----------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_ursi_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_urs_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_cn_delta;

-- Backup tables left in place for audit / manual rollback. Drop when confident:
--   DROP TABLE backup_20260827_urs_items_gst_fix;
--   DROP TABLE backup_20260827_urs_header_gst_fix;
--   DROP TABLE backup_20260827_advance_payments_cn_fix;
--
-- Manual rollback example (return line items):
--   UPDATE user_return_stock_items ursi
--   JOIN backup_20260827_urs_items_gst_fix b ON b.id = ursi.id
--   SET ursi.subtotal = b.subtotal,
--       ursi.gstamount_total = b.gstamount_total,
--       ursi.total = b.total;
-- ============================================================================
