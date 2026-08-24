-- ============================================================================
-- Backfill: fill in missing state_id / district_id / taluk_id / firka_id on
-- TP-onboarded shops by inheriting the location from the Territory Partner
-- they belong to.
-- ============================================================================
-- Why: a shop's gst_type (IGST vs CGST/SGST) is decided by comparing the
-- shop's state to the TP's state. Shops onboarded with no usable location at
-- all (state_id='0'/blank, firka_id='0'/blank) fall through every state
-- lookup and always get taxed as inter-state (IGST), even when they are
-- actually in the same state as their TP.
--
-- Scope: EVERY TP-onboarded shop is re-derived and compared against its
-- current state_id/district_id/taluk_id/firka_id, and only rows that
-- actually differ get updated. This catches three overlapping problems:
--   1. shops with no location at all (blank/0/non-numeric)
--   2. shops whose state_id is non-blank GARBAGE — free text like
--      "Tamilnadu ", "TAMIL NADU", Tamil script, "Test state" — which fails
--      every lookup exactly like a blank value does
--   3. shops that already have a perfectly good, resolvable firka_id, but
--      whose separate state_id column still holds a stray
--      partner_location_nodes id (most commonly '2', the node id for
--      "Tamilnadu") instead of the real state.id (7) — this one is easy to
--      miss because the value looks like a plausible id and the shop's
--      firka/district/taluk are otherwise fine, so an earlier, narrower
--      version of this script (which only looked at shops where NEITHER
--      firka_id NOR state_id resolved) silently skipped these entirely.
--
-- For each such shop, inherited from its onboarding TP
-- (shop.onboard_userID = territory_partners.id, onboard_userTYPE='territory_partner'):
--   - If the TP has exactly ONE assigned firka: fill firka_id, and derive
--     district_id/taluk_id/state_id from that firka's ancestor chain, plus
--     state_id as a safety net.
--   - If the TP has MULTIPLE assigned firkas: there's no way to know which
--     of the TP's areas this particular shop is in, so only state_id is
--     filled (enough to make gst_type correct); firka_id/district_id/
--     taluk_id are left blank rather than guessed.
--   - If the TP has NO firka assignment at all: fall back to the TP's
--     free-text branch_state, resolved to a state.id via the state table
--     (best-effort, only used if it matches a row there).
--
-- Requires MySQL 8.0+ (uses WITH RECURSIVE). Run on a backup / off-peak
-- first. The only write to a real table is the single UPDATE in step 6 —
-- everything before it just builds temporary tables (MySQL auto-commits
-- DDL, so the transaction only actually guards that UPDATE). Inspect the
-- preview SELECT's counts before letting it reach COMMIT; ROLLBACK undoes
-- the UPDATE if anything looks wrong.
-- ============================================================================

START TRANSACTION;

-- ----------------------------------------------------------------------------
-- 1) Every partner_location_nodes id resolved up to its ancestor at each of
--    STATE / DISTRICT / TALUK depth (whichever apply), plus its own depth.
-- ----------------------------------------------------------------------------
-- Depth numbers for each named layer (STATE/DISTRICT/TALUK), captured as
-- plain scalar subqueries rather than a temp table — a temp table can't be
-- read again from inside the recursive CTE that needs these same values.
SET @state_depth    = (SELECT depth FROM partner_location_layers WHERE layer_name = 'STATE' LIMIT 1);
SET @district_depth = (SELECT depth FROM partner_location_layers WHERE layer_name = 'DISTRICT' LIMIT 1);
SET @taluk_depth    = (SELECT depth FROM partner_location_layers WHERE layer_name = 'TALUK' LIMIT 1);

-- IMPORTANT: partner_location_nodes ids and `state` table ids are two
-- SEPARATE id spaces that happen to overlap numerically (partner_location_nodes
-- id=2 is "Tamilnadu", but state.id=2 is a different state entirely — Tamil
-- Nadu in the `state` table is id=7). shop.district_id/taluk_id/firka_id are
-- partner_location_nodes ids (that's what the app's own firka-resolution code
-- walks), but shop.state_id is a legacy FK into the separate `state` table —
-- this is exactly the "legacy state_id=2" quirk the app code special-cases.
-- So the STATE-depth ancestor must be translated to a state.id by matching
-- its name, never written in as a raw partner_location_nodes id.
DROP TEMPORARY TABLE IF EXISTS tmp_node_ancestors_raw;
CREATE TEMPORARY TABLE tmp_node_ancestors_raw AS
WITH RECURSIVE node_walk AS (
    SELECT id AS start_id, id AS cur_id, parent_id, depth, 0 AS hops
    FROM partner_location_nodes
    UNION ALL
    SELECT nw.start_id, n.id, n.parent_id, n.depth, nw.hops + 1
    FROM node_walk nw
    JOIN partner_location_nodes n ON n.id = nw.parent_id
    WHERE nw.hops < 10
)
SELECT
    nw.start_id AS firka_node_id,
    MAX(CASE WHEN nw.depth = @state_depth THEN nw.cur_id END) AS state_node_id,
    MAX(CASE WHEN nw.depth = @district_depth THEN nw.cur_id END) AS district_node_id,
    MAX(CASE WHEN nw.depth = @taluk_depth THEN nw.cur_id END) AS taluk_node_id
