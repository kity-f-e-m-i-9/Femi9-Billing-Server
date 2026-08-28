-- ============================================================================
-- Backfill: convert historical Internal Stock Transfer lines for now-inclusive
-- GST products (Lumi9 diapers, products.id 38-49) from GST-added-on-top to
-- GST-carved-out, and make the row's own gst_type snapshot match.
-- ============================================================================
-- Background
-- ----------
-- company/internal_transfer_action.php used to compute every transfer line as
--   taxable_value = sub_total
--   gst_amount    = sub_total * gst%          (GST on top)
--   total         = sub_total + gst_amount
-- and stamp internal_transfer.gst_type from the product's flag AT THAT TIME.
--
-- The Lumi9 diaper SKUs were later re-flagged products.gst_type='inclusive'.
-- The transfers already booked for them (~2026-07-31, 6 delivery notes,
-- 72 line rows) still carry gst_type='exclusive' and a GST-on-top total,
-- overstating each note by the added tax (~Rs.88,356 in aggregate).
--
-- Business decision (2026-08-27): those transfers should be treated as
-- GST-INCLUSIVE. This script rewrites them so the entered rate is taken to
-- already contain GST:
--   total         = sub_total                       (collapses down)
--   taxable_value = sub_total * 100 / (100 + gst%)   (carve out)
--   gst_amount    = sub_total - taxable_value
--   gst_type      = 'inclusive'                      (snapshot now matches)
--
-- The header table internal_transfer_invoice has no total column; every
-- consumer (internal_transfer_print.php, purchase-bills-hc-to-llp.php,
-- gst_intrsls_detailed_report.php) derives totals live from these rows, so
-- there is nothing else to update.
--
-- SELECTION SIGNATURE:
--   - products.gst_type = 'inclusive' AND products.gst > 0   (product is
--       inclusive NOW -- this is the intent that changed)
--   - ABS(it.total - it.sub_total) > 0.5                     (row was
--       computed GST-on-top, i.e. still needs converting; already-correct
--       rows have total == sub_total and are skipped, so re-running is safe)
--
-- This DELIBERATELY ignores internal_transfer.gst_type on the row, because
-- the whole point is that the stale 'exclusive' snapshot is what we are
-- correcting. If you ever add a genuinely-exclusive inclusive-flagged
-- product you must tighten this filter.
--
-- Requires MySQL 8.0+. Run on a backup / off-peak. Wrapped in a transaction:
-- inspect the PREVIEW SELECTs, then COMMIT (or ROLLBACK). A backup table of
-- every touched row is created first and left in place for manual rollback.
-- ============================================================================

START TRANSACTION;

-- 0) Backup ----------------------------------------------------------------
DROP TABLE IF EXISTS backup_20260827_internal_transfer_gst_fix;
CREATE TABLE backup_20260827_internal_transfer_gst_fix AS
SELECT it.*
FROM internal_transfer it
JOIN products p ON p.id = it.product_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(it.total - it.sub_total) > 0.5;

-- 1) Recompute affected lines -------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_it_fix;
CREATE TEMPORARY TABLE tmp_it_fix AS
SELECT
    it.id,
    it.tempid,
    it.gst_type      AS old_gst_type,
    it.sub_total,
    it.taxable_value AS old_taxable_value,
    it.gst_amount    AS old_gst_amount,
    it.total         AS old_total,
    ROUND(it.sub_total * 100 / (100 + p.gst), 2)                     AS new_taxable_value,
    ROUND(it.sub_total - (it.sub_total * 100 / (100 + p.gst)), 2)    AS new_gst_amount,
    it.sub_total                                                     AS new_total
FROM internal_transfer it
JOIN products p ON p.id = it.product_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(it.total - it.sub_total) > 0.5;

CREATE INDEX idx_tmp_it_fix ON tmp_it_fix (id);

-- Distinct affected transfers, separate table (MySQL error 1137: a TEMPORARY
-- table can't be referenced twice in one statement).
DROP TEMPORARY TABLE IF EXISTS tmp_it_temps;
CREATE TEMPORARY TABLE tmp_it_temps AS SELECT DISTINCT tempid FROM tmp_it_fix;
CREATE INDEX idx_tmp_it_temps ON tmp_it_temps (tempid);

-- 2) PREVIEW -----------------------------------------------------------
SELECT 'internal_transfer lines' AS section,
       COUNT(*)                  AS lines_to_fix,
       COUNT(DISTINCT tempid)    AS transfers_affected,
       ROUND(SUM(old_total - new_total), 2) AS total_overstatement_removed
FROM tmp_it_fix;
-- SELECT * FROM tmp_it_fix ORDER BY tempid, id;   -- uncomment for line detail

-- Per-transfer header effect (what the DL note / purchase bill / GST report
-- SUM(total) changes to):
SELECT b.tempid,
       MIN(b.date)                                              AS transfer_date,
       ROUND(SUM(b.total), 2)                                   AS old_grand_total,
       ROUND(SUM(COALESCE(f.new_total, b.total)), 2)            AS new_grand_total,
       ROUND(SUM(b.gst_amount), 2)                              AS old_grand_gst,
       ROUND(SUM(COALESCE(f.new_gst_amount, b.gst_amount)), 2)  AS new_grand_gst
FROM internal_transfer b
JOIN tmp_it_temps t   ON t.tempid = b.tempid
LEFT JOIN tmp_it_fix f ON f.id = b.id
GROUP BY b.tempid
ORDER BY b.tempid;

-- 3) APPLY -----------------------------------------------------------
UPDATE internal_transfer it
JOIN tmp_it_fix f ON f.id = it.id
SET it.taxable_value = f.new_taxable_value,
    it.gst_amount    = f.new_gst_amount,
    it.total         = f.new_total,
    it.gst_type      = 'inclusive';

-- ############################################################################
-- VERIFY -- should read 0 after the fix.
-- ############################################################################
SELECT 'internal_transfer lines still on-top' AS check_name, COUNT(*) AS n
FROM internal_transfer it JOIN products p ON p.id = it.product_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0 AND ABS(it.total - it.sub_total) > 0.5;

-- Line-level reconciliation: total == sub_total and
-- gst_amount == sub_total - taxable_value for every inclusive-product line.
-- Expect 0 rows.
SELECT 'internal_transfer line mismatch' AS check_name, it.id, it.tempid,
       it.sub_total, it.taxable_value, it.gst_amount, it.total
FROM internal_transfer it JOIN products p ON p.id = it.product_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ( ABS(it.total - it.sub_total) > 1
     OR ABS(it.gst_amount - (it.sub_total - it.taxable_value)) > 1 )
LIMIT 50;

-- ----------------------------------------------------------------------------
-- Review every SELECT above. If the "still on-top" count is 0 and the
-- per-transfer header numbers look right:
COMMIT;
-- Otherwise:  ROLLBACK;
-- ----------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_it_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_it_temps;

-- Backup table left in place for audit / manual rollback. Drop when confident:
--   DROP TABLE backup_20260827_internal_transfer_gst_fix;
--
-- Manual rollback (restores taxable_value, gst_amount, total AND gst_type):
--   UPDATE internal_transfer it
--   JOIN backup_20260827_internal_transfer_gst_fix b ON b.id = it.id
--   SET it.taxable_value = b.taxable_value,
--       it.gst_amount    = b.gst_amount,
--       it.total         = b.total,
--       it.gst_type      = b.gst_type;
-- ============================================================================
