-- user_return_stock_items.id lost its AUTO_INCREMENT attribute at some point
-- (likely a prior schema edit/import that redefined the column). Every
-- insert into this table across every module (company, super-stockist,
-- stockist, distributor, territory-partner, ...) omits `id`, so once the
-- attribute was gone MySQL/MariaDB silently inserted 0 for it. The first
-- such insert created a stray id=0 row; every insert after that hit
-- "Duplicate entry '0' for key 'PRIMARY'" and the return/credit-note flow
-- failed with a 500 everywhere it's used.
--
-- Not written as a blind idempotent script — running this twice on an
-- already-fixed table is a no-op (the UPDATE matches 0 rows, the ALTER is
-- harmless against an already-AUTO_INCREMENT column), but do not run it
-- against a table where `id` was intentionally changed after this date.

-- 1. Move the stray id=0 row to an unused id.
UPDATE user_return_stock_items
SET id = (SELECT * FROM (SELECT MAX(id) + 1 FROM user_return_stock_items) t)
WHERE id = 0;

-- 2. Restore AUTO_INCREMENT on the primary key.
ALTER TABLE user_return_stock_items MODIFY id INT(11) NOT NULL AUTO_INCREMENT;