FROM node_walk nw
GROUP BY nw.start_id;

DROP TEMPORARY TABLE IF EXISTS tmp_node_ancestors;
CREATE TEMPORARY TABLE tmp_node_ancestors AS
SELECT
    r.firka_node_id,
    st.id AS state_node_id,       -- translated: real state.id, name-matched
    r.district_node_id,
    r.taluk_node_id
FROM tmp_node_ancestors_raw r
LEFT JOIN partner_location_nodes pln ON pln.id = r.state_node_id
LEFT JOIN state st
       ON LOWER(REPLACE(st.st_name, ' ', '')) COLLATE utf8mb4_general_ci
        = LOWER(REPLACE(pln.name, ' ', '')) COLLATE utf8mb4_general_ci;

CREATE INDEX idx_tmp_node_ancestors ON tmp_node_ancestors (firka_node_id);

-- A second copy under its own name — MySQL cannot join the same temporary
-- table into a query twice under different aliases ("Can't reopen table"),
-- and step 4 below needs to look this up once for the shop's own firka and
-- once for the TP's single firka in the same query.
DROP TEMPORARY TABLE IF EXISTS tmp_node_ancestors_2;
CREATE TEMPORARY TABLE tmp_node_ancestors_2 AS SELECT * FROM tmp_node_ancestors;
CREATE INDEX idx_tmp_node_ancestors_2 ON tmp_node_ancestors_2 (firka_node_id);

-- ----------------------------------------------------------------------------
-- 2) Per-TP firka assignment: single unambiguous firka, or NULL when the TP
--    has none / more than one.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_tp_single_firka;
CREATE TEMPORARY TABLE tmp_tp_single_firka AS
SELECT territory_partner_id AS tp_id, MIN(location_id) AS firka_node_id
FROM territory_partner_locations
GROUP BY territory_partner_id
HAVING COUNT(*) = 1;

CREATE INDEX idx_tmp_tp_single_firka ON tmp_tp_single_firka (tp_id);

-- State for TPs with MULTIPLE firkas: take the distinct set of states across
-- all their assigned firkas, and keep it only when every firka agrees on the
-- same single state (the overwhelmingly common case — a TP's areas rarely
-- span more than one state). If a TP's firkas genuinely span >1 state,
-- there's no single correct state_id for an unassigned shop under them, so
-- those are intentionally left unresolved rather than guessed.
DROP TEMPORARY TABLE IF EXISTS tmp_tp_multi_state;
CREATE TEMPORARY TABLE tmp_tp_multi_state AS
SELECT tp_id, MIN(state_node_id) AS state_node_id
FROM (
    SELECT DISTINCT tpl.territory_partner_id AS tp_id, na.state_node_id
    FROM territory_partner_locations tpl
    JOIN tmp_node_ancestors na ON na.firka_node_id = tpl.location_id
    WHERE na.state_node_id IS NOT NULL
) x
GROUP BY tp_id
HAVING COUNT(DISTINCT state_node_id) = 1;

CREATE INDEX idx_tmp_tp_multi_state ON tmp_tp_multi_state (tp_id);

-- Fallback state (via free-text branch_state) for TPs with zero firka
-- assignments at all.
DROP TEMPORARY TABLE IF EXISTS tmp_tp_no_firka_state;
CREATE TEMPORARY TABLE tmp_tp_no_firka_state AS
SELECT tp.id AS tp_id, st.id AS state_id
FROM territory_partners tp
LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
LEFT JOIN state st
       ON st.st_name COLLATE utf8mb4_general_ci = tp.branch_state COLLATE utf8mb4_general_ci
WHERE tpl.territory_partner_id IS NULL;

CREATE INDEX idx_tmp_tp_no_firka_state ON tmp_tp_no_firka_state (tp_id);

