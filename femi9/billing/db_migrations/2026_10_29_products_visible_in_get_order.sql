-- Marketing Staff's "Get Order" (add_order.php) used to reuse the same
-- Active/Inactive toggle (deleted_at) every other order flow (TP, CP, SS ->
-- company) uses. That's wrong for Get Order specifically: Get Order doesn't
-- draw from company stock at all, it's fulfilled from a TP's existing stock
-- or sourced elsewhere, so a product the company marked Inactive (stopped
-- supplying/no longer orderable from company) can still be perfectly valid
-- to request via Get Order. This gives Get Order its own independent
-- visibility flag, decoupled from the Active/Inactive status.
--
-- Seeded to match current Active/Inactive status at migration time, so
-- behaviour doesn't change until an admin explicitly flips this per product.

ALTER TABLE products
  ADD COLUMN visible_in_get_order TINYINT(1) NOT NULL DEFAULT 1 AFTER deleted_at;

UPDATE products SET visible_in_get_order = IF(deleted_at IS NULL, 1, 0);
