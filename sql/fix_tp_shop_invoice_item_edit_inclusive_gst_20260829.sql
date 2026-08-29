-- Fix: shop-invoice-item-edit.php (TP login, DM-assigned-order invoice edit-in-place)
-- always computed GST as exclusive, even for inclusive-tax products, before
-- the code fix landed. This backfills user_invoice_items rows that were
-- touched by that specific edit path for inclusive-tax products, recomputing
-- gstamount_total/total using the inclusive convention (tax carved out of
-- subtotal, not added on top).
--
-- Scope: only rows whose invoice item was actually edited via
-- shop-invoice-item-edit.php (identified through shop_invoice_change_log,
-- change_type='qty_changed', which that endpoint uses for both qty and
-- price/discount edits) AND whose product is gst_type='inclusive'.
--
-- Review the SELECT preview below before running the UPDATE.

-- Preview affected rows:
SELECT DISTINCT
    uii.id, uii.inv_id, uii.pr_id, p.gst_type AS product_gst_type,
    uii.qty, uii.amount, uii.discount_amount, uii.gst_percentage,
    uii.subtotal AS current_subtotal, uii.gstamount_total AS current_gstamount_total,
    uii.total AS current_total,
    (uii.subtotal - (uii.subtotal * 100 / (100 + uii.gst_percentage))) AS correct_gstamount_total,
    uii.subtotal AS correct_total
FROM user_invoice_items uii
JOIN shop_invoice_change_log l
    ON l.inv_id = uii.inv_id AND l.pr_id = uii.pr_id AND l.change_type = 'qty_changed'
JOIN products p ON p.id = uii.pr_id
WHERE p.gst_type = 'inclusive'
  AND uii.gst_percentage > 0;

-- Apply fix:
UPDATE user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
SET
    uii.gstamount_total = uii.subtotal - (uii.subtotal * 100 / (100 + uii.gst_percentage)),
    uii.total            = uii.subtotal
WHERE p.gst_type = 'inclusive'
  AND uii.gst_percentage > 0
  AND EXISTS (
      SELECT 1 FROM shop_invoice_change_log l
      WHERE l.inv_id = uii.inv_id AND l.pr_id = uii.pr_id AND l.change_type = 'qty_changed'
  );

-- Note on scope, per user's question about "tp invoices table, shop invoices
-- table and customer invoices table":
--   - "Shop invoices" (user_invoice / user_invoice_items) via
--     shop-invoice-item-edit.php: the ONLY path with this bug — fixed above.
--   - "TP invoices" (tp_invoices) and "Customer invoices" (invoice /
--     invoice_items) in the territory-partner login: no equivalent
--     edit-in-place endpoint exists for them, and
--     customer-invoice-action.php / customer-invoice-action2.php already
--     branch correctly on gst_type_item === 'inclusive'. Nothing to backfill
--     there.