-- ----------------------------------------------------------------------------
-- 3) Target shops: EVERY TP-onboarded shop, whether or not its state_id
--    currently looks "fine" — a shop can already have a perfectly good,
--    resolvable firka_id while its separate state_id column still holds
--    garbage (most commonly the literal partner_location_nodes id for
--    Tamilnadu, '2', instead of the real state.id, 7 — since the two id
--    spaces overlap numerically, that garbage value looks like a plausible
--    id and was easy to miss). Deriving the correct value fresh from the
--    shop's own firka (preferred) or its TP, for every shop, and only
--    overwriting where it actually differs, catches that class of bug too —
--    not just shops where state_id was blank/unresolvable outright.
-- ----------------------------------------------------------------------------
-- Pre-compute safe numeric-or-NULL versions of the shop's own id columns
-- first (same reason as tmp_shop_numeric in the gst_type script: MySQL's
-- optimizer does not reliably short-circuit an AND guard before evaluating a
-- CAST, so a REGEXP check alongside a CAST in the same ON/WHERE clause can
-- still throw on garbage values).
DROP TEMPORARY TABLE IF EXISTS tmp_shop_numeric_ids;
CREATE TEMPORARY TABLE tmp_shop_numeric_ids AS
SELECT
    s.temp_id,
    s.onboard_userTYPE,
    CASE WHEN s.onboard_userID REGEXP '^[0-9]+$' THEN s.onboard_userID END AS onboard_tp_id_num,
    CASE WHEN s.firka_id REGEXP '^[0-9]+$' THEN s.firka_id END AS firka_id_num,
    CASE WHEN s.state_id REGEXP '^[0-9]+$' THEN s.state_id END AS state_id_num
FROM shop s;

CREATE INDEX idx_tmp_shop_numeric_ids ON tmp_shop_numeric_ids (temp_id);

DROP TEMPORARY TABLE IF EXISTS tmp_shops_to_fix;
CREATE TEMPORARY TABLE tmp_shops_to_fix AS
SELECT
    sn.temp_id,
    sn.onboard_tp_id_num AS tp_id,
    firka_ok.firka_node_id AS own_firka_id,   -- the shop's OWN firka, if it resolves
    state_ok.id AS own_state_id               -- the shop's OWN state_id, if it resolves
FROM tmp_shop_numeric_ids sn
LEFT JOIN tmp_node_ancestors firka_ok ON firka_ok.firka_node_id = sn.firka_id_num
LEFT JOIN state state_ok ON state_ok.id = sn.state_id_num
WHERE sn.onboard_userTYPE = 'territory_partner'
  AND sn.onboard_tp_id_num IS NOT NULL;

CREATE INDEX idx_tmp_shops_to_fix ON tmp_shops_to_fix (temp_id);
CREATE INDEX idx_tmp_shops_to_fix_tp ON tmp_shops_to_fix (tp_id);

-- ----------------------------------------------------------------------------
-- 4) Compute the correct value for every shop. Priority for state_id:
--    1. the shop's OWN firka (most specific, always trusted when present)
--    2. the shop's OWN already-valid state_id (nothing to fix)
--    3. the TP's single/shared firka-derived state (fallback for shops with
--       neither of the above)
--    firka_id/district_id/taluk_id are only ever filled from the shop's own
--    firka or (when the TP has exactly one) the TP's — never guessed from a
--    multi-firka TP, so those three are left alone whenever the shop has no
--    resolvable firka of its own.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_shop_fix_values;
CREATE TEMPORARY TABLE tmp_shop_fix_values AS
SELECT
    f.temp_id,
    CASE WHEN f.own_firka_id IS NULL THEN tsf.firka_node_id END AS new_firka_id,
    COALESCE(
        na_own.state_node_id,   -- from the shop's own firka
        f.own_state_id,         -- already correct, nothing to change
        na_tp.state_node_id,    -- from the TP's single firka
        tms.state_node_id,      -- from the TP's multiple-but-agreeing firkas
        nf.state_id             -- from the TP's free-text branch_state
    ) AS new_state_id,
    CASE WHEN f.own_firka_id IS NULL THEN na_tp.district_node_id ELSE na_own.district_node_id END AS new_district_id,
    CASE WHEN f.own_firka_id IS NULL THEN na_tp.taluk_node_id ELSE na_own.taluk_node_id END AS new_taluk_id
