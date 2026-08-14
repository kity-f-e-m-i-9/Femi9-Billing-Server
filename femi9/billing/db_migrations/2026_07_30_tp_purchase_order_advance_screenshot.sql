-- When a TP's purchase order total exceeds their available advance balance,
-- they must upload proof of payment for the excess amount before the order
-- can be submitted. Tracks that excess and where the compressed screenshot
-- is stored on disk.
ALTER TABLE `tp_purchase_orders`
  ADD COLUMN `excess_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `status`,
  ADD COLUMN `payment_screenshot` VARCHAR(255) NULL DEFAULT NULL AFTER `excess_amount`;
