-- One-time data fix (2026-12-01): corrects shop.district_id for every shop
-- owned by a TP who is currently assigned to exactly ONE district. For such
-- a TP, there is zero ambiguity about which district their shops belong to
-- (shop-invoice-action.php itself never offers them a different district to
-- pick on an invoice), so any shop record whose district_id doesn't match
-- that TP's one true district is stale data left over from before a Firka
-- reassignment (or a shop that never had the right district recorded).
-- Confirmed on local DB: 5,896 shop rows affected, 4,476 historical
-- invoices (~Rs.54.45 lakh) were being misattributed to the WRONG DM's
-- dashboard because of this. Multi-district TPs' shops are NOT touched —
-- for them, which district a given shop belongs to is genuinely ambiguous
-- and needs a human decision, not a blanket SQL fix.
--
-- Run this ONCE on the live DB. It creates a backup table first and only
-- proceeds with the UPDATE afterward, so the original values are always
-- recoverable from shop_district_id_fix_backup_20261201 if anything looks
-- wrong afterward.

DROP TABLE IF EXISTS shop_district_id_fix_backup_20261201;

CREATE TABLE shop_district_id_fix_backup_20261201 AS
SELECT s.id AS shop_id, s.onboard_userID AS tp_id, s.district_id AS old_district_id, m.home_district_id AS new_district_id
FROM shop s
JOIN (
    SELECT tpl.territory_partner_id AS tp_id, MIN(d.id) AS home_district_id
    FROM territory_partner_locations tpl
    JOIN territory_partners tp ON tp.id = tpl.territory_partner_id AND tp.deleted_at IS NULL
    JOIN partner_location_nodes f  ON f.id  = tpl.location_id
    JOIN partner_location_nodes t  ON t.id  = f.parent_id
    JOIN partner_location_nodes dv ON dv.id = t.parent_id
    JOIN partner_location_nodes d  ON d.id  = dv.parent_id
    GROUP BY tpl.territory_partner_id
    HAVING COUNT(DISTINCT d.id) = 1
) m ON m.tp_id = s.onboard_userID
WHERE s.onboard_userTYPE = 'territory_partner'
  AND s.deleted_at IS NULL
  AND s.district_id != m.home_district_id;

-- Sanity check before applying -- compare this count against the backup
-- table's row count; they must match.
SELECT COUNT(*) AS rows_to_be_fixed FROM shop_district_id_fix_backup_20261201;

UPDATE shop s
JOIN (
    SELECT tpl.territory_partner_id AS tp_id, MIN(d.id) AS home_district_id
    FROM territory_partner_locations tpl
    JOIN territory_partners tp ON tp.id = tpl.territory_partner_id AND tp.deleted_at IS NULL
    JOIN partner_location_nodes f  ON f.id  = tpl.location_id
    JOIN partner_location_nodes t  ON t.id  = f.parent_id
    JOIN partner_location_nodes dv ON dv.id = t.parent_id
    JOIN partner_location_nodes d  ON d.id  = dv.parent_id
    GROUP BY tpl.territory_partner_id
    HAVING COUNT(DISTINCT d.id) = 1
) m ON m.tp_id = s.onboard_userID
SET s.district_id = m.home_district_id
WHERE s.onboard_userTYPE = 'territory_partner'
  AND s.deleted_at IS NULL
  AND s.district_id != m.home_district_id;

-- After running, ROW_COUNT() from the UPDATE above should equal the
-- rows_to_be_fixed count from the SELECT above (both were 5,896 on local;
-- adding the tp.deleted_at filter above made no difference to this count —
-- the one deleted TP that was otherwise wrongly counted as "single-district"
-- (id 52, P PRIYA) owns zero shops either way).
