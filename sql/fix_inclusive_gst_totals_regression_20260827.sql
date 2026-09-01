-- ============================================================================
-- Backfill #2: re-correct inflated invoice totals for inclusive-tax products
-- (e.g. Lumi9 diapers) on invoices created during the jd-merge regression
-- window (approx 2026-08-24 .. 2026-08-27).
-- ============================================================================
-- Background
-- ----------
-- The inclusive-GST fix (commit e5c4a15, 2026-08-24) stopped order-to-invoice.php
-- from adding GST on top of a price that already has GST baked in. It was
-- correct for ~a day, then the deploy workflow's `git merge origin/jd` step
-- re-introduced the pre-fix code from a stale jd branch and silently reverted
-- it in production. Shop invoices generated in that window were double-taxed
-- again: user_invoice_items.total = subtotal + subtotal*gst%  instead of
-- total = subtotal  (with gstamount_total carved out of subtotal).
--
-- The auto-merge has now been removed (main deploys directly), so no new
-- invoices are affected. This script is the data correction for the window.
--
-- Scope
-- -----
--   * user_invoice_items / user_invoice   -- TP -> shop invoices (the flow
--                                             that regressed: order-to-invoice.php)
--   * invoice_items / invoice             -- TP -> customer invoices. This flow
--                                             (customer-invoice-action.php) has
--                                             ALWAYS handled inclusive GST
--                                             correctly, so this section
--                                             normally matches 0 rows. It is
--                                             included only as a belt-and-braces
--                                             sweep with the same safe signature
--                                             filter -- a no-op if nothing is wrong.
--   * tp_invoices / tp_invoice_items      -- Company -> TP invoices. These tables
--                                             have NO GST columns (only qty/rate/
--                                             amount), so there is nothing to
--                                             double-tax and nothing to fix.
--                                             Deliberately not touched.
--
-- Selection is by BUG SIGNATURE, not by date:
--   - products.gst_type = 'inclusive' AND products.gst > 0
--   - ABS(item.total - item.subtotal) > 0.5   (GST added on top instead of
--     carved out -- the exact fingerprint; won't touch correct exclusive rows)
-- This means any earlier missed row is also picked up, and re-running is safe
-- (already-fixed rows have total == subtotal and no longer match).
--
-- Requires MySQL 8.0+. Run on a backup / off-peak. Wrapped in a transaction:
-- inspect the PREVIEW SELECTs, then COMMIT (or ROLLBACK). Backup tables of
-- every touched row are created first and left in place for manual rollback.
-- ============================================================================

START TRANSACTION;

-- ############################################################################
-- PART A -- TP -> SHOP invoices  (user_invoice_items / user_invoice)
-- ############################################################################

-- A0) Backup -------------------------------------------------------------------
DROP TABLE IF EXISTS backup_20260827_user_invoice_items_gst_fix;
CREATE TABLE backup_20260827_user_invoice_items_gst_fix AS
SELECT uii.*
FROM user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(uii.total - uii.subtotal) > 0.5;

DROP TABLE IF EXISTS backup_20260827_user_invoice_gst_fix;
CREATE TABLE backup_20260827_user_invoice_gst_fix AS
SELECT ui.*
FROM user_invoice ui
WHERE ui.total <> 0
  AND ui.inv_id IN (SELECT DISTINCT inv_id FROM backup_20260827_user_invoice_items_gst_fix);

-- A1) Recompute affected line items ------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_uii_fix;
CREATE TEMPORARY TABLE tmp_uii_fix AS
SELECT
    uii.id,
    uii.inv_id,
    uii.subtotal        AS old_subtotal,
    uii.gstamount_total AS old_gstamount_total,
    uii.total           AS old_total,
    uii.subtotal        AS new_subtotal,               -- subtotal unaffected
    ROUND(uii.subtotal - (uii.subtotal * 100 / (100 + p.gst)), 2) AS new_gstamount_total,
    uii.subtotal        AS new_total                   -- total collapses to subtotal
FROM user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(uii.total - uii.subtotal) > 0.5;

CREATE INDEX idx_tmp_uii_fix     ON tmp_uii_fix (id);
CREATE INDEX idx_tmp_uii_fix_inv ON tmp_uii_fix (inv_id);

-- A2) PREVIEW ---------------------------------------------------------------
SELECT 'PART A: shop invoices' AS section,
       COUNT(*)                AS line_items_to_fix,
       COUNT(DISTINCT inv_id)  AS invoices_affected
