-- ============================================================================
-- Backfill: correct inflated totals on TP -> shop invoices for inclusive-tax
-- products (e.g. Lumi9 diapers)
-- ============================================================================
-- Background: order-to-invoice.php (converts a TP field order into a shop
-- invoice) always computed each line item's GST as if the product were
-- tax-EXCLUSIVE — total = subtotal + subtotal*gst%. But several products are
-- flagged gst_type='inclusive' in the products table, meaning the entered
-- rate already has GST baked in; for those, GST should be carved OUT of the
-- subtotal (total = subtotal, gstamount_total = subtotal - subtotal*100/(100+gst%)),
-- not added on top again. This double-taxed every inclusive-tax line item
-- created via that flow, inflating user_invoice_items.total (and therefore
-- user_invoice.total / the printed invoice Total) by the GST amount.
--
-- Fixed in code: femi9/billing/territory-partner/order-to-invoice.php now
-- branches on gst_type the same way shop-invoice-action.php already did.
-- This script is the one-time data correction for invoices created BEFORE
-- that code fix.
--
-- Only touches user_invoice_items rows where:
--   - the current product master says gst_type='inclusive' and gst>0
--   - the stored total does not equal the stored subtotal (i.e. GST was
--     added on top instead of carved out) — this is the exact signature of
--     the bug and won't false-positive on correctly-priced exclusive items.
-- For invoices that have been submitted (user_invoice.total <> 0), the
-- invoice header (sub_total/total) is also corrected by re-summing its
-- (now-fixed) line items, preserving existing discount/courier_charges/
-- roundoff. Draft invoices (total = 0, never submitted) have only their line
-- items corrected — the header stays 0 as-is, to be computed normally
-- whenever the TP eventually submits.
--
-- Requires MySQL 8.0+ (uses window functions / CTEs are not required here,
-- but JOIN-UPDATE syntax is MySQL-specific). Run on a backup / off-peak first.
-- Wrapped in a transaction — inspect the preview SELECTs before running the
-- UPDATEs, and COMMIT only after you're satisfied. A backup table of every
-- touched row is created first for rollback-after-commit if ever needed.
-- ============================================================================

START TRANSACTION;

-- ----------------------------------------------------------------------------
-- 0) Backup every user_invoice_items row this script will touch, plus the
--    user_invoice rows whose header will be updated. Kept permanently (not
--    dropped at the end) as an audit trail / manual-rollback source — safe
--    to drop later once you're confident the fix is correct.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS backup_20260824_user_invoice_items_gst_fix;
CREATE TABLE backup_20260824_user_invoice_items_gst_fix AS
SELECT uii.*
FROM user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(uii.total - uii.subtotal) > 0.5;

DROP TABLE IF EXISTS backup_20260824_user_invoice_gst_fix;
CREATE TABLE backup_20260824_user_invoice_gst_fix AS
SELECT ui.*
FROM user_invoice ui
WHERE ui.total <> 0
  AND ui.inv_id IN (
      SELECT DISTINCT inv_id FROM backup_20260824_user_invoice_items_gst_fix
  );

-- ----------------------------------------------------------------------------
-- 1) Recompute corrected subtotal/gstamount_total/total per affected line
--    item, using the same inclusive-tax formula as the fixed PHP code.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_item_fix;
CREATE TEMPORARY TABLE tmp_item_fix AS
SELECT
    uii.id,
    uii.inv_id,
    uii.subtotal AS old_subtotal,
    uii.gstamount_total AS old_gstamount_total,
    uii.total AS old_total,
    -- subtotal itself is unaffected by inclusive/exclusive (it's amount*qty - discount);
    -- only gstamount_total / total change.
    uii.subtotal AS new_subtotal,
    ROUND(uii.subtotal - (uii.subtotal * 100 / (100 + p.gst)), 2) AS new_gstamount_total,
    uii.subtotal AS new_total
FROM user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(uii.total - uii.subtotal) > 0.5;

CREATE INDEX idx_tmp_item_fix ON tmp_item_fix (id);
CREATE INDEX idx_tmp_item_fix_inv ON tmp_item_fix (inv_id);

-- ----------------------------------------------------------------------------
-- 2) PREVIEW — inspect before applying.
-- ----------------------------------------------------------------------------
SELECT COUNT(*) AS line_items_to_fix, COUNT(DISTINCT inv_id) AS invoices_affected
FROM tmp_item_fix;

