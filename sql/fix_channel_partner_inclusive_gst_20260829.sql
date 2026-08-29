-- Fix: channel-partner login's invoice item-add/update actions
-- (shop-invoice-action.php, shop-invoice-action2.php,
--  customer-invoice-action.php, customer-invoice-action2.php)
-- never fetched products.gst_type and always added GST on top of subtotal,
-- even for inclusive-tax products (where GST should be carved OUT of the
-- entered price instead). Code fixed separately; this backfills the rows
-- already written wrong.
--
-- Detection: for an inclusive-tax product, a correctly-computed row has
-- total == subtotal (tax carved out, not added on top). A row where
-- total > subtotal (i.e. still has gst added on top) for an inclusive
-- product is a row written by the buggy code path.
--
-- Review the SELECT preview below before running the UPDATEs.

-- ============ shop invoices (channel_partner -> shop), user_invoice_items ============

-- Preview:
SELECT
    uii.id, uii.inv_id, uii.pr_id, p.gst_type AS product_gst_type,
    uii.qty, uii.amount, uii.discount_amount, uii.gst_percentage,
    uii.subtotal AS current_subtotal, uii.gstamount_total AS current_gstamount_total,
    uii.total AS current_total,
    (uii.subtotal - (uii.subtotal * 100 / (100 + uii.gst_percentage))) AS correct_gstamount_total,
    uii.subtotal AS correct_total
FROM user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
WHERE uii.from_user_type = 'channel_partner'
  AND p.gst_type = 'inclusive'
  AND uii.gst_percentage > 0
  AND uii.total > uii.subtotal;   -- still has GST added on top => wrong

-- Apply:
UPDATE user_invoice_items uii
JOIN products p ON p.id = uii.pr_id
SET
    uii.gstamount_total = uii.subtotal - (uii.subtotal * 100 / (100 + uii.gst_percentage)),
    uii.total            = uii.subtotal
WHERE uii.from_user_type = 'channel_partner'
  AND p.gst_type = 'inclusive'
  AND uii.gst_percentage > 0
  AND uii.total > uii.subtotal;

-- ============ customer invoices (channel_partner -> customer), invoice_items ============

-- Preview:
SELECT
    ii.id, ii.inv_id, ii.pr_id, p.gst_type AS product_gst_type,
    ii.qty, ii.amount, ii.discount_amount, ii.gst_percentage,
    ii.subtotal AS current_subtotal, ii.gstamount_total AS current_gstamount_total,
    ii.total AS current_total,
    (ii.subtotal - (ii.subtotal * 100 / (100 + ii.gst_percentage))) AS correct_gstamount_total,
    ii.subtotal AS correct_total
FROM invoice_items ii
JOIN products p ON p.id = ii.pr_id
WHERE ii.user_type = 'channel_partner'
  AND p.gst_type = 'inclusive'
  AND ii.gst_percentage > 0
  AND ii.total > ii.subtotal;

-- Apply:
UPDATE invoice_items ii
JOIN products p ON p.id = ii.pr_id
SET
    ii.gstamount_total = ii.subtotal - (ii.subtotal * 100 / (100 + ii.gst_percentage)),
    ii.total            = ii.subtotal
WHERE ii.user_type = 'channel_partner'
  AND p.gst_type = 'inclusive'
  AND ii.gst_percentage > 0
  AND ii.total > ii.subtotal;

-- Note: cnote_action.php (credit notes) already branches correctly on
-- gst_type === 'inclusive' — nothing to fix/backfill there.
-- No "tp_invoices"-equivalent edit-in-place endpoint exists in the
-- channel-partner login with this bug.
