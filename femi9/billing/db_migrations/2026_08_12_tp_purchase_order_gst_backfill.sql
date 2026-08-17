-- Mirrors 2026_08_12_tp_invoice_approver_gst_backfill.sql's rule for
-- tp_purchase_orders: any PO containing at least one GST product
-- (products.gst > 0) is forced to approver_type='company', overriding
-- whatever it's currently set to — a GST PO must never sit under an SS's
-- approval queue, regardless of which approver the TP originally selected.
--
-- tp_advance_payments is deliberately left untouched here — it's a payment
-- ledger with no product/line-item link, so "GST-ness" doesn't apply to it
-- directly (see tp_purchase_orders/tp_invoices, which do carry line items).
--
-- Idempotent — safe to re-run: always re-asserts 'company' for qualifying
-- rows, never touches non-GST rows.

UPDATE tp_purchase_orders po
SET po.approver_type = 'company', po.approver_ss_id = NULL
WHERE EXISTS (
    SELECT 1 FROM tp_purchase_order_items poi
    JOIN products p ON p.id = poi.product_id
    WHERE poi.po_id = po.id AND p.gst > 0
);
