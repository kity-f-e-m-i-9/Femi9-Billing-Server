-- shop.temp_id has no index of its own (only as the 2nd column of
-- idx_shop_state_temp, which MySQL can't use for a lookup by temp_id
-- alone — leftmost-prefix rule). Several pages join user_invoice.to_user_id
-- to shop.temp_id (e.g. marketing/dashboard.php's Shop Invoices /
-- "Your TPs' Invoice Amount" query) — without this index that join does a
-- full table scan of `shop` (confirmed via EXPLAIN: type=ALL, ~32k rows)
-- for every invoice row, which was the single largest contributor to
-- marketing/dashboard.php taking 10+ seconds to load for an ASM/SM with a
-- sizeable team. Adding the index brought that same query from ~11.6s to
-- well under 1s.
--
-- Safe to run on live: CREATE INDEX only adds a lookup structure, it does
-- not change any data or existing behavior.
SET @idx_exists := (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE table_schema = DATABASE() AND table_name = 'shop' AND index_name = 'idx_shop_temp_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_shop_temp_id ON shop (temp_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