FROM tmp_uii_fix;
-- SELECT * FROM tmp_uii_fix;   -- uncomment for full detail

-- A3) Recompute SUBMITTED invoice headers (total <> 0) ---------------------
DROP TEMPORARY TABLE IF EXISTS tmp_ui_affected;
CREATE TEMPORARY TABLE tmp_ui_affected AS SELECT DISTINCT inv_id FROM tmp_uii_fix;
CREATE INDEX idx_tmp_ui_affected ON tmp_ui_affected (inv_id);

DROP TEMPORARY TABLE IF EXISTS tmp_ui_fix;
CREATE TEMPORARY TABLE tmp_ui_fix AS
SELECT
    ui.inv_id,
    ui.total     AS old_total,
    ui.sub_total AS old_sub_total,
    ROUND(SUM(COALESCE(f.new_total, uii.total)), 2) AS new_sub_total,
    ROUND(SUM(COALESCE(f.new_total, uii.total)) - ui.discount + ui.courier_charges + ui.roundoff, 2) AS new_total
FROM user_invoice ui
JOIN tmp_ui_affected ai      ON ai.inv_id = ui.inv_id
JOIN user_invoice_items uii  ON uii.inv_id = ui.inv_id
LEFT JOIN tmp_uii_fix f      ON f.id = uii.id
WHERE ui.total <> 0
GROUP BY ui.inv_id, ui.total, ui.sub_total, ui.discount, ui.courier_charges, ui.roundoff;

CREATE INDEX idx_tmp_ui_fix ON tmp_ui_fix (inv_id);

-- PREVIEW -- header changes:
SELECT * FROM tmp_ui_fix ORDER BY inv_id;

-- A4) APPLY -- line items (submitted + draft) ------------------------------
UPDATE user_invoice_items uii
JOIN tmp_uii_fix f ON f.id = uii.id
SET uii.subtotal        = f.new_subtotal,
    uii.gstamount_total = f.new_gstamount_total,
    uii.total           = f.new_total;

-- A5) APPLY -- headers (submitted only) ----------------------------------
UPDATE user_invoice ui
JOIN tmp_ui_fix f ON f.inv_id = ui.inv_id
SET ui.sub_total = f.new_sub_total,
    ui.total     = f.new_total;


-- ############################################################################
-- PART B -- TP -> CUSTOMER invoices  (invoice_items / invoice)
--          Normally a no-op (this flow was never broken). Same safe filter.
-- ############################################################################

-- B0) Backup -------------------------------------------------------------------
DROP TABLE IF EXISTS backup_20260827_invoice_items_gst_fix;
CREATE TABLE backup_20260827_invoice_items_gst_fix AS
SELECT ii.*
FROM invoice_items ii
JOIN products p ON p.id = ii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(ii.total - ii.subtotal) > 0.5;

DROP TABLE IF EXISTS backup_20260827_invoice_gst_fix;
CREATE TABLE backup_20260827_invoice_gst_fix AS
SELECT i.*
FROM invoice i
WHERE i.total <> 0
  AND i.inv_id IN (SELECT DISTINCT inv_id FROM backup_20260827_invoice_items_gst_fix);

-- B1) Recompute affected line items ------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_ii_fix;
CREATE TEMPORARY TABLE tmp_ii_fix AS
SELECT
    ii.id,
    ii.inv_id,
    ii.subtotal        AS old_subtotal,
    ii.gstamount_total AS old_gstamount_total,
    ii.total           AS old_total,
    ii.subtotal        AS new_subtotal,
    ROUND(ii.subtotal - (ii.subtotal * 100 / (100 + p.gst)), 2) AS new_gstamount_total,
    ii.subtotal        AS new_total
FROM invoice_items ii
JOIN products p ON p.id = ii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(ii.total - ii.subtotal) > 0.5;

CREATE INDEX idx_tmp_ii_fix     ON tmp_ii_fix (id);
CREATE INDEX idx_tmp_ii_fix_inv ON tmp_ii_fix (inv_id);

-- B2) PREVIEW ---------------------------------------------------------------
SELECT 'PART B: customer invoices' AS section,
       COUNT(*)                    AS line_items_to_fix,
       COUNT(DISTINCT inv_id)      AS invoices_affected
FROM tmp_ii_fix;

