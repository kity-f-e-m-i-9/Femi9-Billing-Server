-- Lets company cancel a TP purchase order (still 'waiting', not yet
-- invoiced) from tp-today-orders.php. The cancelled status/reason needs to
-- be visible back on the TP's own manage-purchase-orders.php page.
ALTER TABLE tp_purchase_orders
  MODIFY status ENUM('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting',
  ADD COLUMN cancelled_at TIMESTAMP NULL AFTER tp_invoice_id,
  ADD COLUMN cancelled_by VARCHAR(100) NULL AFTER cancelled_at,
  ADD COLUMN cancel_reason VARCHAR(500) NULL AFTER cancelled_by;