-- Uncomment to see every affected line item:
-- SELECT * FROM tmp_item_fix;

-- ----------------------------------------------------------------------------
-- 3) Recompute the new invoice-level sub_total/total for SUBMITTED invoices
--    only (total <> 0). Draft invoices (total = 0) are left with header
--    untouched — only their line items get fixed in step 4/5.
-- ----------------------------------------------------------------------------
-- Snapshot the distinct affected invoice ids into their own temp table first
-- — MySQL can't reference the same temporary table twice in one statement
-- (once via JOIN, once via a subquery), so tmp_item_fix itself can't be the
-- source of both the LEFT JOIN and an IN (...) filter below.
DROP TEMPORARY TABLE IF EXISTS tmp_affected_invoices;
CREATE TEMPORARY TABLE tmp_affected_invoices AS
SELECT DISTINCT inv_id FROM tmp_item_fix;

CREATE INDEX idx_tmp_affected_invoices ON tmp_affected_invoices (inv_id);

DROP TEMPORARY TABLE IF EXISTS tmp_inv_fix;
CREATE TEMPORARY TABLE tmp_inv_fix AS
SELECT
    ui.inv_id,
    ui.total AS old_total,
    ui.sub_total AS old_sub_total,
    ROUND(SUM(
        COALESCE(f.new_total, uii.total)
    ), 2) AS new_sub_total,
    ROUND(
        SUM(COALESCE(f.new_total, uii.total)) - ui.discount + ui.courier_charges + ui.roundoff,
        2
    ) AS new_total
FROM user_invoice ui
JOIN tmp_affected_invoices ai ON ai.inv_id = ui.inv_id
JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
LEFT JOIN tmp_item_fix f ON f.id = uii.id
WHERE ui.total <> 0
GROUP BY ui.inv_id, ui.total, ui.sub_total, ui.discount, ui.courier_charges, ui.roundoff;

CREATE INDEX idx_tmp_inv_fix ON tmp_inv_fix (inv_id);

-- PREVIEW — invoice header changes:
SELECT * FROM tmp_inv_fix ORDER BY inv_id;

-- ----------------------------------------------------------------------------
-- 4) APPLY — line items (all affected invoices, submitted or draft).
-- ----------------------------------------------------------------------------
UPDATE user_invoice_items uii
JOIN tmp_item_fix f ON f.id = uii.id
SET uii.subtotal = f.new_subtotal,
    uii.gstamount_total = f.new_gstamount_total,
    uii.total = f.new_total;

-- ----------------------------------------------------------------------------
-- 5) APPLY — invoice headers (submitted invoices only).
-- ----------------------------------------------------------------------------
UPDATE user_invoice ui
JOIN tmp_inv_fix f ON f.inv_id = ui.inv_id
SET ui.sub_total = f.new_sub_total,
    ui.total = f.new_total;

-- ----------------------------------------------------------------------------
-- 6) Verify, then COMMIT (or ROLLBACK if anything looks wrong).
-- ----------------------------------------------------------------------------
SELECT COUNT(*) AS line_items_fixed FROM tmp_item_fix;
SELECT COUNT(*) AS invoice_headers_fixed FROM tmp_inv_fix;

-- Sanity check: 0 rows expected after the fix.
SELECT COUNT(*) AS remaining_affected_line_items
FROM user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(uii.total - uii.subtotal) > 0.5;

-- Review the SELECTs above. If they match your expectations (the last one
-- returns 0):
COMMIT;
-- Otherwise, run ROLLBACK; instead of COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_item_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_affected_invoices;
DROP TEMPORARY TABLE IF EXISTS tmp_inv_fix;

-- Backup tables (backup_20260824_user_invoice_items_gst_fix,
-- backup_20260824_user_invoice_gst_fix) are intentionally left in place for
-- audit / manual rollback. Drop them yourself once you're confident:
--   DROP TABLE backup_20260824_user_invoice_items_gst_fix;
--   DROP TABLE backup_20260824_user_invoice_gst_fix;
-- ============================================================================
