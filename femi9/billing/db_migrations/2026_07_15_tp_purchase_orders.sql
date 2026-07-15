-- Territory Partner-initiated "Purchase Order" requests, shown to company on
-- tp-today-orders.php and converted into a real tp_invoices record (via the
-- existing add-tp-invoice.php flow) once billed. Separate from tp_invoices,
-- which is the priced/stock-moving document — this table only tracks what
-- the TP asked for and whether it has been actioned yet.
--
-- Idempotent — safe to run more than once.

CREATE TABLE IF NOT EXISTS `tp_purchase_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `territory_partner_id` INT UNSIGNED NOT NULL,
  `order_date` DATE NOT NULL,
  `status` ENUM('waiting','completed') NOT NULL DEFAULT 'waiting',
  `tp_invoice_id` INT UNSIGNED NULL DEFAULT NULL,
  `notes` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_tppo_tp_date` (`territory_partner_id`, `order_date`),
  KEY `idx_tppo_status_date` (`status`, `order_date`),
  KEY `idx_tppo_invoice` (`tp_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_purchase_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT UNSIGNED NOT NULL,
  `product_id` INT NOT NULL,
  `qty` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  KEY `idx_tppoi_po` (`po_id`),
  KEY `idx_tppoi_product` (`product_id`),
  CONSTRAINT `fk_tppoi_po` FOREIGN KEY (`po_id`) REFERENCES `tp_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
