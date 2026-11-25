-- One-time data fix (2026-12-01, v2 -- corrected). Corrects shop.state_id
-- for every shop whose own district_id correctly resolves to a real
-- District-depth node. Found while investigating "Edit Shop" showing an
-- empty District dropdown for a real shop (territory-partner/shop-edit.php)
-- -- turned out shop.state_id (varchar(255)) is a mess company-wide: a
-- partner_location_nodes district id, a text value like 'Tamilnadu', blank,
-- or other garbage in the large majority of rows.
--
-- IMPORTANT -- shop.state_id is a `state` TABLE id (e.g. 7 = Tamilnadu,
-- 18 = Karnataka), NOT a partner_location_nodes id (where Tamilnadu is 2,
-- Karnataka is 1319) -- confirmed from shared/ShopInvoiceData.php's own
-- comment and every GST-calculation file that reads shop.state_id via
-- `SELECT st_name FROM state WHERE id=...` (invoice printing across TP,
-- Stockist, Super-Stockist, Distributor, Channel-Partner portals all depend
-- on this). An earlier version of this script (v1) wrongly wrote
-- partner_location_nodes ids into state_id instead -- caught and corrected
-- on local DB before this ever reached live; if v1 was somehow already run
-- against this DB, this v2 script safely re-derives and overwrites those
-- wrong values too (it doesn't assume state_id's current value is right,
-- it always re-derives fresh from district_id).
--
-- This fix derives the correct value as: district_id -> its real ancestor
-- State name (via partner_location_nodes) -> that name's real id in the
-- `state` table. Confirmed on local DB: 28,914 shop rows fixed (including
-- re-deriving a smaller earlier wrong attempt). The remaining shops whose
-- district_id itself doesn't resolve to a real district (0, invalid legacy
-- id, or literal text) are left untouched -- there's nothing to derive
-- their state from either.
--
-- 2026-11-25: added explicit COLLATE utf8mb4_general_ci on the `state`
-- table join -- live DB's `state` table turned out to be utf8mb4_unicode_ci
-- while partner_location_nodes is utf8mb4_general_ci (same drifted-collation
-- class of issue as elsewhere in this codebase), which threw "Illegal mix of
-- collations" on live even though local DB (same collation both sides)
-- never hit it.
--
-- Run this ONCE on the live DB, after
-- fix-single-district-tp-shop-districts-20261201.sql (that script corrects
-- district_id itself for single-district TPs first, so more rows here can
-- be derived correctly) -- though this one is safe to run on its own too.
-- Creates a backup table first; original values are recoverable from
-- shop_state_id_fix_backup_20261201 if anything looks wrong afterward.

DROP TABLE IF EXISTS shop_state_id_fix_backup_20261201;

CREATE TABLE shop_state_id_fix_backup_20261201 AS
SELECT s.id AS shop_id, s.district_id, s.state_id AS old_state_id, rs.id AS new_state_id, rs.st_name
FROM shop s
JOIN partner_location_nodes d  ON d.id = s.district_id AND d.depth = 3
JOIN partner_location_nodes st ON st.id = d.parent_id AND st.depth = 2
JOIN state rs ON LOWER(REPLACE(rs.st_name, ' ', '')) COLLATE utf8mb4_general_ci = LOWER(REPLACE(st.name, ' ', '')) COLLATE utf8mb4_general_ci
WHERE s.deleted_at IS NULL
  AND s.district_id REGEXP '^[0-9]+$'
  AND (s.state_id IS NULL OR TRIM(s.state_id) COLLATE utf8mb4_general_ci != CAST(rs.id AS CHAR));

-- Sanity check before applying -- compare this count against the backup
-- table's row count; they must match.
SELECT COUNT(*) AS rows_to_be_fixed FROM shop_state_id_fix_backup_20261201;

UPDATE shop s
JOIN partner_location_nodes d  ON d.id = s.district_id AND d.depth = 3
JOIN partner_location_nodes st ON st.id = d.parent_id AND st.depth = 2
JOIN state rs ON LOWER(REPLACE(rs.st_name, ' ', '')) COLLATE utf8mb4_general_ci = LOWER(REPLACE(st.name, ' ', '')) COLLATE utf8mb4_general_ci
SET s.state_id = rs.id
WHERE s.deleted_at IS NULL
  AND s.district_id REGEXP '^[0-9]+$'
  AND (s.state_id IS NULL OR TRIM(s.state_id) COLLATE utf8mb4_general_ci != CAST(rs.id AS CHAR));

-- After running, ROW_COUNT() from the UPDATE above should equal the
-- rows_to_be_fixed count from the SELECT above (both were 28,914 on local).