FROM tmp_shops_to_fix f
LEFT JOIN tmp_node_ancestors na_own ON na_own.firka_node_id = f.own_firka_id
LEFT JOIN tmp_tp_single_firka tsf ON tsf.tp_id = f.tp_id
LEFT JOIN tmp_node_ancestors_2 na_tp ON na_tp.firka_node_id = tsf.firka_node_id
LEFT JOIN tmp_tp_multi_state tms ON tms.tp_id = f.tp_id
LEFT JOIN tmp_tp_no_firka_state nf ON nf.tp_id = f.tp_id;

-- Keep only rows that actually represent a real change, comparing against
-- the shop's CURRENT raw column values (so a shop whose state_id is already
-- correct — whether resolved from its own firka or just already valid — is
-- never touched, and only genuine corrections/fills go to the UPDATE below).
DROP TEMPORARY TABLE IF EXISTS tmp_shop_fix_changed;
CREATE TEMPORARY TABLE tmp_shop_fix_changed AS
SELECT v.*
FROM tmp_shop_fix_values v
JOIN shop s ON s.temp_id COLLATE utf8mb4_general_ci = v.temp_id COLLATE utf8mb4_general_ci
WHERE (v.new_state_id IS NOT NULL AND (s.state_id <> v.new_state_id OR s.state_id NOT REGEXP '^[0-9]+$'))
   OR (v.new_firka_id IS NOT NULL AND (s.firka_id <> v.new_firka_id OR s.firka_id NOT REGEXP '^[0-9]+$'))
   OR (v.new_district_id IS NOT NULL AND (s.district_id <> v.new_district_id OR s.district_id NOT REGEXP '^[0-9]+$'))
   OR (v.new_taluk_id IS NOT NULL AND (s.taluk_id <> v.new_taluk_id OR s.taluk_id NOT REGEXP '^[0-9]+$'));

CREATE INDEX idx_tmp_shop_fix_changed ON tmp_shop_fix_changed (temp_id);

-- ----------------------------------------------------------------------------
-- 5) PREVIEW — inspect before applying.
-- ----------------------------------------------------------------------------
-- How many shops get a full firka+district+taluk+state correction vs only
-- state (multi-firka TP, or a shop whose own state_id was garbage but whose
-- firka is fine) vs nothing resolvable at all (TP itself has no usable
-- location either — these are left completely untouched).
SELECT
    CASE
        WHEN new_firka_id IS NOT NULL THEN 'firka+district+taluk+state'
        WHEN new_state_id IS NOT NULL THEN 'state only'
        ELSE 'unresolved - left untouched'
    END AS resolution,
    COUNT(*) AS shop_count
FROM tmp_shop_fix_changed
GROUP BY 1;

-- Uncomment to see the full list of affected shops:
-- SELECT * FROM tmp_shop_fix_changed;

-- ----------------------------------------------------------------------------
-- 6) APPLY — only touches shops that actually need a correction.
-- ----------------------------------------------------------------------------
UPDATE shop s
JOIN tmp_shop_fix_changed v ON v.temp_id COLLATE utf8mb4_general_ci = s.temp_id COLLATE utf8mb4_general_ci
SET
    s.state_id    = COALESCE(v.new_state_id, s.state_id),
    s.district_id = COALESCE(v.new_district_id, s.district_id),
    s.taluk_id    = COALESCE(v.new_taluk_id, s.taluk_id),
    s.firka_id    = COALESCE(v.new_firka_id, s.firka_id)
WHERE v.new_state_id IS NOT NULL;

-- ----------------------------------------------------------------------------
-- 7) Verify, then COMMIT (or ROLLBACK if anything looks wrong).
-- ----------------------------------------------------------------------------
SELECT ROW_COUNT() AS shops_updated;

-- Review the SELECTs above. If they match your expectations:
COMMIT;
-- Otherwise, run ROLLBACK; instead of COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_node_ancestors_raw;
DROP TEMPORARY TABLE IF EXISTS tmp_node_ancestors;
DROP TEMPORARY TABLE IF EXISTS tmp_node_ancestors_2;
DROP TEMPORARY TABLE IF EXISTS tmp_tp_single_firka;
DROP TEMPORARY TABLE IF EXISTS tmp_tp_multi_state;
DROP TEMPORARY TABLE IF EXISTS tmp_tp_no_firka_state;
DROP TEMPORARY TABLE IF EXISTS tmp_shop_numeric_ids;
DROP TEMPORARY TABLE IF EXISTS tmp_shops_to_fix;
DROP TEMPORARY TABLE IF EXISTS tmp_shop_fix_values;
DROP TEMPORARY TABLE IF EXISTS tmp_shop_fix_changed;
