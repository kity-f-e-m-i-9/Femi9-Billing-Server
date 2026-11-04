-- New, independent visibility flag for a product on a TP's or CP's own
-- "place a new PO" screen (territory-partner/add-purchase-order.php's
-- Company/Super Stockist product list, and channel-partner/add-purchase-
-- order.php's Company product list) -- separate from the main Active/
-- Inactive status (deleted_at) and from visible_in_get_order.
--
-- Rationale: the company may want to stop a TP/CP from ordering FRESH stock
-- of a product (e.g. discontinuing it for that channel) while still keeping
-- it Active everywhere else (OT Channel sales, stock transfers, etc). Same
-- convention as visible_in_get_order -- and symmetric with it: existing
-- stock a TP/CP/SS already holds (territory-partner/get-cp-products.php,
-- company/get-tp-location-products.php, super-stockist/get-ss-tp-
-- products.php) is deliberately NEVER gated by either flag, only by
-- closing_qty > 0, so turning this off never blocks moving stock a partner
-- already has.
--
-- Seeded to match current Active/Inactive status at migration time, so
-- behaviour doesn't change until an admin explicitly flips this per product.

ALTER TABLE products
  ADD COLUMN visible_to_tp_cp TINYINT(1) NOT NULL DEFAULT 1 AFTER visible_in_get_order;

UPDATE products SET visible_to_tp_cp = IF(deleted_at IS NULL, 1, 0);
