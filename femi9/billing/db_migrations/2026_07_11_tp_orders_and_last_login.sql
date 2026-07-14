-- Adds the "Got Order / No Order" field-visit tracking table for Territory
-- Partner logins (femi9/billing/territory-partner/add-order.php,
-- order-action.php, order-action-get.php, manage-orders.php) — mirrors the
-- existing ms_orders table used by the Marketing Staff module.
--
-- Also adds territory_partners.last_login, which CheckLogin.php already
-- writes to on every successful login (the column was missing from this
-- environment's DB, which made login silently fail after a valid password
-- check — see the UPDATE ... SET last_login = NOW() call in CheckLogin.php).
--
-- Idempotent — safe to run more than once.

CREATE TABLE IF NOT EXISTS `tp_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(60) NOT NULL,
  `shop_id` INT NOT NULL,
  `tp_id` INT NOT NULL,
  `order_date` DATE NOT NULL,
  `new_order` ENUM('yes','no') NOT NULL,
  `noorder_reason` VARCHAR(500) NOT NULL DEFAULT 'nil',
  `marketing_tool` VARCHAR(500) NOT NULL DEFAULT '',
  `pr_id` INT NOT NULL DEFAULT 0,
  `qty` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_tp_id_date` (`tp_id`, `order_date`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_new_order` (`new_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS _add_tp_last_login;
DELIMITER //
CREATE PROCEDURE _add_tp_last_login()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'territory_partners'
      AND COLUMN_NAME  = 'last_login'
  ) THEN
    ALTER TABLE `territory_partners` ADD COLUMN `last_login` DATETIME NULL DEFAULT NULL;
  END IF;
END //
DELIMITER ;
CALL _add_tp_last_login();
DROP PROCEDURE IF EXISTS _add_tp_last_login;
