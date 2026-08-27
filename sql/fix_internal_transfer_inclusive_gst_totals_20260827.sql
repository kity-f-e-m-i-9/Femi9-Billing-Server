-- ============================================================================
-- Backfill: correct inflated Internal Stock Transfer totals for inclusive-GST
-- products (e.g. Lumi9 diapers).
-- ============================================================================
-- Background
-- ----------
-- company/internal_transfer_action.php used to compute every transfer line as:
--
--     sub_total     = rate*qty - discount
--     taxable_value = sub_total
--     gst_amount    = sub_total * gst%          -- GST always added on top
--     total         = sub_total + gst_amount    -- inflated for 'inclusive'
--
-- For a product flagged products.gst_type = 'inclusive' the entered rate
-- already contains GST, so the tax must be backed OUT of sub_total, not added
-- again:
--
--     total         = sub_total
--     taxable_value = sub_total / (1 + gst%/100)
--     gst_amount    = sub_total - taxable_value
--
-- The code is now fixed (branches on gst_type, same convention as
-- neksomo-manufacturer-purchase-action.php / user-invoice-action.php). This
-- script corrects the internal_transfer rows written before that fix.
--
-- Blast radius:
--   * internal_transfer            -- taxable_value / gst_amount / total
--                                    inflated for inclusive lines. sub_total,
--                                    rate, qty, discount are all correct.
--   * internal_transfer_invoice    -- header row has NO total column; every
--                                    consumer (purchase-bills-hc-to-llp.php,
--                                    internal_transfer_print.php,
--                                    gst_intrsls_detailed_report.php) derives
--                                    it live as SUM(internal_transfer.total) /
--                                    SUM(gst_amount), so fixing the line rows
--                                    fixes those views automatically. Nothing
--                                    else to update here.
--
-- Selection is by BUG SIGNATURE, not by date:
--   - products.gst_type = 'inclusive' AND products.gst > 0
--   - ABS(it.total - it.sub_total) > 0.5      (GST added on top instead of
--     carved out -- the exact fingerprint; leaves correct exclusive rows and
--     already-fixed rows untouched, so re-running is safe)
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
    it.sub_total,
    it.taxable_value AS old_taxable_value,
    it.gst_amount    AS old_gst_amount,
    it.total         AS old_total,
    ROUND(it.sub_total / (1 + p.gst / 100), 2)                       AS new_taxable_value,
    ROUND(it.sub_total - (it.sub_total / (1 + p.gst / 100)), 2)      AS new_gst_amount,
    it.sub_total                                                     AS new_total
FROM internal_transfer it
JOIN products p ON p.id = it.product_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ABS(it.total - it.sub_total) > 0.5;

CREATE INDEX idx_tmp_it_fix      ON tmp_it_fix (id);
CREATE INDEX idx_tmp_it_fix_temp ON tmp_it_fix (tempid);

-- Distinct affected transfers, in a table of their own. MySQL cannot
-- reference a TEMPORARY table twice in one statement (error 1137), so the
-- per-transfer preview below joins this instead of sub-selecting tmp_it_fix.
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

-- Per-transfer header effect (what the SUM(total) shown on the DL note /
-- purchase bill / GST report changes to). Recompute per line from the
-- backup + tmp_it_fix so tmp_it_fix is still referenced only once here.
SELECT b.tempid,
       ROUND(SUM(b.total), 2)                                    AS old_grand_total,
       ROUND(SUM(COALESCE(f.new_total, b.total)), 2)             AS new_grand_total,
       ROUND(SUM(b.gst_amount), 2)                               AS old_grand_gst,
       ROUND(SUM(COALESCE(f.new_gst_amount, b.gst_amount)), 2)   AS new_grand_gst
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
    it.total         = f.new_total;

-- ############################################################################
-- VERIFY -- should read 0 after the fix.
-- ############################################################################
SELECT 'internal_transfer lines still inflated' AS check_name, COUNT(*) AS n
FROM internal_transfer it JOIN products p ON p.id = it.product_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0 AND ABS(it.total - it.sub_total) > 0.5;

-- Line-level reconciliation: for every fixed inclusive line,
-- total == sub_total and gst_amount == sub_total - taxable_value. Expect 0 rows.
SELECT 'internal_transfer line mismatch' AS check_name, it.id, it.tempid,
       it.sub_total, it.taxable_value, it.gst_amount, it.total
FROM internal_transfer it JOIN products p ON p.id = it.product_id
WHERE p.gst_type = 'inclusive' AND p.gst > 0
  AND ( ABS(it.total - it.sub_total) > 1
     OR ABS(it.gst_amount - (it.sub_total - it.taxable_value)) > 1 )
LIMIT 50;

-- ----------------------------------------------------------------------------
-- Review every SELECT above. If the "still inflated" count is 0 and the
-- per-transfer header numbers look right:
COMMIT;
-- Otherwise:  ROLLBACK;
-- ----------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_it_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_it_temps;

-- Backup table left in place for audit / manual rollback. Drop when confident:
--   DROP TABLE backup_20260827_internal_transfer_gst_fix;
--
-- Manual rollback:
--   UPDATE internal_transfer it
--   JOIN backup_20260827_internal_transfer_gst_fix b ON b.id = it.id
--   SET it.taxable_value = b.taxable_value,
--       it.gst_amount    = b.gst_amount,
--       it.total         = b.total;
-- ============================================================================
