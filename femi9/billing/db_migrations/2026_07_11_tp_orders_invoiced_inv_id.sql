-- Tracks which invoice (user_invoice.inv_id) a "Got Order" field visit was
-- turned into, via territory-partner/order-to-invoice.php. NULL = not yet
-- invoiced; the "Invoice" button on manage-orders.php checks this to decide
-- between "Create Invoice" (first click) and "Continue Invoice" (re-click
-- after the TP left the invoice mid-way, e.g. to add receipt details).
--
-- Idempotent — safe to run more than once.

DROP PROCEDURE IF EXISTS _add_tp_orders_invoiced_inv_id;
DELIMITER //
CREATE PROCEDURE _add_tp_orders_invoiced_inv_id()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tp_orders'
      AND COLUMN_NAME  = 'invoiced_inv_id'
  ) THEN
    ALTER TABLE `tp_orders` ADD COLUMN `invoiced_inv_id` VARCHAR(60) NULL DEFAULT NULL AFTER `qty`;
    ALTER TABLE `tp_orders` ADD KEY `idx_invoiced_inv_id` (`invoiced_inv_id`);
  END IF;
END //
DELIMITER ;
CALL _add_tp_orders_invoiced_inv_id();
DROP PROCEDURE IF EXISTS _add_tp_orders_invoiced_inv_id;
