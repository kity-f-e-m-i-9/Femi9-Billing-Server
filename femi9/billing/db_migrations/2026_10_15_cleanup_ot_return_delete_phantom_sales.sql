-- ONE-TIME CLEANUP. Run this AFTER:
--   1. Applying 2026_10_14_stock_ledger_ot_reverse_undo.sql (adds the
--      'ot_reverse_undo' enum value this script uses)
--   2. Uploading StockService.php / ot-return-delete.php /
--      DatewiseStockReport.php to the server
--
-- Before that fix, deleting an OT Channel return (ot-return-delete.php)
-- wrote a plain 'ot_deduct' ledger entry to undo it — indistinguishable
-- from a real new sale. That inflated the Sales Qty report and the
-- stock.sales_qty running snapshot for every return anyone ever deleted
-- (found 3 such entries as of 2026-10-14, going back to 2026-09-26 — this
-- bug predates today). Stock quantity itself (stock.closing_qty) was NOT
-- affected — only the sales-count bookkeeping.
--
-- This script:
--   1. Reclassifies those old phantom 'ot_deduct' rows to 'ot_reverse_undo'
--      (same rows, just correctly labelled — nothing deleted, ledger
--      history stays intact), identified by ref_id being a bare row id
--      (digits only) rather than a real tempid (which always contains
--      "RTST" — see ot-sale-add.php's generateTempId()).
--   2. Walks the now-reclassified rows and removes the qty they wrongly
--      added to stock.sales_qty, floored at 0.
--
-- SAFE TO RE-RUN: the UPDATE in step 1 only touches rows still tagged
-- 'ot_deduct' with a bare-digit ref_id, so running this twice is a no-op
-- the second time (step 1 finds nothing left to reclassify, so step 2
-- also updates nothing).

-- Preview first — review this list before running the UPDATEs below.
SELECT id, product_id, user_id, warehouse_id, qty, ref_id, created_at
FROM stock_ledger
WHERE action = 'ot_deduct' AND ref_type = 'ot_sale' AND ref_id NOT LIKE '%RTST%';

-- Step 1: reclassify.
UPDATE stock_ledger
SET action = 'ot_reverse_undo'
WHERE action = 'ot_deduct' AND ref_type = 'ot_sale' AND ref_id NOT LIKE '%RTST%';

-- Step 2: correct the stock.sales_qty snapshot per (product, user, warehouse).
-- NULL-safe join on warehouse_id via <=> since some older rows have no
-- warehouse tracked.
UPDATE stock s
JOIN (
    SELECT product_id, user_id, warehouse_id, SUM(qty) AS over_counted
    FROM stock_ledger
    WHERE action = 'ot_reverse_undo' AND ref_type = 'ot_sale' AND ref_id NOT LIKE '%RTST%'
    GROUP BY product_id, user_id, warehouse_id
) x ON x.product_id = s.product_id
   AND x.user_id = s.user_id
   AND x.warehouse_id <=> s.warehouse_id
SET s.sales_qty = GREATEST(0, s.sales_qty - x.over_counted);

-- Verify: should return 0 rows now.
SELECT id, product_id, user_id, warehouse_id, qty, ref_id, created_at
FROM stock_ledger
WHERE action = 'ot_deduct' AND ref_type = 'ot_sale' AND ref_id NOT LIKE '%RTST%';
