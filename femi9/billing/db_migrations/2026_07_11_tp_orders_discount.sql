-- Captures per-line discount at field-order time (add-order.php's Got Order
-- product adder), so the invoice later created from that visit
-- (order-to-invoice.php) can carry the exact same Disc(%)/Disc(Rs.) the TP
-- entered instead of always billing at full price.
--
-- Idempotent — safe to run more than once.

DROP PROCEDURE IF EXISTS _add_tp_orders_discount;
DELIMITER //
CREATE PROCEDURE _add_tp_orders_discount()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tp_orders'
      AND COLUMN_NAME  = 'discount_percentage'
  ) THEN
    ALTER TABLE `tp_orders` ADD COLUMN `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `qty`;
    ALTER TABLE `tp_orders` ADD COLUMN `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `discount_percentage`;
  END IF;
END //
DELIMITER ;
CALL _add_tp_orders_discount();
DROP PROCEDURE IF EXISTS _add_tp_orders_discount;
