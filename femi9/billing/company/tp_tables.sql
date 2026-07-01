-- ============================================================
-- Territory Partner Module — Missing Tables
-- Generated: 2026-06-09
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. territory_partners
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `territory_partners` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tp_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gstin` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tp_id` (`tp_id`),
  UNIQUE KEY `uk_tp_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. tp_id_sequence
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tp_id_sequence` (
  `id` tinyint UNSIGNED NOT NULL DEFAULT 1,
  `last_val` int UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tp_id_sequence` (`id`, `last_val`) VALUES (1, 0);

-- ------------------------------------------------------------
-- 3. territory_partner_locations
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `territory_partner_locations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int UNSIGNED NOT NULL,
  `location_id` int UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tpl_location` (`location_id`),
  KEY `idx_tpl_partner` (`territory_partner_id`),
  CONSTRAINT `fk_tpl_partner` FOREIGN KEY (`territory_partner_id`) REFERENCES `territory_partners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpl_location` FOREIGN KEY (`location_id`) REFERENCES `partner_location_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. tp_inv_sequence
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tp_inv_sequence` (
  `id` tinyint UNSIGNED NOT NULL DEFAULT 1,
  `last_val` int UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tp_inv_sequence` (`id`, `last_val`) VALUES (1, 0);

-- ------------------------------------------------------------
-- 5. tp_invoices
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tp_invoices` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `territory_partner_id` int UNSIGNED NOT NULL,
  `source_location_id` int UNSIGNED NOT NULL,
  `invoice_date` date NOT NULL,
  `courier_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tp_inv_number` (`invoice_number`),
  KEY `idx_tpi_tp` (`territory_partner_id`),
  KEY `idx_tpi_location` (`source_location_id`),
  KEY `idx_tpi_date` (`invoice_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. tp_invoice_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tp_invoice_items` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tp_invoice_id` int UNSIGNED NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tpii_inv` (`tp_invoice_id`),
  CONSTRAINT `fk_tpii_inv` FOREIGN KEY (`tp_invoice_id`) REFERENCES `tp_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. tp_invoice_receipts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tp_invoice_receipts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tp_invoice_id` int UNSIGNED NOT NULL,
  `invoice_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `receipt_date` date NOT NULL,
  `payment_mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tprcpt_inv` (`tp_invoice_id`),
  CONSTRAINT `fk_tprcpt_inv` FOREIGN KEY (`tp_invoice_id`) REFERENCES `tp_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. tp_advance_payments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tp_advance_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjusted_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','partially_adjusted','fully_adjusted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tpap_tp` (`territory_partner_id`),
  KEY `idx_tpap_status` (`status`),
  KEY `idx_tpap_date` (`payment_date`),
  KEY `idx_tpap_balance` (`territory_partner_id`, `balance_amount`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. territory_partner_stock
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `territory_partner_stock` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int UNSIGNED NOT NULL,
  `product_id` int NOT NULL,
  `input_qty` int NOT NULL DEFAULT 0,
  `closing_qty` int NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tps` (`territory_partner_id`, `product_id`),
  KEY `idx_tps_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. territory_partner_stock_ledger
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `territory_partner_stock_ledger` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int UNSIGNED NOT NULL,
  `product_id` int NOT NULL,
  `action` enum('credit','deduct','adjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL,
  `qty_before` int NOT NULL,
  `qty_after` int NOT NULL,
  `ref_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ref_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tpsl_tp_prod` (`territory_partner_id`, `product_id`, `created_at`),
  KEY `idx_tpsl_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
