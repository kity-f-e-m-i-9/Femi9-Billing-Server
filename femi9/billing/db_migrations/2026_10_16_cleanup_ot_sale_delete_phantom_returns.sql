-- ONE-TIME CLEANUP. Run this AFTER:
--   1. Applying 2026_10_15_stock_ledger_ot_deduct_undo.sql (adds the
--      'ot_deduct_undo' enum value this script uses)
--   2. Uploading StockService.php / ot-sale-delete.php /
--      DatewiseStockReport.php to the server
--
-- Before that fix, deleting an OT sale entirely (ot-sale-delete.php) wrote
-- a plain 'ot_reverse' ledger entry to undo it — indistinguishable from a
-- genuine customer return. Since Datewise/Current-Stock reporting shows
-- Sales Qty GROSS (deduct/ot_deduct, not netted by returns — see
-- DatewiseStockReport.php's computeStockMovement()), a deleted sale's
-- original 'ot_deduct' kept counting as a sale forever, while the delete
-- only inflated "Return Qty" alongside it, next to a Sales Qty that should
-- have shrunk instead (found 11 such entries as of 2026-10-16, going back
-- to 2026-08-17 — this bug predates today, affects multiple products).
--
-- stock.closing_qty / stock.sales_qty were NOT affected by this bug —
-- otReverse() and otDeductUndo() both apply the exact same snapshot update
-- (closing_qty += qty, sales_qty -= qty, floored at 0; see StockService.php).
-- Only the ledger ACTION LABEL was wrong, which is what the Datewise report
-- reads to decide "was this a real sale that got deleted (remove from Sales
-- Qty entirely) or a genuine customer return (show in Return Qty, keep in
-- Sales Qty)?" — so this cleanup only needs to relabel, nothing to
-- recompute on the stock table itself.
--
-- This script reclassifies those old phantom 'ot_reverse' rows to
-- 'ot_deduct_undo' (same rows, just correctly labelled — nothing deleted,
-- ledger history stays intact), identified by ref_id being a bare row id
-- (digits only) rather than a real tempid (which always contains "RTST" —
-- see ot-sale-add.php's generateTempId()). This is the exact inverse of
-- 2026_10_15_cleanup_ot_return_delete_phantom_sales.sql's own ref_id
-- pattern match, for the mirror-image bug.
--
-- SAFE TO RE-RUN: the UPDATE only touches rows still tagged 'ot_reverse'
-- with a bare-digit ref_id, so running this twice is a no-op the second
-- time (nothing left to reclassify).

-- Preview first — review this list before running the UPDATE below.
SELECT id, product_id, user_id, warehouse_id, qty, ref_id, created_at
FROM stock_ledger
WHERE action = 'ot_reverse' AND ref_type = 'ot_sale' AND ref_id NOT LIKE '%RTST%';

-- Reclassify.
UPDATE stock_ledger
SET action = 'ot_deduct_undo'
WHERE action = 'ot_reverse' AND ref_type = 'ot_sale' AND ref_id NOT LIKE '%RTST%';

-- Verify: should return 0 rows now.
SELECT id, product_id, user_id, warehouse_id, qty, ref_id, created_at
FROM stock_ledger
WHERE action = 'ot_reverse' AND ref_type = 'ot_sale' AND ref_id NOT LIKE '%RTST%';
