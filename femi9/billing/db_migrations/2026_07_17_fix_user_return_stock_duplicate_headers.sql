-- user_return_stock had no UNIQUE constraint on `returnid`, even though every
-- return-creation flow across every module (company, super-stockist,
-- stockist, distributor, territory-partner, ...) inserts the header row via
-- "INSERT ... ON DUPLICATE KEY UPDATE" keyed on returnid. Without a real
-- unique key, that ON DUPLICATE KEY UPDATE never triggered — every time a
-- product was added to a credit note, a fresh duplicate header row got
-- appended instead of reusing the existing one. A CN with 9 products ended
-- up with 9 identical header rows, all shown as separate entries on
-- Manage Return (Credit Note).
--
-- NOT idempotent — running this twice is harmless (the DELETE matches 0 rows,
-- the ADD UNIQUE KEY errors if already present) but only needs to run once.

-- 1. Collapse duplicate header rows, keeping the earliest (lowest id) per
--    returnid — verified identical in content across every duplicate group.
DELETE t1 FROM user_return_stock t1
INNER JOIN user_return_stock t2
WHERE t1.returnid = t2.returnid AND t1.id > t2.id;

-- 2. Prevent this from happening again.
ALTER TABLE user_return_stock ADD UNIQUE KEY uk_returnid (returnid);

-- 3. user_return_stock.id had the SAME missing-AUTO_INCREMENT bug as
--    user_return_stock_items (see 2026_07_16's migration) — every insert
--    omits `id`, so without AUTO_INCREMENT it silently tried id=0 every
--    time. Once a row with id=0 existed, every subsequent first-insert-of-a-
--    new-return collided on the PRIMARY KEY and "ON DUPLICATE KEY UPDATE"
--    silently overwrote that unrelated row's from_usertype/from_userid/
--    to_usertype/to_userid with the new request's values — corrupting a
--    real accepted return in place while leaving its returnid/invnumber/
--    totals untouched. Same fix: move the stray id=0 row to a free id, then
--    restore AUTO_INCREMENT.
UPDATE user_return_stock
SET id = (SELECT * FROM (SELECT MAX(id) + 1 FROM user_return_stock) t)
WHERE id = 0;

ALTER TABLE user_return_stock MODIFY id INT(11) NOT NULL AUTO_INCREMENT;
