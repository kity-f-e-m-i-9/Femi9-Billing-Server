-- tp-invoice-action.php (company "Add TP Invoice") previously recorded
-- company-godown-sourced TP invoices as sent_qty on the `stock` table,
-- matching internal transfers / demo-free. The company wants these counted
-- as sales instead, consistent with overstock_datewise.php's SALES-3 and
-- with how other channel partners' company-issued invoices are already
-- counted. The application code (tp-invoice-action.php, delete-tp-invoice.php,
-- edit-tp-invoice-action.php) now writes to sales_qty going forward — this
-- migration moves the historical totals for existing tp_invoices rows out of
-- sent_qty and into sales_qty so the all-time "Overall Stock" summary
-- (company/overall-stock.php) matches without needing to be re-derived.
--
-- Only tp_invoices sourced directly from a company godown (source_godown_id)
-- touched `stock` at all — channel-partner-sourced TP invoices never did.
--
-- Idempotent guard: skips products/godowns already reconciled (detected via
-- a marker row in stock_ledger so re-running this file is a no-op).

DROP PROCEDURE IF EXISTS _backfill_tp_invoice_sales_qty;
DELIMITER //
CREATE PROCEDURE _backfill_tp_invoice_sales_qty()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM stock_ledger WHERE ref_type='adjustment' AND ref_id='backfill_tp_invoice_sales_qty_2026_07_14' LIMIT 1
  ) THEN
    -- Move each (godown, product) total from sent_qty to sales_qty.
    UPDATE stock s
    JOIN (
      SELECT ti.source_godown_id AS godown_id, tii.product_id, SUM(tii.quantity) AS qty
      FROM tp_invoices ti
      JOIN tp_invoice_items tii ON tii.tp_invoice_id = ti.id
      WHERE ti.source_godown_id IS NOT NULL AND ti.source_godown_id > 0
      GROUP BY ti.source_godown_id, tii.product_id
    ) t ON s.user_type='company' AND s.user_id=CAST(t.godown_id AS CHAR) AND s.product_id=t.product_id
    SET s.sent_qty = GREATEST(0, s.sent_qty - t.qty),
        s.sales_qty = s.sales_qty + t.qty;

    -- Marker row so this migration is safe to re-run.
    INSERT INTO stock_ledger
      (product_id,user_type,user_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by)
    VALUES
      (0,'company','0','transfer_out',0,0,0,'adjustment','backfill_tp_invoice_sales_qty_2026_07_14','one-time sent_qty->sales_qty reclass for existing tp_invoices','migration');
  END IF;
END //
DELIMITER ;
CALL _backfill_tp_invoice_sales_qty();
DROP PROCEDURE IF EXISTS _backfill_tp_invoice_sales_qty;
