-- Phase 2 of the Napkin/Diaper separation project — every TP invoice now
-- carries the same product_type as the purchase order it was billed from
-- (tp_purchase_orders.product_type, Phase 1); a direct/manual invoice with
-- no originating PO gets the type chosen explicitly at creation time.
--
-- This is what Phase 3's advance-payment deduction/restore will key off of
-- to draw from the correct wallet — see shared/TpAdvanceService.php.

ALTER TABLE tp_invoices
  ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id;
