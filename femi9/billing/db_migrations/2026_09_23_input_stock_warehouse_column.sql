-- Adds optional physical-warehouse tagging to input_stock, so deleting an
-- Input Stock entry can restore/reverse against the same warehouse bucket it
-- was credited to. Nullable, additive — no backfill, no existing row is
-- touched (pre-existing rows keep warehouse_id NULL, matching where their
-- credit actually landed — every existing input-stock stock_ledger entry
-- confirmed to be warehouse_id IS NULL as of this migration).

ALTER TABLE input_stock
  ADD COLUMN warehouse_id INT NULL AFTER godownid;
