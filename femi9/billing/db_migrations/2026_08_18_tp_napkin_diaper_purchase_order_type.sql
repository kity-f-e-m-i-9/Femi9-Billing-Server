-- Phase 1 of the Napkin/Diaper separation project — every TP Purchase Order
-- now belongs to exactly one product type, chosen at creation time (the
-- product picker only offers that type's products; the server re-validates
-- every submitted line matches before accepting the order).
--
-- No backfill needed here: existing POs stay NULL-safe under the app's own
-- self-migrating column-add (which sets a DEFAULT), but since every legacy
-- PO predates this feature, application code must treat an unset/NULL value
-- defensively — see the per-row "infer from its own items" backfill applied
-- in the same pass that adds product_type to tp_invoices (Phase 2) and
-- tp_advance_payments (Phase 3), which is data-driven, not a blanket value,
-- and is therefore run from PHP (shared/TpProductType.php's classification
-- logic), not plain SQL.

ALTER TABLE tp_purchase_orders
  ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id;
