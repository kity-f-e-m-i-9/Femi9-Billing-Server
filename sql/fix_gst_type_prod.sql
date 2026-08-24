-- ============================================================================
-- Backfill: correct stale gst_type on TP -> shop invoices
-- ============================================================================
-- Background: order-to-invoice.php (and, before Aug 13, shop-invoice-action.php)
-- used to decide IGST vs CGST/SGST by comparing raw free-text state names
-- (e.g. shop state "Tamilnadu" vs TP branch_state "Tamil Nadu"), which never
-- matched due to spelling/spacing differences and always fell back to
-- gst_type='outer' (IGST) even for shops correctly within the same state as
-- the TP. That was fixed in code (Aug 13 / Aug 20) to resolve both sides via
-- the firka location hierarchy instead. This script re-derives gst_type the
-- same way the fixed code does and corrects any invoice rows that still carry
-- the old, wrong value.
--
-- Requires MySQL 8.0+ (uses WITH RECURSIVE). Run on a backup / off-peak first.
-- Wrapped in a transaction — inspect the preview SELECT before running the
-- UPDATEs, and COMMIT only after you're satisfied.
-- ============================================================================

START TRANSACTION;

-- ----------------------------------------------------------------------------
-- 1) Resolve every partner_location_nodes id up to its STATE-depth ancestor
--    name, normalized (lowercase, no whitespace) for reliable comparison.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_node_state;
CREATE TEMPORARY TABLE tmp_node_state AS
WITH RECURSIVE state_depth AS (
    SELECT COALESCE(
        (SELECT depth FROM partner_location_layers WHERE layer_name = 'STATE' LIMIT 1),
        2
    ) AS depth
),
node_walk AS (
    SELECT id AS start_id, id AS cur_id, parent_id, depth, name, 0 AS hops
    FROM partner_location_nodes
    UNION ALL
    SELECT nw.start_id, n.id, n.parent_id, n.depth, n.name, nw.hops + 1
    FROM node_walk nw
    JOIN partner_location_nodes n ON n.id = nw.parent_id
    WHERE nw.depth <> (SELECT depth FROM state_depth)
      AND nw.hops < 10
)
SELECT nw.start_id AS node_id,
       LOWER(REPLACE(REPLACE(CONVERT(nw.name USING utf8mb4) COLLATE utf8mb4_general_ci, ' ', ''), '\t', '')) AS state_norm
FROM node_walk nw
WHERE nw.depth = (SELECT depth FROM state_depth);

CREATE INDEX idx_tmp_node_state ON tmp_node_state (node_id);

-- ----------------------------------------------------------------------------
-- 2) Shop -> normalized state, preferring firka_id, falling back to state_id
--    (special-casing the legacy state_id=2 "Tamilnadu" stray-node-id bug),
--    then the state master table.
-- ----------------------------------------------------------------------------
-- shop.state_id and shop.firka_id are VARCHAR columns and, for a subset of
-- shops, actually contain free text ("Tamilnadu ", "TAMIL NADU", Tamil
-- script, "Test state", blanks) instead of a numeric id — the PHP code
-- silently casts these to 0 with (int), which is safe there but MySQL's
-- strict-mode implicit numeric conversion throws on it ("Truncated incorrect
-- INTEGER/DOUBLE value"), and the optimizer does not reliably short-circuit
-- an AND guard before evaluating a CAST. So pre-compute safe numeric-or-NULL
-- versions of both columns first (NULLIF avoids ever casting the bad values),
-- then join/compare against those.
DROP TEMPORARY TABLE IF EXISTS tmp_shop_numeric;
CREATE TEMPORARY TABLE tmp_shop_numeric AS
SELECT
    s.temp_id,
    CASE WHEN s.firka_id REGEXP '^[0-9]+$' THEN s.firka_id END AS firka_id_num,
    CASE WHEN s.state_id REGEXP '^[0-9]+$' THEN s.state_id END AS state_id_num
FROM shop s;

CREATE INDEX idx_tmp_shop_numeric ON tmp_shop_numeric (temp_id);

DROP TEMPORARY TABLE IF EXISTS tmp_shop_state;
CREATE TEMPORARY TABLE tmp_shop_state AS
SELECT
    sn.temp_id,
    COALESCE(
        ns_firka.state_norm,
        CASE WHEN sn.state_id_num = '2' THEN 'tamilnadu' END,
        LOWER(REPLACE(REPLACE(st.st_name COLLATE utf8mb4_general_ci, ' ', ''), '\t', ''))
    ) AS shop_state_norm
FROM tmp_shop_numeric sn
LEFT JOIN tmp_node_state ns_firka
       ON ns_firka.node_id = sn.firka_id_num AND sn.firka_id_num > 0
LEFT JOIN state st
       ON st.id = sn.state_id_num;

CREATE INDEX idx_tmp_shop_state ON tmp_shop_state (temp_id);

-- ----------------------------------------------------------------------------
-- 3) TP -> set of normalized states across all assigned firka locations,
--    falling back to territory_partners.branch_state when the TP has no
--    firka assignment (or none resolve) at all.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_tp_state;
CREATE TEMPORARY TABLE tmp_tp_state AS
SELECT DISTINCT
    tp.id AS tp_id,
    ns.state_norm
