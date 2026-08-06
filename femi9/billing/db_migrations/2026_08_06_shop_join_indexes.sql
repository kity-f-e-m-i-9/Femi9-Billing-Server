-- Speeds up mis-report.php's district/state revenue queries, which join
-- shop on temp_id and district_id with no supporting index — on production
-- data volumes this made the report take 10+ minutes and blocked other
-- queries queued behind it.
-- NOT safe to re-run as-is (CREATE INDEX errors if it already exists) —
-- check SHOW INDEX FROM shop first if applying by hand.
CREATE INDEX idx_shop_temp_id ON shop(temp_id);
CREATE INDEX idx_shop_district_id ON shop(district_id);