-- B3) Recompute SUBMITTED invoice headers (total <> 0) ---------------------
-- customer-invoice-submit.php: total = round(sub_total - discount + courier_charges)
-- (roundoff is stored but NOT added into total for this flow).
DROP TEMPORARY TABLE IF EXISTS tmp_i_affected;
CREATE TEMPORARY TABLE tmp_i_affected AS SELECT DISTINCT inv_id FROM tmp_ii_fix;
CREATE INDEX idx_tmp_i_affected ON tmp_i_affected (inv_id);

DROP TEMPORARY TABLE IF EXISTS tmp_i_fix;
CREATE TEMPORARY TABLE tmp_i_fix AS
SELECT
    i.inv_id,
    i.total     AS old_total,
    i.sub_total AS old_sub_total,
    ROUND(SUM(COALESCE(f.new_total, ii.total)), 2) AS new_sub_total,
    ROUND(SUM(COALESCE(f.new_total, ii.total)) - i.discount + i.courier_charges) AS new_total
FROM invoice i
JOIN tmp_i_affected ai     ON ai.inv_id = i.inv_id
JOIN invoice_items ii      ON ii.inv_id = i.inv_id
LEFT JOIN tmp_ii_fix f     ON f.id = ii.id
WHERE i.total <> 0
GROUP BY i.inv_id, i.total, i.sub_total, i.discount, i.courier_charges;

CREATE INDEX idx_tmp_i_fix ON tmp_i_fix (inv_id);

-- PREVIEW -- header changes:
SELECT * FROM tmp_i_fix ORDER BY inv_id;

-- B4) APPLY -- line items -------------------------------------------------
UPDATE invoice_items ii
JOIN tmp_ii_fix f ON f.id = ii.id
SET ii.subtotal        = f.new_subtotal,
    ii.gstamount_total = f.new_gstamount_total,
    ii.total           = f.new_total;

-- B5) APPLY -- headers (submitted only) ---------------------------------
UPDATE invoice i
JOIN tmp_i_fix f ON f.inv_id = i.inv_id
SET i.sub_total = f.new_sub_total,
    i.total     = f.new_total;


-- ############################################################################
-- VERIFY -- all three counts below should be 0 after the fix.
-- ############################################################################
SELECT 'shop items remaining'     AS check_name, COUNT(*) AS n
FROM user_invoice_items uii JOIN products p ON p.id = uii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0 AND ABS(uii.total - uii.subtotal) > 0.5
UNION ALL
SELECT 'customer items remaining', COUNT(*)
FROM invoice_items ii JOIN products p ON p.id = ii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0 AND ABS(ii.total - ii.subtotal) > 0.5;

-- Optional cross-check: header total should reconcile with its line items.
-- Expect 0 rows.
SELECT 'shop header mismatch' AS check_name, ui.inv_id,
       ui.total AS header_total,
       ROUND(SUM(x.total) - ui.discount + ui.courier_charges + ui.roundoff, 2) AS recomputed
FROM user_invoice ui
JOIN user_invoice_items x ON x.inv_id = ui.inv_id
WHERE ui.total <> 0
GROUP BY ui.inv_id, ui.total, ui.discount, ui.courier_charges, ui.roundoff
HAVING ABS(ui.total - (SUM(x.total) - ui.discount + ui.courier_charges + ui.roundoff)) > 1
LIMIT 50;

-- ----------------------------------------------------------------------------
-- Review every SELECT above. If the "remaining" counts are 0 and the header
-- changes look right:
COMMIT;
-- Otherwise:  ROLLBACK;
-- ----------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_uii_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_ui_affected;
DROP TEMPORARY TABLE IF EXISTS tmp_ui_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_ii_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_i_affected;
DROP TEMPORARY TABLE IF EXISTS tmp_i_fix;

-- Backup tables left in place for audit / manual rollback. Drop when confident:
--   DROP TABLE backup_20260827_user_invoice_items_gst_fix;
--   DROP TABLE backup_20260827_user_invoice_gst_fix;
--   DROP TABLE backup_20260827_invoice_items_gst_fix;
--   DROP TABLE backup_20260827_invoice_gst_fix;
--
-- Manual rollback example (shop line items):
--   UPDATE user_invoice_items uii
--   JOIN backup_20260827_user_invoice_items_gst_fix b ON b.id = uii.id
--   SET uii.subtotal = b.subtotal,
--       uii.gstamount_total = b.gstamount_total,
--       uii.total = b.total;
-- ============================================================================
