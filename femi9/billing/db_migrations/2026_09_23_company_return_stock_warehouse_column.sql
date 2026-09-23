-- Adds optional physical-warehouse tagging to company_return_stock, so
-- deleting a return can restore stock to the same warehouse bucket it was
-- deducted from. Nullable, additive — no backfill, no existing row is
-- touched (pre-existing rows keep warehouse_id NULL, matching where their
-- deduction actually landed — stock_return_update.php always wrote to the
-- warehouse_id IS NULL row before this column existed).

ALTER TABLE company_return_stock
  ADD COLUMN warehouse_id INT NULL AFTER godownid;