FROM territory_partners tp
JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
JOIN tmp_node_state ns ON ns.node_id = tpl.location_id
WHERE ns.state_norm IS NOT NULL;

-- TPs with no firka-resolvable location at all: fall back to branch_state.
-- (Snapshot the "already resolved" tp ids into their own temp table first —
-- MySQL can't SELECT from a temporary table while also INSERTing into it.)
DROP TEMPORARY TABLE IF EXISTS tmp_tp_resolved;
CREATE TEMPORARY TABLE tmp_tp_resolved AS
SELECT DISTINCT tp_id FROM tmp_tp_state;

INSERT INTO tmp_tp_state (tp_id, state_norm)
SELECT tp.id, LOWER(REPLACE(REPLACE(tp.branch_state COLLATE utf8mb4_general_ci, ' ', ''), '\t', ''))
FROM territory_partners tp
LEFT JOIN tmp_tp_resolved r ON r.tp_id = tp.id
WHERE r.tp_id IS NULL;

CREATE INDEX idx_tmp_tp_state ON tmp_tp_state (tp_id, state_norm);

-- ----------------------------------------------------------------------------
-- 4) Compute the correct gst_type per invoice and compare to what's stored.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_gst_fix;
CREATE TEMPORARY TABLE tmp_gst_fix AS
SELECT
    ui.inv_id,
    ui.gst_type AS old_gst_type,
    CASE
        WHEN ss.shop_state_norm IS NOT NULL
         AND ss.shop_state_norm <> ''
         AND EXISTS (
             SELECT 1 FROM tmp_tp_state ts
             WHERE ts.tp_id = CAST(ui.from_user_id AS UNSIGNED)
               AND ts.state_norm COLLATE utf8mb4_general_ci = ss.shop_state_norm COLLATE utf8mb4_general_ci
         )
        THEN 'inner'
        ELSE 'outer'
    END AS new_gst_type
FROM user_invoice ui
JOIN tmp_shop_state ss ON ss.temp_id COLLATE utf8mb4_general_ci = ui.to_user_id COLLATE utf8mb4_general_ci
WHERE ui.to_user_type = 'shop'
  AND ui.from_user_type = 'territory_partner'
  AND (ui.status IS NULL OR ui.status <> 'cancelled');

-- Keep only the rows that actually need correcting. (new_gst_type is a
-- plain CASE-literal result column with no inherited collation, so compare
-- explicitly against old_gst_type's utf8mb4_general_ci.)
DELETE FROM tmp_gst_fix WHERE new_gst_type = old_gst_type COLLATE utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 5) PREVIEW — inspect before applying. Expect old_gst_type='outer' and
--    new_gst_type='inner' for the vast majority (the IGST-should-be-CGST/SGST
--    bug reported); confirm counts look sane for your data before UPDATEing.
-- ----------------------------------------------------------------------------
SELECT old_gst_type, new_gst_type, COUNT(*) AS invoice_count
FROM tmp_gst_fix
GROUP BY old_gst_type, new_gst_type;

-- Uncomment to see the full list of affected invoice ids:
-- SELECT * FROM tmp_gst_fix;

-- ----------------------------------------------------------------------------
-- 6) APPLY — updates both the invoice header and its line items.
-- ----------------------------------------------------------------------------
UPDATE user_invoice ui
JOIN tmp_gst_fix f ON f.inv_id = ui.inv_id
SET ui.gst_type = f.new_gst_type;

UPDATE user_invoice_items uii
JOIN tmp_gst_fix f ON f.inv_id = uii.inv_id
SET uii.gst_type = f.new_gst_type;

-- ----------------------------------------------------------------------------
-- 7) Verify, then COMMIT (or ROLLBACK if anything looks wrong).
-- ----------------------------------------------------------------------------
SELECT COUNT(*) AS invoices_corrected FROM tmp_gst_fix;

-- Review the two SELECTs above. If they match your expectations:
COMMIT;
-- Otherwise, run ROLLBACK; instead of COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_node_state;
DROP TEMPORARY TABLE IF EXISTS tmp_shop_numeric;
DROP TEMPORARY TABLE IF EXISTS tmp_shop_state;
DROP TEMPORARY TABLE IF EXISTS tmp_tp_state;
DROP TEMPORARY TABLE IF EXISTS tmp_tp_resolved;
DROP TEMPORARY TABLE IF EXISTS tmp_gst_fix;
