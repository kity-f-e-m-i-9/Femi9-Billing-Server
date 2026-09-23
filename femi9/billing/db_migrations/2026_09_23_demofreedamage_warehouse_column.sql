-- Adds optional physical-warehouse tagging to demofreedamage, so deleting a
-- Demo/Free/Damage record can restore stock to the same warehouse bucket it
-- was deducted from. Nullable, additive — no backfill, no existing row is
-- touched (pre-existing rows keep warehouse_id NULL, meaning "unassigned",
-- matching where their deduction actually landed before this column existed).

ALTER TABLE demofreedamage
  ADD COLUMN warehouse_id INT NULL AFTER userid;
