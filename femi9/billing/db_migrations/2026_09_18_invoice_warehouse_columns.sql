-- Adds optional physical-warehouse tagging to every invoice/sale header
-- table, per docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
-- Nullable, additive — no backfill, no existing row is touched.

ALTER TABLE tp_invoices
  ADD COLUMN warehouse_id INT NULL AFTER source_godown_id;

ALTER TABLE ot_sales
  ADD COLUMN warehouse_id INT NULL AFTER godownid;

ALTER TABLE invoice
  ADD COLUMN warehouse_id INT NULL AFTER user_id;

ALTER TABLE user_invoice
  ADD COLUMN warehouse_id INT NULL AFTER from_user_id;
