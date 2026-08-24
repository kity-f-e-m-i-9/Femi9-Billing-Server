-- Dispatch slip's existing packs_per_carton does exact carton math
-- (intdiv/modulo -> "N ctn + M packs"). This is a separate, simpler
-- per-product setting: once an order LINE's quantity passes this count,
-- the whole line is flagged as a full box on the dispatch slip — a plain
-- threshold, not an exact carton count.

ALTER TABLE products
  ADD COLUMN packs_per_cover INT UNSIGNED NULL AFTER packs_per_carton;
