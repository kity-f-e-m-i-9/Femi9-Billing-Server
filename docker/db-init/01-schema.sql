-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: billing0femi9_billingapp
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `billing0femi9_billingapp`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `billing0femi9_billingapp` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `billing0femi9_billingapp`;

--
-- Table structure for table `admin_developer`
--

DROP TABLE IF EXISTS `admin_developer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_developer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_log`
--

DROP TABLE IF EXISTS `admin_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(250) NOT NULL,
  `password` varchar(255) NOT NULL,
  `usertype` varchar(250) NOT NULL,
  `state` int(11) NOT NULL,
  `dash` int(11) NOT NULL,
  `report` int(11) NOT NULL,
  `company_profile` int(11) NOT NULL,
  `users_demo` int(11) NOT NULL,
  `reward_points` int(11) NOT NULL,
  `demo_free` int(11) NOT NULL,
  `manage_return` int(11) NOT NULL,
  `debit_note` int(11) NOT NULL,
  `stock_request` int(11) NOT NULL,
  `products` int(11) NOT NULL,
  `add_input_stock` int(11) NOT NULL,
  `manage_input_stock` int(11) NOT NULL,
  `add_input_stock_users` int(11) NOT NULL,
  `manage_input_stock_users` int(11) NOT NULL,
  `ot_channels` int(11) NOT NULL,
  `location` int(11) NOT NULL,
  `ss` int(11) NOT NULL,
  `st` int(11) NOT NULL,
  `dt` int(11) NOT NULL,
  `sdt` int(11) DEFAULT NULL,
  `shop` int(11) NOT NULL,
  `cus` int(11) NOT NULL,
  `ms` int(11) NOT NULL,
  `unassigned` int(11) NOT NULL,
  `remap` int(11) NOT NULL,
  `partner_location` tinyint(1) NOT NULL DEFAULT 0,
  `channel_partner` tinyint(1) NOT NULL DEFAULT 0,
  `territory_partner` tinyint(1) NOT NULL DEFAULT 0,
  `stock_transfers` tinyint(1) NOT NULL DEFAULT 0,
  `users_network` int(11) NOT NULL,
  `payment_entry` int(11) NOT NULL,
  `manage_payment_entry` int(11) NOT NULL DEFAULT 0,
  `consolidated_payment_entry` int(11) NOT NULL DEFAULT 0,
  `bonus_calculator` int(11) NOT NULL,
  `manage_bonus_points` int(11) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last successful login time',
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mobile` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_log_ot`
--

DROP TABLE IF EXISTS `admin_log_ot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_log_ot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `ot_cat` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=343 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_scroll_msg`
--

DROP TABLE IF EXISTS `admin_scroll_msg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_scroll_msg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ss_msg` varchar(255) NOT NULL,
  `st_msg` varchar(255) NOT NULL,
  `dt_msg` varchar(255) NOT NULL,
  `cf_msg` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_settings`
--

DROP TABLE IF EXISTS `admin_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tds_percentage` int(11) NOT NULL COMMENT 'Wallet Withdraw',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_website_coupon_commission`
--

DROP TABLE IF EXISTS `admin_website_coupon_commission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_website_coupon_commission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usertype` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_whatsapp_configuration`
--

DROP TABLE IF EXISTS `admin_whatsapp_configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_whatsapp_configuration` (
  `id` int(11) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `wa_id` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `advance_payment_adjustments`
--

DROP TABLE IF EXISTS `advance_payment_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `advance_payment_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `advance_payment_id` int(10) unsigned NOT NULL COMMENT 'FK to advance_payments.id',
  `invoice_id` varchar(255) DEFAULT NULL COMMENT 'Invoice ID from user_invoice.inv_id',
  `invoice_number` varchar(255) DEFAULT NULL COMMENT 'Human-readable invoice number',
  `adjusted_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount adjusted in this transaction',
  `adjustment_date` date NOT NULL COMMENT 'Date of adjustment',
  `adjustment_type` enum('invoice','manual','credit_note','refund','correction') NOT NULL DEFAULT 'invoice' COMMENT 'Type of adjustment',
  `balance_before` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Balance before this adjustment',
  `balance_after` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Balance after this adjustment',
  `adjusted_by_user_id` varchar(255) DEFAULT NULL COMMENT 'User who performed adjustment',
  `adjusted_by_user_type` varchar(255) DEFAULT NULL COMMENT 'User type who performed adjustment',
  `remarks` text DEFAULT NULL COMMENT 'Notes about this adjustment',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional data (invoice items, products, etc)' CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  `deleted_by_user_id` varchar(50) DEFAULT NULL,
  `deleted_by_user_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_advance_payment_id` (`advance_payment_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_adjustment_date` (`adjustment_date`),
  KEY `idx_adjustment_type` (`adjustment_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_composite_search` (`advance_payment_id`,`adjustment_type`,`deleted_at`),
  KEY `idx_adj_invoice_lookup` (`invoice_id`,`deleted_at`,`adjustment_type`),
  CONSTRAINT `fk_adjustment_advance_payment` FOREIGN KEY (`advance_payment_id`) REFERENCES `advance_payments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3468 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks all adjustments made to advance payments';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `advance_payment_reconciliation_invoice_log`
--

DROP TABLE IF EXISTS `advance_payment_reconciliation_invoice_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `advance_payment_reconciliation_invoice_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(50) NOT NULL,
  `invoice_id` varchar(100) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `customer_id` varchar(100) NOT NULL,
  `customer_type` varchar(50) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `invoice_amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `receipts_updated` int(11) DEFAULT 0,
  `old_receipt_data` text DEFAULT NULL,
  `status` enum('success','partial','failed','insufficient_balance') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_execution_id` (`execution_id`),
  KEY `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4208 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `advance_payment_reconciliation_log`
--

DROP TABLE IF EXISTS `advance_payment_reconciliation_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `advance_payment_reconciliation_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(50) NOT NULL,
  `execution_date` datetime NOT NULL,
  `mode` enum('dry_run','execution','rollback') NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `total_invoices` int(11) DEFAULT 0,
  `successful_invoices` int(11) DEFAULT 0,
  `failed_invoices` int(11) DEFAULT 0,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `executed_by` varchar(100) DEFAULT NULL,
  `status` enum('started','completed','failed') DEFAULT 'started',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `advance_payments`
--

DROP TABLE IF EXISTS `advance_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `advance_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_user_id` varchar(255) NOT NULL COMMENT 'temp_id of user making payment',
  `from_user_type` varchar(50) NOT NULL COMMENT 'User type: super_stockiest, stockiest, distributor, super_distributor, c_and_f',
  `from_user_name` varchar(255) NOT NULL COMMENT 'Name of payer (for quick reference)',
  `company_id` int(11) DEFAULT NULL,
  `to_user_id` varchar(255) NOT NULL COMMENT 'temp_id of user receiving payment',
  `to_user_type` varchar(255) NOT NULL COMMENT 'User type: company, super_stockiest, stockiest, c_and_f',
  `to_user_name` varchar(255) NOT NULL COMMENT 'Name of receiver (for quick reference)',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total advance amount paid',
  `payment_date` date NOT NULL COMMENT 'Date when payment was actually made',
  `payment_mode` varchar(255) DEFAULT NULL COMMENT 'Cash, Bank Transfer, Cheque, UPI, NEFT, RTGS, etc.',
  `reference_number` varchar(255) DEFAULT NULL COMMENT 'Transaction/Cheque/UTR reference number',
  `bank_name` varchar(255) DEFAULT NULL COMMENT 'Bank name if applicable',
  `adjusted_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount already adjusted against invoices',
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Remaining balance available',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes about the payment',
  `created_by_user_id` varchar(255) DEFAULT NULL COMMENT 'temp_id of user who created this entry',
  `created_by_user_type` varchar(255) DEFAULT NULL COMMENT 'User type of creator',
  `updated_by_user_id` varchar(255) DEFAULT NULL COMMENT 'temp_id of user who last updated',
  `updated_by_user_type` varchar(255) DEFAULT NULL COMMENT 'User type of updater',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Entry creation timestamp',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_from_user` (`from_user_id`,`from_user_type`),
  KEY `idx_to_user` (`to_user_id`,`to_user_type`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_status` (`status`),
  KEY `idx_deleted` (`deleted_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_balance` (`balance_amount`),
  KEY `idx_reference` (`reference_number`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2661 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks advance payments between users in distribution hierarchy';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260824_user_invoice_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260824_user_invoice_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260824_user_invoice_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `source_ms_order_id` varchar(255) DEFAULT NULL,
  `id_only` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `inv_year` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `credit` int(11) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL COMMENT 'inner state, outer state',
  `roundoff` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `rwpoints_enable` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','submitted','cancelled') DEFAULT 'draft',
  `voided_at` datetime DEFAULT NULL,
  `voided_by_user_type` varchar(50) DEFAULT NULL,
  `voided_by_user_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260824_user_invoice_items_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260824_user_invoice_items_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260824_user_invoice_items_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `pr_id` int(11) NOT NULL,
  `amount` float NOT NULL,
  `qty` int(11) NOT NULL,
  `gst_percentage` int(11) NOT NULL,
  `gstamount_singlepr` varchar(255) NOT NULL,
  `gstamount_total` varchar(255) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `discount_percentage` varchar(255) NOT NULL,
  `discount_amount` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `rwpoints` float NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `rwpoints_sls` float NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_advance_payments_cn_fix`
--

DROP TABLE IF EXISTS `backup_20260827_advance_payments_cn_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_advance_payments_cn_fix` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `from_user_id` varchar(255) NOT NULL COMMENT 'temp_id of user making payment',
  `from_user_type` varchar(50) NOT NULL COMMENT 'User type: super_stockiest, stockiest, distributor, super_distributor, c_and_f',
  `from_user_name` varchar(255) NOT NULL COMMENT 'Name of payer (for quick reference)',
  `company_id` int(11) DEFAULT NULL,
  `to_user_id` varchar(255) NOT NULL COMMENT 'temp_id of user receiving payment',
  `to_user_type` varchar(255) NOT NULL COMMENT 'User type: company, super_stockiest, stockiest, c_and_f',
  `to_user_name` varchar(255) NOT NULL COMMENT 'Name of receiver (for quick reference)',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total advance amount paid',
  `payment_date` date NOT NULL COMMENT 'Date when payment was actually made',
  `payment_mode` varchar(255) DEFAULT NULL COMMENT 'Cash, Bank Transfer, Cheque, UPI, NEFT, RTGS, etc.',
  `reference_number` varchar(255) DEFAULT NULL COMMENT 'Transaction/Cheque/UTR reference number',
  `bank_name` varchar(255) DEFAULT NULL COMMENT 'Bank name if applicable',
  `adjusted_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount already adjusted against invoices',
  `balance_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Remaining balance available',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes about the payment',
  `created_by_user_id` varchar(255) DEFAULT NULL COMMENT 'temp_id of user who created this entry',
  `created_by_user_type` varchar(255) DEFAULT NULL COMMENT 'User type of creator',
  `updated_by_user_id` varchar(255) DEFAULT NULL COMMENT 'temp_id of user who last updated',
  `updated_by_user_type` varchar(255) DEFAULT NULL COMMENT 'User type of updater',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Entry creation timestamp',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_internal_transfer_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260827_internal_transfer_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_internal_transfer_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `tempid` varchar(255) NOT NULL,
  `send_from` int(11) NOT NULL,
  `send_to` int(11) NOT NULL,
  `date` date NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `gst` int(11) NOT NULL,
  `gst_type` enum('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
  `taxable_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_amount` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_invoice_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260827_invoice_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_invoice_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `id_only` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `inv_year` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `roundoff` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_invoice_items_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260827_invoice_items_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_invoice_items_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `pr_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `total` varchar(255) NOT NULL,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `gst_percentage` varchar(255) NOT NULL,
  `gstamount_singlepr` varchar(255) NOT NULL,
  `gstamount_total` varchar(255) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `discount_percentage` varchar(255) NOT NULL,
  `discount_amount` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `rwpoints` float NOT NULL DEFAULT 0,
  `rwpoints_sls` float NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_urs_header_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260827_urs_header_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_urs_header_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `returnid` varchar(255) NOT NULL,
  `invnumber` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `subtotal` int(11) NOT NULL,
  `discount` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `from_usertype` varchar(255) NOT NULL COMMENT 'stock returned from',
  `from_userid` varchar(255) NOT NULL,
  `to_usertype` varchar(255) NOT NULL,
  `to_userid` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL COMMENT 'pending, accept, reject',
  `rwpoints_enable` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_urs_items_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260827_urs_items_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_urs_items_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `returnid` varchar(255) NOT NULL,
  `invnumber` varchar(255) NOT NULL,
  `prid` int(11) NOT NULL,
  `amount` float NOT NULL,
  `qty` int(11) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `gst_percentage` varchar(255) NOT NULL,
  `gstamount_total` varchar(255) NOT NULL,
  `total` float NOT NULL,
  `from_usertype` varchar(255) NOT NULL,
  `from_userid` varchar(255) NOT NULL,
  `to_usertype` varchar(255) NOT NULL,
  `to_userid` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `status` varchar(255) NOT NULL COMMENT 'pending, accept, reject',
  `hsn` varchar(255) NOT NULL,
  `damaged_qty` int(11) NOT NULL,
  `rwpoints` float NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `rwpoints_sls` float NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_user_invoice_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260827_user_invoice_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_user_invoice_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `source_ms_order_id` varchar(255) DEFAULT NULL,
  `id_only` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `inv_year` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `credit` int(11) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL COMMENT 'inner state, outer state',
  `roundoff` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `rwpoints_enable` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','submitted','cancelled') DEFAULT 'draft',
  `voided_at` datetime DEFAULT NULL,
  `voided_by_user_type` varchar(50) DEFAULT NULL,
  `voided_by_user_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_20260827_user_invoice_items_gst_fix`
--

DROP TABLE IF EXISTS `backup_20260827_user_invoice_items_gst_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_20260827_user_invoice_items_gst_fix` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `pr_id` int(11) NOT NULL,
  `amount` float NOT NULL,
  `qty` int(11) NOT NULL,
  `gst_percentage` int(11) NOT NULL,
  `gstamount_singlepr` varchar(255) NOT NULL,
  `gstamount_total` varchar(255) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `discount_percentage` varchar(255) NOT NULL,
  `discount_amount` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `rwpoints` float NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `rwpoints_sls` float NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_deactivation_log`
--

DROP TABLE IF EXISTS `bonus_deactivation_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_deactivation_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(50) NOT NULL COMMENT 'Links to bonus_execution_log',
  `user_id` varchar(50) NOT NULL,
  `user_type` enum('super_stockiest','stockiest','territory_partner') NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `previous_status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'Always active — we only deactivate active users',
  `deactivation_reason` varchar(255) NOT NULL,
  `deactivated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `restored_at` timestamp NULL DEFAULT NULL,
  `restored_by_user_id` varchar(50) DEFAULT NULL,
  `restored_by_user_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_execution_id` (`execution_id`),
  KEY `idx_user` (`user_id`,`user_type`),
  KEY `idx_restored_at` (`restored_at`)
) ENGINE=InnoDB AUTO_INCREMENT=752 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks account deactivations triggered by bonus execution for clean rollback';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_execution_log`
--

DROP TABLE IF EXISTS `bonus_execution_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_execution_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(50) NOT NULL,
  `execution_mode` enum('dry_run','execute') NOT NULL,
  `month_year` varchar(7) NOT NULL,
  `total_users_processed` int(11) NOT NULL DEFAULT 0,
  `total_eligible_users` int(11) NOT NULL DEFAULT 0,
  `total_ineligible_users` int(11) NOT NULL DEFAULT 0,
  `total_bonus_points_awarded` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_accounts_deactivated` int(11) NOT NULL DEFAULT 0,
  `executed_by_user_id` varchar(50) NOT NULL,
  `executed_by_user_type` varchar(50) NOT NULL,
  `executed_by_user_name` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_rolled_back` tinyint(1) NOT NULL DEFAULT 0,
  `rolled_back_at` timestamp NULL DEFAULT NULL,
  `rolled_back_by_user_id` varchar(50) DEFAULT NULL,
  `rolled_back_by_user_type` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `execution_id` (`execution_id`),
  KEY `idx_execution_id` (`execution_id`),
  KEY `idx_month_year` (`month_year`),
  KEY `idx_executed_at` (`executed_at`),
  KEY `idx_is_rolled_back` (`is_rolled_back`),
  KEY `idx_exec_mode_date` (`execution_mode`,`executed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Execution log for bonus points calculations with rollback tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_points_history`
--

DROP TABLE IF EXISTS `bonus_points_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_points_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL COMMENT 'temp_id from super_stockiest or stockiest table',
  `user_type` enum('super_stockiest','stockiest','territory_partner') NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `category_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `monthly_target` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_advance_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week1_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week1_cumulative` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week1_required` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week1_status` enum('pass','fail') NOT NULL,
  `week2_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week2_cumulative` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week2_required` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week2_status` enum('pass','fail') NOT NULL,
  `week3_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week3_cumulative` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week3_required` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week3_status` enum('pass','fail') NOT NULL,
  `week4_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week4_cumulative` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week4_required` decimal(10,2) NOT NULL DEFAULT 0.00,
  `week4_status` enum('pass','fail') NOT NULL,
  `eligibility_status` enum('eligible','not_eligible') NOT NULL,
  `bonus_points_awarded` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bonus_calculation` varchar(255) NOT NULL COMMENT 'Formula used for calculation',
  `execution_id` varchar(50) NOT NULL COMMENT 'Unique execution identifier',
  `executed_by_user_id` varchar(50) NOT NULL,
  `executed_by_user_type` varchar(50) NOT NULL,
  `executed_by_user_name` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rolled_back_at` timestamp NULL DEFAULT NULL,
  `rolled_back_by_user_id` varchar(50) DEFAULT NULL,
  `rolled_back_by_user_type` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`user_type`),
  KEY `idx_month_year` (`month_year`),
  KEY `idx_execution_id` (`execution_id`),
  KEY `idx_eligibility` (`eligibility_status`),
  KEY `idx_executed_at` (`executed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3495 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History of bonus points awarded based on monthly target achievement';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_and_f`
--

DROP TABLE IF EXISTS `c_and_f`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_and_f` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(255) NOT NULL,
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` varchar(255) NOT NULL,
  `district_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `plan_amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `amount_method` varchar(255) NOT NULL,
  `amount_status` varchar(255) NOT NULL COMMENT 'pending / paid',
  `ref_number` varchar(255) NOT NULL COMMENT 'amount trasnfer ref number (or) coupon number',
  `account_status` varchar(255) NOT NULL COMMENT 'pending / active / deactive',
  `merchantOrderId` varchar(255) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `channel_partner_locations`
--

DROP TABLE IF EXISTS `channel_partner_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `channel_partner_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel_partner_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cpl_location` (`location_id`),
  KEY `idx_cpl_partner` (`channel_partner_id`),
  CONSTRAINT `fk_cpl_location` FOREIGN KEY (`location_id`) REFERENCES `partner_location_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cpl_partner` FOREIGN KEY (`channel_partner_id`) REFERENCES `channel_partners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `channel_partner_stock`
--

DROP TABLE IF EXISTS `channel_partner_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `channel_partner_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel_partner_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `input_qty` int(11) NOT NULL DEFAULT 0,
  `closing_qty` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cp_product` (`channel_partner_id`,`product_id`),
  KEY `idx_cp` (`channel_partner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `channel_partner_stock_ledger`
--

DROP TABLE IF EXISTS `channel_partner_stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `channel_partner_stock_ledger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel_partner_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `action` enum('opening','credit','deduct','transfer_in','transfer_out','adjustment') NOT NULL,
  `qty` int(11) NOT NULL,
  `qty_before` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `ref_type` enum('input','transfer','invoice','adjustment','opening','tp_invoice') NOT NULL DEFAULT 'input',
  `ref_id` varchar(255) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cp` (`channel_partner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=400 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `channel_partners`
--

DROP TABLE IF EXISTS `channel_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `channel_partners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cp_id` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `referral_id` varchar(100) DEFAULT NULL,
  `referral_percentage` decimal(5,2) DEFAULT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `branch_line1` varchar(255) DEFAULT NULL,
  `branch_line2` varchar(255) DEFAULT NULL,
  `branch_city` varchar(100) DEFAULT NULL,
  `branch_district` varchar(100) DEFAULT NULL,
  `branch_state` varchar(100) DEFAULT NULL,
  `branch_country` varchar(100) DEFAULT NULL,
  `branch_pincode` varchar(20) DEFAULT NULL,
  `delivery_line1` varchar(255) DEFAULT NULL,
  `delivery_line2` varchar(255) DEFAULT NULL,
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_district` varchar(100) DEFAULT NULL,
  `delivery_state` varchar(100) DEFAULT NULL,
  `delivery_country` varchar(100) DEFAULT NULL,
  `delivery_pincode` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `gst_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `password` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `updated_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cp_id` (`cp_id`),
  UNIQUE KEY `uk_cp_mobile` (`mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cleanup_backup_20260131`
--

DROP TABLE IF EXISTS `cleanup_backup_20260131`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cleanup_backup_20260131` (
  `table_name` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `record_id` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inv_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_method` varchar(255) NOT NULL,
  `receipt_remarks` varchar(255) NOT NULL,
  `received` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_bdm_view_bridge`
--

DROP TABLE IF EXISTS `company_bdm_view_bridge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_bdm_view_bridge` (
  `token` varchar(64) NOT NULL,
  `bdm_id` int(11) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_godown`
--

DROP TABLE IF EXISTS `company_godown`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_godown` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gname` varchar(255) NOT NULL,
  `finance_only` tinyint(1) NOT NULL DEFAULT 0,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `state_code` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `acname` varchar(255) NOT NULL,
  `acnumber` varchar(255) NOT NULL,
  `bankname` varchar(255) NOT NULL,
  `branchname` varchar(255) NOT NULL,
  `ifsc` varchar(255) NOT NULL,
  `upinumber` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_login_switch_bridge`
--

DROP TABLE IF EXISTS `company_login_switch_bridge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_login_switch_bridge` (
  `token` varchar(64) NOT NULL,
  `bdm_id` int(11) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_return_stock`
--

DROP TABLE IF EXISTS `company_return_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_return_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `godownid` int(11) NOT NULL,
  `prid` int(11) NOT NULL,
  `returnqty` int(11) NOT NULL,
  `date` date NOT NULL,
  `remarks` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competitor_brand`
--

DROP TABLE IF EXISTS `competitor_brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competitor_brand` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `country`
--

DROP TABLE IF EXISTS `country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `c_name` varchar(255) NOT NULL,
  `c_code` varchar(255) NOT NULL,
  `currency_name` varchar(55) NOT NULL,
  `currency_ascii_code` varchar(155) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(255) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `plan_amount` int(11) NOT NULL,
  `coupon_number` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `stock_user_tempid` varchar(255) NOT NULL COMMENT 'coupon buyers',
  `user_type` varchar(255) NOT NULL COMMENT 'company,super_stockiest,stockiest,distributor',
  `coupon_date` date NOT NULL,
  `coupon_status` varchar(255) NOT NULL COMMENT 'none (or) used',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='coupons provide from developer side';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courier_payment_settings`
--

DROP TABLE IF EXISTS `courier_payment_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courier_payment_settings` (
  `id` int(10) unsigned NOT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `upi_payee_name` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `napkin_box_rate` decimal(10,2) NOT NULL DEFAULT 80.00,
  `napkin_box_rate_tier2` decimal(10,2) NOT NULL DEFAULT 60.00,
  `napkin_tier2_threshold` int(10) unsigned NOT NULL DEFAULT 10,
  `diaper_box_rate` decimal(10,2) NOT NULL DEFAULT 80.00,
  `cover_rate` decimal(10,2) NOT NULL DEFAULT 50.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cp_id_sequence`
--

DROP TABLE IF EXISTS `cp_id_sequence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cp_id_sequence` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `last_val` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `marketing_date` date NOT NULL,
  `date` int(11) NOT NULL,
  `user_type` varchar(255) NOT NULL COMMENT 'company,super_stockiest,stockiest,distributor',
  `user_id` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Last update timestamp',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_mapping` (`user_type`,`user_id`),
  KEY `idx_customer_name` (`name`),
  KEY `idx_customer_mobile` (`mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=35151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `daily_login_rewards`
--

DROP TABLE IF EXISTS `daily_login_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_login_rewards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(50) NOT NULL COMMENT 'super_stockiest, stockiest, distributor, super_distributor',
  `user_id` varchar(255) NOT NULL COMMENT 'temp_id of the user',
  `reward_date` date NOT NULL COMMENT 'Date when reward was earned',
  `points_awarded` int(11) NOT NULL DEFAULT 5 COMMENT 'Points awarded (always 5)',
  `invoice_id` varchar(255) NOT NULL COMMENT 'Invoice ID that triggered the reward',
  `invoice_number` varchar(255) NOT NULL COMMENT 'Human-readable invoice number',
  `wallet_entry_id` int(11) DEFAULT NULL COMMENT 'Reference to wallet_monthly_sls_report.id',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'When reward was processed',
  `notes` varchar(500) DEFAULT NULL COMMENT 'Any additional notes',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_daily_reward` (`user_type`,`user_id`,`reward_date`),
  KEY `idx_user_lookup` (`user_type`,`user_id`),
  KEY `idx_reward_date` (`reward_date`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=33707 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks daily login and billing rewards (5 points per day)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `daily_reward_audit_log`
--

DROP TABLE IF EXISTS `daily_reward_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_reward_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `action_type` varchar(50) NOT NULL COMMENT 'e.g., reward_granted, reward_revoked, backfill_run',
  `user_type` varchar(50) DEFAULT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `invoice_id` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `reward_date` date DEFAULT NULL,
  `points_amount` int(11) DEFAULT NULL,
  `admin_user` varchar(255) DEFAULT NULL COMMENT 'Admin who performed action',
  `notes` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_user` (`user_type`,`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for daily reward system';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `daily_reward_config`
--

DROP TABLE IF EXISTS `daily_reward_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_reward_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL COMMENT 'Configuration key',
  `setting_value` varchar(500) NOT NULL COMMENT 'Configuration value',
  `description` varchar(500) DEFAULT NULL COMMENT 'What this setting does',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Configuration for daily reward system';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `delivery_note`
--

DROP TABLE IF EXISTS `delivery_note`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_note` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `inv_table` varchar(255) NOT NULL COMMENT 'user,shop,customer',
  `dl_note` varchar(255) NOT NULL,
  `mode_pmnt` varchar(255) NOT NULL,
  `ref_no` varchar(255) NOT NULL,
  `ref_date` date NOT NULL,
  `ot_ref` varchar(255) NOT NULL,
  `order_no` varchar(255) NOT NULL,
  `dated` date NOT NULL,
  `dispatch_doc_no` varchar(255) NOT NULL,
  `dlnote_date` date NOT NULL,
  `dispatch_through` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `terms` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demo_awareness`
--

DROP TABLE IF EXISTS `demo_awareness`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demo_awareness` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `photo` text NOT NULL,
  `title` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `ssid` varchar(255) NOT NULL,
  `stockist_id` varchar(255) NOT NULL,
  `distributor_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=494 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demofreedamage`
--

DROP TABLE IF EXISTS `demofreedamage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demofreedamage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `remarks` varchar(255) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4735 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dispatch_slip_settings`
--

DROP TABLE IF EXISTS `dispatch_slip_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dispatch_slip_settings` (
  `id` int(10) unsigned NOT NULL,
  `overall_packs_per_box` int(10) unsigned NOT NULL DEFAULT 50,
  `overall_packs_per_cover` int(10) unsigned NOT NULL DEFAULT 21,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `distributor`
--

DROP TABLE IF EXISTS `distributor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distributor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stockiest_id` varchar(255) NOT NULL COMMENT 'stockiest id',
  `temp_id` varchar(255) NOT NULL COMMENT 'Distributor ID',
  `category_id` int(11) NOT NULL DEFAULT 1 COMMENT 'Category ID from distributor_category table',
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` varchar(255) NOT NULL,
  `district_id` varchar(255) NOT NULL,
  `taluk_id` varchar(255) NOT NULL,
  `pincode_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last successful login time',
  `plan_amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `amount_method` varchar(255) NOT NULL,
  `amount_status` varchar(255) NOT NULL COMMENT 'pending / paid',
  `ref_number` varchar(255) NOT NULL COMMENT 'amount trasnfer ref number (or) coupon number',
  `account_status` varchar(255) NOT NULL COMMENT 'pending / active / deactive',
  `merchantOrderId` varchar(255) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `shop_onboard` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_distributor_valid` (`valid_from`),
  KEY `idx_dist_state_temp` (`state_id`,`temp_id`),
  KEY `idx_mobile` (`mobile_number`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `distributor_category`
--

DROP TABLE IF EXISTS `distributor_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distributor_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `ref_commission_percentage` int(11) NOT NULL,
  `cash_back_percentage` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `distributor_referral`
--

DROP TABLE IF EXISTS `distributor_referral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distributor_referral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `distributor_id` varchar(255) NOT NULL,
  `dist_cat_id` int(11) NOT NULL DEFAULT 0,
  `target_amount` int(11) NOT NULL,
  `ref_by_user_type` varchar(255) NOT NULL,
  `ref_by_user_id` varchar(255) NOT NULL,
  `updated` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=769 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `district`
--

DROP TABLE IF EXISTS `district`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `district` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `dist_name` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `assigned_SSID` varchar(255) NOT NULL COMMENT 'Super Stockist ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expense_import_items`
--

DROP TABLE IF EXISTS `expense_import_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_import_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `import_id` int(10) unsigned NOT NULL,
  `particulars` varchar(255) NOT NULL,
  `date` date DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_import` (`import_id`),
  CONSTRAINT `fk_expense_import_items_import` FOREIGN KEY (`import_id`) REFERENCES `expense_imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expense_imports`
--

DROP TABLE IF EXISTS `expense_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_imports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `expense_month` date NOT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `source_filename` varchar(255) NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `period_label` varchar(255) DEFAULT NULL,
  `total_debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_month` (`company_id`,`expense_month`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `femi9_llp_sale_rates`
--

DROP TABLE IF EXISTS `femi9_llp_sale_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `femi9_llp_sale_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `effective_date` date NOT NULL,
  `rate_per_piece` decimal(10,2) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_type` varchar(10) NOT NULL DEFAULT 'exclusive',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_date` (`product_id`,`effective_date`),
  KEY `idx_product` (`product_id`),
  KEY `idx_effective_date` (`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fix_courier_payment_type_audit`
--

DROP TABLE IF EXISTS `fix_courier_payment_type_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fix_courier_payment_type_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `run_at` datetime NOT NULL DEFAULT current_timestamp(),
  `receiptid` varchar(255) NOT NULL,
  `inv_id` varchar(255) NOT NULL,
  `invoice_amount` int(11) NOT NULL,
  `old_payment_type` varchar(50) NOT NULL,
  `new_payment_type` varchar(50) NOT NULL,
  `invoice_source` varchar(50) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `rolled_back` tinyint(1) NOT NULL DEFAULT 0,
  `rolled_back_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receiptid` (`receiptid`),
  KEY `idx_rolled_back` (`rolled_back`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forgotpassword`
--

DROP TABLE IF EXISTS `forgotpassword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forgotpassword` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usertype` varchar(50) NOT NULL,
  `mobilenumber` varchar(15) NOT NULL,
  `reset_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Flag to force password change (1=must change, 0=changed)',
  `password_changed_at` timestamp NULL DEFAULT NULL COMMENT 'When user changed the reset password',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `attempts` int(10) unsigned DEFAULT 1,
  `reset` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`usertype`,`mobilenumber`),
  KEY `idx_reset_at` (`reset_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2524 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `full_cleanup_backup_20260131`
--

DROP TABLE IF EXISTS `full_cleanup_backup_20260131`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `full_cleanup_backup_20260131` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `id_only` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `inv_year` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `credit` int(11) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL COMMENT 'inner state, outer state',
  `roundoff` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `rwpoints_enable` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `receipt_id` int(11) DEFAULT 0,
  `receipt_amount` int(11) DEFAULT NULL,
  `receipt_method` varchar(255) DEFAULT NULL,
  `receipt_remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `input_stock`
--

DROP TABLE IF EXISTS `input_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `input_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `godownid` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `input_qty` int(11) NOT NULL,
  `input_date` date NOT NULL,
  `input_remarks` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_input_stock_date` (`input_date`),
  KEY `idx_input_stock_tempid` (`tempid`,`input_date`)
) ENGINE=InnoDB AUTO_INCREMENT=754 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `input_stock_users`
--

DROP TABLE IF EXISTS `input_stock_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `input_stock_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `input_qty` varchar(255) NOT NULL,
  `input_date` varchar(255) NOT NULL,
  `remarks` varchar(255) NOT NULL,
  `still_maintain` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1342 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `internal_transfer`
--

DROP TABLE IF EXISTS `internal_transfer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_transfer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `send_from` int(11) NOT NULL,
  `send_to` int(11) NOT NULL,
  `date` date NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `gst` int(11) NOT NULL,
  `gst_type` enum('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
  `taxable_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_amount` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2358 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `internal_transfer_invoice`
--

DROP TABLE IF EXISTS `internal_transfer_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_transfer_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `inv_id` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1035 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `internal_transfer_ss`
--

DROP TABLE IF EXISTS `internal_transfer_ss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_transfer_ss` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `prid` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `date` date NOT NULL,
  `from_usertype` varchar(255) NOT NULL,
  `from_userid` varchar(255) NOT NULL,
  `to_usertype` varchar(255) NOT NULL,
  `to_userid` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1906 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice`
--

DROP TABLE IF EXISTS `invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `id_only` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `inv_year` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `roundoff` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_inv_id` (`inv_id`),
  KEY `idx_invoice_user_type_date` (`user_type`,`date`,`sub_total`),
  KEY `idx_invoice_user_id` (`user_type`,`user_id`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=60586 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_backup_cleanup_20260131`
--

DROP TABLE IF EXISTS `invoice_backup_cleanup_20260131`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_backup_cleanup_20260131` (
  `id` int(11) NOT NULL DEFAULT 0,
  `inv_id` varchar(255) NOT NULL,
  `id_only` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `inv_year` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `credit` int(11) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL COMMENT 'inner state, outer state',
  `roundoff` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `rwpoints_enable` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `pr_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `total` varchar(255) NOT NULL,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `gst_percentage` varchar(255) NOT NULL,
  `gstamount_singlepr` varchar(255) NOT NULL,
  `gstamount_total` varchar(255) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `discount_percentage` varchar(255) NOT NULL,
  `discount_amount` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `rwpoints` float NOT NULL DEFAULT 0,
  `rwpoints_sls` float NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_product` (`inv_id`,`pr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=93374 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_shipping_label_items`
--

DROP TABLE IF EXISTS `invoice_shipping_label_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_shipping_label_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label_id` int(10) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `product_text` varchar(255) NOT NULL,
  `packs_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_isli_label` (`label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=257 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoice_shipping_labels`
--

DROP TABLE IF EXISTS `invoice_shipping_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_shipping_labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` varchar(64) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `count_text` varchar(20) NOT NULL DEFAULT '',
  `source_text` varchar(100) NOT NULL DEFAULT '',
  `from_address` text DEFAULT NULL,
  `to_address` text DEFAULT NULL,
  `note_text` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_isl_invoice` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_staff`
--

DROP TABLE IF EXISTS `marketing_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marketing_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ms_name` varchar(255) NOT NULL,
  `ms_mobile` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last successful login time',
  `ms_email` varchar(255) NOT NULL,
  `ms_address` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `account_status` varchar(255) NOT NULL,
  `user_position` int(11) NOT NULL DEFAULT 0,
  `team_level_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `monthly_target_amount` decimal(12,2) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mobile` (`ms_mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_staff_locations`
--

DROP TABLE IF EXISTS `marketing_staff_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marketing_staff_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ms_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ms_location` (`ms_id`,`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_team_levels`
--

DROP TABLE IF EXISTS `marketing_team_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marketing_team_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_rank` int(11) NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `location_layer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level_rank` (`level_rank`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monthly_target_achievements`
--

DROP TABLE IF EXISTS `monthly_target_achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_target_achievements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `distributor_type` varchar(50) NOT NULL COMMENT 'distributor or super_distributor',
  `distributor_id` varchar(255) NOT NULL COMMENT 'temp_id of distributor/super_distributor',
  `parent_type` varchar(50) NOT NULL COMMENT 'super_stockiest or stockiest',
  `parent_id` varchar(255) NOT NULL COMMENT 'temp_id of parent user',
  `achievement_month` tinyint(3) unsigned NOT NULL COMMENT 'Month (1-12)',
  `achievement_year` year(4) NOT NULL COMMENT 'Year (e.g., 2025)',
  `category_id` int(11) NOT NULL COMMENT 'Category ID of distributor',
  `target_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monthly target from category',
  `total_sales` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total sales for the month',
  `user_invoice_sales` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Sales from user_invoice table',
  `invoice_sales` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Sales from invoice table',
  `target_achieved` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if achieved, 0 if not',
  `achievement_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage of target achieved',
  `calculated_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'When this was calculated',
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_distributor_month` (`distributor_type`,`distributor_id`,`achievement_year`,`achievement_month`),
  KEY `idx_parent_lookup` (`parent_type`,`parent_id`),
  KEY `idx_achievement_period` (`achievement_year`,`achievement_month`),
  KEY `idx_target_achieved` (`target_achieved`),
  KEY `idx_distributor_lookup` (`distributor_type`,`distributor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29306 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Detailed tracking of individual distributor achievements for monthly targets';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monthly_target_audit_log`
--

DROP TABLE IF EXISTS `monthly_target_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_target_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `action_type` varchar(50) NOT NULL COMMENT 'reward_granted, backfill_run, manual_adjustment, etc.',
  `action_description` text DEFAULT NULL COMMENT 'Detailed description of the action',
  `user_type` varchar(50) DEFAULT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `reward_month` tinyint(3) unsigned DEFAULT NULL,
  `reward_year` year(4) DEFAULT NULL,
  `points_amount` int(11) DEFAULT NULL,
  `execution_mode` enum('dry_run','execute','rollback','manual') DEFAULT NULL COMMENT 'How this was run',
  `records_affected` int(10) unsigned DEFAULT 0 COMMENT 'Number of records processed',
  `admin_user` varchar(255) DEFAULT NULL COMMENT 'Admin who performed action',
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `error_message` text DEFAULT NULL COMMENT 'Any error that occurred',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_user_lookup` (`user_type`,`user_id`),
  KEY `idx_period` (`reward_year`,`reward_month`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_admin_user` (`admin_user`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for monthly target reward system actions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monthly_target_config`
--

DROP TABLE IF EXISTS `monthly_target_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_target_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL COMMENT 'Configuration key',
  `setting_value` varchar(500) NOT NULL COMMENT 'Configuration value',
  `data_type` enum('string','integer','boolean','json','date') NOT NULL DEFAULT 'string',
  `description` varchar(500) DEFAULT NULL COMMENT 'What this setting does',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Configuration settings for monthly target rewards';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monthly_target_rewards`
--

DROP TABLE IF EXISTS `monthly_target_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_target_rewards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(50) NOT NULL COMMENT 'super_stockiest, stockiest',
  `user_id` varchar(255) NOT NULL COMMENT 'temp_id of super stockiest or stockiest',
  `reward_month` tinyint(3) unsigned NOT NULL COMMENT 'Month (1-12)',
  `reward_year` year(4) NOT NULL COMMENT 'Year (e.g., 2025)',
  `reward_date` date NOT NULL COMMENT 'Date when reward was calculated',
  `total_subordinates` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Total distributors/super_distributors under this user',
  `achieved_subordinates` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Number who achieved monthly target',
  `achievement_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage of subordinates who achieved',
  `reward_tier` enum('tier_5','tier_10','none') NOT NULL DEFAULT 'none' COMMENT 'tier_5 = 5 achievers, tier_10 = 10+ achievers',
  `points_awarded` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Points awarded (100 or 250)',
  `wallet_entry_id` int(11) DEFAULT NULL COMMENT 'Reference to wallet_monthly_sls_report.id if applicable',
  `processed_by` varchar(100) DEFAULT NULL COMMENT 'System process that created this (backfill/realtime/manual)',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'When reward was processed',
  `notes` varchar(500) DEFAULT NULL COMMENT 'Additional notes or details',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_user_month` (`user_type`,`user_id`,`reward_year`,`reward_month`),
  KEY `idx_user_lookup` (`user_type`,`user_id`),
  KEY `idx_reward_period` (`reward_year`,`reward_month`),
  KEY `idx_reward_date` (`reward_date`),
  KEY `idx_points_awarded` (`points_awarded`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=168 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks monthly target achievement rewards for Super Stockists and Stockists';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ms_attendance`
--

DROP TABLE IF EXISTS `ms_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ms_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ms_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ms_exp`
--

DROP TABLE IF EXISTS `ms_exp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ms_exp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `ms_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `amount` int(11) NOT NULL,
  `remarks` varchar(255) NOT NULL,
  `photos` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ms_orders`
--

DROP TABLE IF EXISTS `ms_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ms_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `ms_id` int(11) NOT NULL,
  `tp_id` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `new_order` varchar(255) NOT NULL,
  `noorder_reason` varchar(255) NOT NULL,
  `marketing_tool` varchar(255) NOT NULL,
  `pr_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_date` (`shop_id`,`order_date`),
  KEY `idx_ms_id` (`ms_id`),
  KEY `idx_tp_id` (`tp_id`),
  KEY `idx_order_date` (`order_date`)
) ENGINE=InnoDB AUTO_INCREMENT=82748 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ms_shop`
--

DROP TABLE IF EXISTS `ms_shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ms_shop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ms_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_name` varchar(255) NOT NULL,
  `district_name` varchar(255) NOT NULL,
  `taluk_name` varchar(255) NOT NULL,
  `pincode` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `shop_cat` int(11) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `landline` varchar(255) NOT NULL,
  `google_location` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `state_node_id` int(10) unsigned DEFAULT NULL,
  `district_node_id` int(10) unsigned DEFAULT NULL,
  `taluk_node_id` int(10) unsigned DEFAULT NULL,
  `firka_node_id` int(10) unsigned DEFAULT NULL,
  `location_recapture_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_district_node_id` (`district_node_id`),
  KEY `idx_taluk_node_id` (`taluk_node_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21499 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ms_shop_location_requests`
--

DROP TABLE IF EXISTS `ms_shop_location_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ms_shop_location_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `ms_id` int(11) NOT NULL,
  `district_name` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `accept_reason` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  `responded_by_bdm_id` int(11) DEFAULT NULL,
  `responded_by_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop` (`shop_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `neksomo_llp_piece_purchase_rates`
--

DROP TABLE IF EXISTS `neksomo_llp_piece_purchase_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `neksomo_llp_piece_purchase_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `effective_date` date NOT NULL,
  `rate_per_piece` decimal(14,6) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_type` enum('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_date` (`product_id`,`effective_date`),
  KEY `idx_product` (`product_id`),
  KEY `idx_effective_date` (`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `neksomo_llp_piece_rates`
--

DROP TABLE IF EXISTS `neksomo_llp_piece_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `neksomo_llp_piece_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `effective_date` date NOT NULL,
  `rate_per_piece` decimal(14,6) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_type` enum('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_date` (`product_id`,`effective_date`),
  KEY `idx_product` (`product_id`),
  KEY `idx_sale_date` (`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `neksomo_manufacturer_purchases`
--

DROP TABLE IF EXISTS `neksomo_manufacturer_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `neksomo_manufacturer_purchases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` int(10) unsigned NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `manufacturer_name` varchar(255) NOT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(16,6) NOT NULL,
  `total_taxable_value` decimal(16,6) NOT NULL,
  `total_gst_amount` decimal(16,6) NOT NULL,
  `quantity_packs` int(10) unsigned NOT NULL,
  `cost_per_piece` decimal(14,6) NOT NULL,
  `total_cost` decimal(16,6) NOT NULL,
  `stock_ledger_id` int(11) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_number` (`invoice_number`),
  KEY `idx_product` (`product_id`),
  KEY `idx_purchase_date` (`purchase_date`),
  KEY `idx_mp_vendor` (`vendor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `neksomo_product_mapping`
--

DROP TABLE IF EXISTS `neksomo_product_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `neksomo_product_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `neksomo_product_id` int(11) NOT NULL,
  `company_product_id` int(11) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mapping` (`neksomo_product_id`,`company_product_id`),
  KEY `idx_neksomo_product` (`neksomo_product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `neksomo_purchase_items`
--

DROP TABLE IF EXISTS `neksomo_purchase_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `neksomo_purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_packs` int(10) unsigned NOT NULL,
  `quantity_pieces` int(10) unsigned DEFAULT NULL,
  `cost_per_piece` decimal(14,6) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_type` enum('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
  `total_cost` decimal(16,6) NOT NULL,
  `taxable_value` decimal(16,6) NOT NULL,
  `gst_amount` decimal(16,6) NOT NULL,
  `stock_ledger_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_npi_purchase` (`purchase_id`),
  KEY `idx_npi_product` (`product_id`),
  CONSTRAINT `fk_npi_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `neksomo_manufacturer_purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `neksomo_stock_conversions`
--

DROP TABLE IF EXISTS `neksomo_stock_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `neksomo_stock_conversions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `neksomo_product_id` int(11) NOT NULL,
  `company_product_id` int(11) NOT NULL,
  `qty_neksomo_unit` int(11) NOT NULL,
  `qty_company_packs` int(11) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nsc_neksomo` (`neksomo_product_id`),
  KEY `idx_nsc_company` (`company_product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=292 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `neksomo_vendors`
--

DROP TABLE IF EXISTS `neksomo_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `neksomo_vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vendor_name` (`vendor_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `offers_manage`
--

DROP TABLE IF EXISTS `offers_manage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `offers_manage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `offer_title` varchar(255) NOT NULL,
  `offer_img` varchar(255) NOT NULL,
  `expired_date` date NOT NULL,
  `posted_date` date NOT NULL,
  `login_username` varchar(255) NOT NULL,
  `login_usertype` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ot_cat`
--

DROP TABLE IF EXISTS `ot_cat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ot_cat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cat` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ot_sales`
--

DROP TABLE IF EXISTS `ot_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ot_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `godownid` int(11) NOT NULL,
  `cat` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `date` date NOT NULL,
  `tempid` varchar(255) NOT NULL,
  `prid` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `discount` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL COMMENT 'MRP * Qty',
  `total` varchar(255) NOT NULL COMMENT 'Sub Total + GST Amount',
  `gst` int(11) NOT NULL,
  `gst_amount` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_mobile` varchar(255) NOT NULL,
  `customer_address` varchar(255) NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `amount_received` int(11) NOT NULL COMMENT '0=pending, 1=received',
  `amount_date` date NOT NULL COMMENT 'amount received date',
  `shipping_address` varchar(255) NOT NULL,
  `gst_number` varchar(255) NOT NULL,
  `order_date` date NOT NULL,
  `ship_date` date NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `state_id` int(11) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ot_sales_tempid` (`tempid`),
  KEY `idx_ot_sales_date` (`date`),
  KEY `idx_ot_sales_tempid_prid` (`tempid`,`prid`)
) ENGINE=InnoDB AUTO_INCREMENT=34724 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ot_sales_gst_backfill_backup_20260820`
--

DROP TABLE IF EXISTS `ot_sales_gst_backfill_backup_20260820`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ot_sales_gst_backfill_backup_20260820` (
  `id` int(11) NOT NULL DEFAULT 0,
  `godownid` int(11) NOT NULL,
  `cat` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `date` date NOT NULL,
  `tempid` varchar(255) NOT NULL,
  `prid` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `discount` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL COMMENT 'MRP * Qty',
  `total` varchar(255) NOT NULL COMMENT 'Sub Total + GST Amount',
  `gst` int(11) NOT NULL,
  `gst_amount` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_mobile` varchar(255) NOT NULL,
  `customer_address` varchar(255) NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `amount_received` int(11) NOT NULL COMMENT '0=pending, 1=received',
  `amount_date` date NOT NULL COMMENT 'amount received date',
  `shipping_address` varchar(255) NOT NULL,
  `gst_number` varchar(255) NOT NULL,
  `order_date` date NOT NULL,
  `ship_date` date NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `state_id` int(11) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ot_sales_invoice`
--

DROP TABLE IF EXISTS `ot_sales_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ot_sales_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `inv_id` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `cat` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `wallet_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` varchar(255) NOT NULL,
  `round_off` varchar(255) NOT NULL,
  `total` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `coupon_code` varchar(255) NOT NULL DEFAULT '0',
  `website_commission` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ot_sales_invoice_tempid` (`tempid`)
) ENGINE=InnoDB AUTO_INCREMENT=26979 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ot_sales_return`
--

DROP TABLE IF EXISTS `ot_sales_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ot_sales_return` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `prid` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `godownid` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `price` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1313 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `outlet`
--

DROP TABLE IF EXISTS `outlet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `outlet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(255) NOT NULL,
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` int(11) NOT NULL,
  `district_id` int(11) NOT NULL,
  `taluk_id` int(11) NOT NULL,
  `pincode_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `plan_amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `amount_method` varchar(255) NOT NULL,
  `amount_status` varchar(255) NOT NULL,
  `ref_number` varchar(255) NOT NULL,
  `account_status` varchar(255) NOT NULL,
  `merchantOrderId` varchar(255) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partner_location_layers`
--

DROP TABLE IF EXISTS `partner_location_layers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_location_layers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `depth` tinyint(3) unsigned NOT NULL,
  `layer_name` varchar(50) NOT NULL,
  `is_stock_location` tinyint(1) NOT NULL DEFAULT 0,
  `is_cp_filter_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `is_tp_filter_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `is_ms_filter_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_salesbdm_filter_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pll_depth` (`depth`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partner_location_nodes`
--

DROP TABLE IF EXISTS `partner_location_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_location_nodes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `depth` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `target_amount` decimal(12,2) DEFAULT NULL,
  `diaper_target_amount` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pln_parent` (`parent_id`),
  KEY `idx_pln_depth` (`depth`),
  CONSTRAINT `fk_pln_parent` FOREIGN KEY (`parent_id`) REFERENCES `partner_location_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1613 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partner_location_stock`
--

DROP TABLE IF EXISTS `partner_location_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_location_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `partner_location_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `opening_qty` int(11) NOT NULL DEFAULT 0,
  `input_qty` int(11) NOT NULL DEFAULT 0,
  `transfer_in_qty` int(11) NOT NULL DEFAULT 0,
  `transfer_out_qty` int(11) NOT NULL DEFAULT 0,
  `deduct_qty` int(11) NOT NULL DEFAULT 0,
  `closing_qty` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pls` (`partner_location_id`,`product_id`),
  KEY `idx_pls_product` (`product_id`),
  CONSTRAINT `fk_pls_location` FOREIGN KEY (`partner_location_id`) REFERENCES `partner_location_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partner_location_stock_ledger`
--

DROP TABLE IF EXISTS `partner_location_stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_location_stock_ledger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `partner_location_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `action` enum('opening','credit','deduct','transfer_in','transfer_out','adjustment') NOT NULL,
  `qty` int(11) NOT NULL,
  `qty_before` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `ref_type` enum('input','transfer','invoice','adjustment','opening','tp_invoice') NOT NULL,
  `ref_id` varchar(255) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plsl_loc_prod` (`partner_location_id`,`product_id`,`created_at`),
  KEY `idx_plsl_ref` (`ref_type`,`ref_id`),
  CONSTRAINT `fk_plsl_location` FOREIGN KEY (`partner_location_id`) REFERENCES `partner_location_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `paymentdetails`
--

DROP TABLE IF EXISTS `paymentdetails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paymentdetails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchantOrderId` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `transactionId` varchar(255) NOT NULL,
  `user_temp_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pincode`
--

DROP TABLE IF EXISTS `pincode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pincode` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `dist_id` int(11) NOT NULL,
  `taluk_id` int(11) NOT NULL,
  `pincode` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `assigned_SID` varchar(255) NOT NULL COMMENT 'Stockist ID',
  `assigned_DID` varchar(255) NOT NULL COMMENT 'Distributor ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2365 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pl_godown_transfer_items`
--

DROP TABLE IF EXISTS `pl_godown_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pl_godown_transfer_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plgti_transfer` (`transfer_id`),
  CONSTRAINT `fk_plgti_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `pl_godown_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pl_godown_transfers`
--

DROP TABLE IF EXISTS `pl_godown_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pl_godown_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_type` enum('godown_to_location','location_to_godown') NOT NULL,
  `godown_id` int(11) NOT NULL,
  `location_id` int(10) unsigned DEFAULT NULL,
  `cp_id` int(10) unsigned DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `ref_number` varchar(50) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plgt_godown` (`godown_id`),
  KEY `idx_plgt_location` (`location_id`),
  KEY `idx_plgt_type` (`transfer_type`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cat` varchar(255) NOT NULL COMMENT 'Super-Stockist,Stockist,Distributor',
  `amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `po_shipping_label_items`
--

DROP TABLE IF EXISTS `po_shipping_label_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `po_shipping_label_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label_id` int(10) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `product_text` varchar(255) NOT NULL,
  `packs_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_psli_label` (`label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `po_shipping_labels`
--

DROP TABLE IF EXISTS `po_shipping_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `po_shipping_labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `count_text` varchar(20) NOT NULL DEFAULT '',
  `source_text` varchar(100) NOT NULL DEFAULT '',
  `from_address` text DEFAULT NULL,
  `to_address` text DEFAULT NULL,
  `note_text` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psl_po` (`po_id`)
) ENGINE=InnoDB AUTO_INCREMENT=695 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(255) NOT NULL,
  `productName` varchar(255) NOT NULL,
  `pieces_per_pack` int(11) DEFAULT NULL,
  `packs_per_carton` int(11) DEFAULT NULL,
  `packs_per_cover` int(10) unsigned DEFAULT NULL,
  `unit_type` enum('pieces','pack') NOT NULL DEFAULT 'pieces',
  `category` enum('napkin','diaper') DEFAULT NULL,
  `mrp` int(11) NOT NULL,
  `supersstock_price` int(11) NOT NULL,
  `stockist_price` int(11) NOT NULL,
  `distributor_price` int(11) NOT NULL,
  `super_distributor_price` int(11) NOT NULL,
  `outlet_price` int(11) NOT NULL,
  `gst` int(11) NOT NULL,
  `gst_type` enum('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
  `hsn` varchar(255) NOT NULL,
  `rwpoints` float NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receipt`
--

DROP TABLE IF EXISTS `receipt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receiptid` varchar(255) NOT NULL,
  `inv_id` varchar(255) NOT NULL,
  `invoice_amount` int(11) NOT NULL,
  `received` int(11) NOT NULL,
  `receivable` int(11) NOT NULL,
  `date` date NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `receipt_method` varchar(255) NOT NULL,
  `receipt_remarks` varchar(255) NOT NULL,
  `payment_type` enum('advance_product','courier_charge','regular','credit_note') DEFAULT 'regular',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receipt_inv_id` (`inv_id`),
  KEY `idx_receipt_user_type` (`to_user_type`,`from_user_type`),
  KEY `idx_receipt_composite` (`to_user_type`,`inv_id`)
) ENGINE=InnoDB AUTO_INCREMENT=130789 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receipt_backup_20260131`
--

DROP TABLE IF EXISTS `receipt_backup_20260131`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_backup_20260131` (
  `id` int(11) NOT NULL DEFAULT 0,
  `receiptid` varchar(255) NOT NULL,
  `inv_id` varchar(255) NOT NULL,
  `invoice_amount` int(11) NOT NULL,
  `received` int(11) NOT NULL,
  `receivable` int(11) NOT NULL,
  `date` date NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `receipt_method` varchar(255) NOT NULL,
  `receipt_remarks` varchar(255) NOT NULL,
  `payment_type` enum('advance_product','courier_charge','regular') DEFAULT 'regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receipt_backup_before_reset_20260131`
--

DROP TABLE IF EXISTS `receipt_backup_before_reset_20260131`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_backup_before_reset_20260131` (
  `id` int(11) NOT NULL DEFAULT 0,
  `receiptid` varchar(255) NOT NULL,
  `inv_id` varchar(255) NOT NULL,
  `invoice_amount` int(11) NOT NULL,
  `received` int(11) NOT NULL,
  `receivable` int(11) NOT NULL,
  `date` date NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `receipt_method` varchar(255) NOT NULL,
  `receipt_remarks` varchar(255) NOT NULL,
  `payment_type` enum('advance_product','courier_charge','regular') DEFAULT 'regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receipt_backup_cleanup_20260131`
--

DROP TABLE IF EXISTS `receipt_backup_cleanup_20260131`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_backup_cleanup_20260131` (
  `id` int(11) NOT NULL DEFAULT 0,
  `receiptid` varchar(255) NOT NULL,
  `inv_id` varchar(255) NOT NULL,
  `invoice_amount` int(11) NOT NULL,
  `received` int(11) NOT NULL,
  `receivable` int(11) NOT NULL,
  `date` date NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `receipt_method` varchar(255) NOT NULL,
  `receipt_remarks` varchar(255) NOT NULL,
  `payment_type` enum('advance_product','courier_charge','regular') DEFAULT 'regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `referral_percentage_options`
--

DROP TABLE IF EXISTS `referral_percentage_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_percentage_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `percentage` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pct` (`percentage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `remapping_audit_log`
--

DROP TABLE IF EXISTS `remapping_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remapping_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(11) NOT NULL COMMENT 'ID of admin who performed the remapping',
  `from_user_type` varchar(50) NOT NULL COMMENT 'Source user type',
  `from_user_id` varchar(50) NOT NULL COMMENT 'Source user ID',
  `to_user_type` varchar(50) NOT NULL COMMENT 'Target user type',
  `to_user_id` varchar(50) NOT NULL COMMENT 'Target user ID',
  `customer_ids` text NOT NULL COMMENT 'Comma-separated list of remapped customer IDs',
  `customer_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of customers remapped',
  `action_date` datetime NOT NULL COMMENT 'When the remapping occurred',
  `action_type` varchar(50) NOT NULL DEFAULT 'customer_remap' COMMENT 'Type of remapping action',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_user` (`admin_user_id`),
  KEY `idx_from_user` (`from_user_type`,`from_user_id`),
  KEY `idx_to_user` (`to_user_type`,`to_user_id`),
  KEY `idx_action_date` (`action_date`),
  KEY `idx_action_type` (`action_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit log for customer remapping activities';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `return_credit`
--

DROP TABLE IF EXISTS `return_credit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_credit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `credit_amount` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reward_points`
--

DROP TABLE IF EXISTS `reward_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reward_points` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL COMMENT 'temp_id from user tables (super_stockiest, stockiest, distributor, etc.)',
  `user_type` enum('super_stockiest','stockiest','distributor','super_distributor','c_and_f','territory_partner') NOT NULL,
  `points` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Points earned (positive) or redeemed (negative)',
  `transaction_type` enum('bonus_target_achievement','referral_bonus','purchase_bonus','manual_adjustment','redemption','expiry','rollback','other') NOT NULL DEFAULT 'other' COMMENT 'Type of points transaction',
  `transaction_id` varchar(100) DEFAULT NULL COMMENT 'Reference to related transaction (execution_id, invoice_id, etc.)',
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date of the transaction',
  `description` text DEFAULT NULL COMMENT 'Detailed description of the transaction',
  `invoice_id` varchar(50) DEFAULT NULL COMMENT 'Related invoice ID if applicable',
  `invoice_number` varchar(100) DEFAULT NULL COMMENT 'Invoice number for reference',
  `reference_user_id` varchar(50) DEFAULT NULL COMMENT 'User ID if points from referral',
  `reference_user_type` varchar(50) DEFAULT NULL COMMENT 'User type if points from referral',
  `expiry_date` date DEFAULT NULL COMMENT 'Date when points expire (if applicable)',
  `is_expired` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether points have expired',
  `is_redeemed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether points have been redeemed',
  `redemption_date` datetime DEFAULT NULL COMMENT 'Date when points were redeemed',
  `redemption_amount` decimal(10,2) DEFAULT NULL COMMENT 'Cash value of redeemed points',
  `redemption_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number for redemption',
  `created_by_user_id` varchar(50) NOT NULL COMMENT 'User ID who created this entry',
  `created_by_user_type` varchar(50) NOT NULL COMMENT 'User type who created this entry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  `notes` text DEFAULT NULL COMMENT 'Additional notes or remarks',
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`user_type`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_expiry` (`expiry_date`,`is_expired`),
  KEY `idx_redeemed` (`is_redeemed`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_points` (`points`),
  KEY `idx_user_active` (`user_id`,`user_type`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=860 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reward points ledger for tracking all point transactions - earnings, redemptions, and adjustments';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales_bdm_staff`
--

DROP TABLE IF EXISTS `sales_bdm_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_bdm_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bdm_name` varchar(255) NOT NULL,
  `bdm_mobile` varchar(255) NOT NULL,
  `fixed_note_district` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `bdm_email` varchar(255) DEFAULT NULL,
  `bdm_address` varchar(255) DEFAULT NULL,
  `country_code` varchar(255) DEFAULT NULL,
  `account_status` varchar(255) NOT NULL DEFAULT 'active',
  `user_position` int(11) DEFAULT NULL,
  `team_level_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `monthly_target_amount` decimal(12,2) DEFAULT NULL,
  `espo_user_id` varchar(24) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bdm_mobile` (`bdm_mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salesbdm_company_bridge`
--

DROP TABLE IF EXISTS `salesbdm_company_bridge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salesbdm_company_bridge` (
  `token` varchar(64) NOT NULL,
  `bdm_id` int(11) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salesbdm_district_notes`
--

DROP TABLE IF EXISTS `salesbdm_district_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salesbdm_district_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bdm_id` int(11) NOT NULL,
  `district` varchar(150) NOT NULL,
  `note_type` enum('software','tp') NOT NULL DEFAULT 'tp',
  `issue_text` text NOT NULL,
  `priority` enum('high','priority','normal') NOT NULL DEFAULT 'normal',
  `status` enum('open','in_progress','completed') NOT NULL DEFAULT 'open',
  `resolution_note` varchar(500) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `tp_names` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bdm_created` (`bdm_id`,`created_at`),
  KEY `idx_district` (`district`),
  KEY `idx_priority` (`priority`),
  KEY `idx_note_type` (`note_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salesbdm_locations`
--

DROP TABLE IF EXISTS `salesbdm_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salesbdm_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bdm_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `is_dual_role` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bdm_location` (`bdm_id`,`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salesbdm_login_switch_bridge`
--

DROP TABLE IF EXISTS `salesbdm_login_switch_bridge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salesbdm_login_switch_bridge` (
  `token` varchar(64) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salesbdm_team_levels`
--

DROP TABLE IF EXISTS `salesbdm_team_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salesbdm_team_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_rank` int(11) NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `location_layer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level_rank` (`level_rank`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop`
--

DROP TABLE IF EXISTS `shop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `distributor_id` varchar(255) NOT NULL COMMENT 'stockiest id',
  `temp_id` varchar(255) NOT NULL,
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` varchar(255) NOT NULL,
  `district_id` varchar(255) NOT NULL,
  `taluk_id` varchar(255) NOT NULL,
  `firka_id` varchar(255) NOT NULL DEFAULT '',
  `pincode_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `plan_amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `amount_method` varchar(255) NOT NULL,
  `amount_status` varchar(255) NOT NULL COMMENT 'pending / paid',
  `ref_number` varchar(255) NOT NULL COMMENT 'amount trasnfer ref number (or) coupon number',
  `account_status` varchar(255) NOT NULL COMMENT 'pending / active / deactive',
  `merchantOrderId` varchar(255) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `shop_cat` int(11) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `landline` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `source_ms_shop_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_valid` (`valid_from`),
  KEY `idx_shop_state_temp` (`state_id`,`temp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27859 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_category`
--

DROP TABLE IF EXISTS `shop_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `catlable` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_competitor_stock`
--

DROP TABLE IF EXISTS `shop_competitor_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_competitor_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `shop_id` varchar(255) NOT NULL,
  `brandid` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `cst_panty` varchar(255) NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_current_stock`
--

DROP TABLE IF EXISTS `shop_current_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_current_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `shop_id` varchar(255) NOT NULL,
  `prid` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63379 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_invoice_change_log`
--

DROP TABLE IF EXISTS `shop_invoice_change_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_invoice_change_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `pr_id` int(11) DEFAULT NULL,
  `change_type` enum('initial','added','removed','qty_changed','voided') NOT NULL,
  `qty_before` int(11) DEFAULT NULL,
  `qty_after` int(11) DEFAULT NULL,
  `changed_by_user_type` varchar(50) NOT NULL,
  `changed_by_user_id` varchar(50) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inv_id` (`inv_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9805 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `state`
--

DROP TABLE IF EXISTS `state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `st_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `opening_qty` int(11) NOT NULL,
  `opening_date` date NOT NULL,
  `input_qty` int(11) NOT NULL,
  `sales_qty` int(11) NOT NULL,
  `sent_qty` int(11) NOT NULL,
  `returnqty` int(11) NOT NULL,
  `closing_qty` int(11) NOT NULL,
  `extra_pieces` int(10) unsigned NOT NULL DEFAULT 0,
  `user_type` varchar(255) NOT NULL COMMENT 'company,super_stockiest,stockiest,distributor',
  `user_id` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_entity` (`product_id`,`user_type`,`user_id`),
  KEY `idx_stock_user_type` (`user_type`),
  KEY `idx_stock_lookup` (`product_id`,`user_type`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_ledger`
--

DROP TABLE IF EXISTS `stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `action` enum('deduct','credit','reverse_deduct','reverse_credit','transfer_out','transfer_in','transfer_out_reverse','transfer_in_reverse','return_accept','return_reject','ot_deduct','ot_reverse') NOT NULL,
  `qty` int(11) NOT NULL,
  `qty_before` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `ref_type` enum('invoice','user_invoice','return','transfer','ot_sale','adjustment','demofree','tp_invoice') NOT NULL,
  `ref_id` varchar(255) NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ledger_ref` (`ref_type`,`ref_id`),
  KEY `idx_ledger_stock` (`product_id`,`user_type`,`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9333 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_request`
--

DROP TABLE IF EXISTS `stock_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reqid` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `fromusertype` varchar(255) NOT NULL,
  `fromuserid` varchar(255) NOT NULL,
  `tousertype` varchar(255) NOT NULL,
  `touserid` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL COMMENT 'pending (or) billed',
  `inv_id` varchar(255) NOT NULL,
  `screenshot` varchar(255) NOT NULL,
  `screenshot2` varchar(255) NOT NULL,
  `verified` int(11) NOT NULL,
  `reqtype` varchar(255) NOT NULL,
  `delivery_address` varchar(255) NOT NULL,
  `amount` varchar(255) NOT NULL,
  `utr` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=474 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_request_items`
--

DROP TABLE IF EXISTS `stock_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reqid` varchar(255) NOT NULL,
  `prid` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `fromusertype` varchar(255) NOT NULL,
  `fromuserid` varchar(255) NOT NULL,
  `tousertype` varchar(255) NOT NULL,
  `touserid` varchar(255) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `gst` varchar(255) NOT NULL,
  `gsttotal` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stockiest`
--

DROP TABLE IF EXISTS `stockiest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stockiest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ss_id` varchar(255) NOT NULL COMMENT 'super stockiest id',
  `temp_id` varchar(255) NOT NULL COMMENT 'Stockist ID',
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` int(11) NOT NULL,
  `district_id` int(11) NOT NULL,
  `taluk_id` varchar(255) NOT NULL,
  `pincode_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last successful login time',
  `plan_amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `amount_method` varchar(255) NOT NULL,
  `amount_status` varchar(255) NOT NULL COMMENT 'pending / paid',
  `ref_number` varchar(255) NOT NULL COMMENT 'amount trasnfer ref number (or) coupon number',
  `account_status` varchar(255) NOT NULL COMMENT 'pending / active / deactive',
  `merchantOrderId` varchar(255) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stockiest_valid` (`valid_from`),
  KEY `idx_stock_state_temp` (`state_id`,`temp_id`),
  KEY `idx_mobile` (`mobile_number`)
) ENGINE=InnoDB AUTO_INCREMENT=387 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stockiest_userid_backup_20260226`
--

DROP TABLE IF EXISTS `stockiest_userid_backup_20260226`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stockiest_userid_backup_20260226` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `backed_up_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stockist_category`
--

DROP TABLE IF EXISTS `stockist_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stockist_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `catname` varchar(255) NOT NULL,
  `target_amount` int(11) NOT NULL,
  `ref_commission_percentage` int(11) NOT NULL,
  `cash_back_percentage` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stockist_referral`
--

DROP TABLE IF EXISTS `stockist_referral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stockist_referral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stockist_id` varchar(255) NOT NULL,
  `st_cat_id` int(11) NOT NULL,
  `st_ref_type` varchar(255) NOT NULL,
  `st_ref_userid` varchar(255) NOT NULL,
  `updated` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=387 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `super_distributor`
--

DROP TABLE IF EXISTS `super_distributor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_distributor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stockiest_id` varchar(255) NOT NULL COMMENT 'stockiest id',
  `temp_id` varchar(255) NOT NULL COMMENT 'Distributor ID',
  `category_id` int(11) NOT NULL DEFAULT 1 COMMENT 'Category ID from super_distributor_category table',
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` varchar(255) NOT NULL,
  `district_id` varchar(255) NOT NULL,
  `taluk_id` varchar(255) NOT NULL,
  `pincode_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last successful login time',
  `plan_amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `amount_method` varchar(255) NOT NULL,
  `amount_status` varchar(255) NOT NULL COMMENT 'pending / paid',
  `ref_number` varchar(255) NOT NULL COMMENT 'amount trasnfer ref number (or) coupon number',
  `account_status` varchar(255) NOT NULL COMMENT 'pending / active / deactive',
  `merchantOrderId` varchar(255) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `shop_onboard` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_super_distributor_valid` (`valid_from`),
  KEY `idx_sd_state_temp` (`state_id`,`temp_id`),
  KEY `idx_mobile` (`mobile_number`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `super_distributor_category`
--

DROP TABLE IF EXISTS `super_distributor_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_distributor_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `ref_commission_percentage` varchar(10) NOT NULL,
  `cash_back_percentage` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `super_distributor_referral`
--

DROP TABLE IF EXISTS `super_distributor_referral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_distributor_referral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sd_id` varchar(255) NOT NULL COMMENT 'Super Distribor ID',
  `sd_cat_id` int(11) NOT NULL DEFAULT 0,
  `target_amount` int(11) NOT NULL,
  `ref_by_user_type` varchar(255) NOT NULL,
  `ref_by_user_id` varchar(255) NOT NULL,
  `updated` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `super_stockiest`
--

DROP TABLE IF EXISTS `super_stockiest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_stockiest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(255) NOT NULL,
  `user_icon` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` int(11) NOT NULL,
  `district_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last successful login time',
  `plan_amount` int(11) NOT NULL,
  `valid_months` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `amount_method` varchar(255) NOT NULL,
  `amount_status` varchar(255) NOT NULL COMMENT 'pending / paid',
  `ref_number` varchar(255) NOT NULL COMMENT 'amount trasnfer ref number (or) coupon number',
  `account_status` varchar(255) NOT NULL COMMENT 'pending / active / deactive',
  `merchantOrderId` varchar(255) NOT NULL,
  `merchantTransactionId` varchar(255) NOT NULL,
  `merchantUserId` varchar(255) NOT NULL,
  `gstin` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `userid` int(11) NOT NULL,
  `useridtext` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_super_stockiest_valid` (`valid_from`),
  KEY `idx_ss_state_temp` (`state_id`,`temp_id`),
  KEY `idx_mobile` (`mobile_number`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `super_stockiest_category`
--

DROP TABLE IF EXISTS `super_stockiest_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_stockiest_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `district_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Category name',
  `target_amount` int(11) NOT NULL DEFAULT 0 COMMENT 'Target amount for this category',
  `ref_commission_percentage` int(11) NOT NULL DEFAULT 0 COMMENT 'Referral commission percentage',
  `cash_back_percentage` int(11) NOT NULL DEFAULT 0 COMMENT 'Cash back percentage',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ss_category_district` (`district_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Super Stockiest category definitions with target amounts';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `super_stockiest_referral`
--

DROP TABLE IF EXISTS `super_stockiest_referral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_stockiest_referral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `super_stockiest_id` varchar(255) NOT NULL COMMENT 'Super Stockiest temp_id',
  `ss_cat_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Super Stockiest category ID',
  `target_amount` int(11) NOT NULL DEFAULT 0 COMMENT 'Individual target amount override (0 = use category target)',
  `ref_by_user_type` varchar(255) NOT NULL DEFAULT '' COMMENT 'Referrer user type',
  `ref_by_user_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Referrer user ID',
  `updated` int(11) NOT NULL DEFAULT 0 COMMENT 'Update flag',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_super_stockiest_id` (`super_stockiest_id`),
  KEY `idx_ss_cat_id` (`ss_cat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Super Stockiest referral and target amount information';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `taluk`
--

DROP TABLE IF EXISTS `taluk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taluk` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state_id` int(11) NOT NULL,
  `dist_id` int(11) NOT NULL,
  `taluk` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `assigned_SID` varchar(255) NOT NULL COMMENT 'Stockist ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=530 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `temp_competerion_stock_report`
--

DROP TABLE IF EXISTS `temp_competerion_stock_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_competerion_stock_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `qty` int(11) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=944720 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `temp_not_purchased`
--

DROP TABLE IF EXISTS `temp_not_purchased`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_not_purchased` (
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `purchse_count` int(11) NOT NULL,
  `searchtype` varchar(255) NOT NULL,
  `onboard_userTYPE` varchar(255) NOT NULL,
  `onboard_userID` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `temp_report`
--

DROP TABLE IF EXISTS `temp_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `pr_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `temp_stocks_stockist`
--

DROP TABLE IF EXISTS `temp_stocks_stockist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_stocks_stockist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usertype` varchar(255) NOT NULL,
  `userid` varchar(255) NOT NULL,
  `stocks` int(11) NOT NULL,
  `ss_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1003522 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `territory_partner_locations`
--

DROP TABLE IF EXISTS `territory_partner_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `territory_partner_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `location_id` int(10) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tpl_location` (`location_id`),
  KEY `idx_tpl_partner` (`territory_partner_id`),
  CONSTRAINT `fk_tpl_location` FOREIGN KEY (`location_id`) REFERENCES `partner_location_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpl_partner` FOREIGN KEY (`territory_partner_id`) REFERENCES `territory_partners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2072 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `territory_partner_stock`
--

DROP TABLE IF EXISTS `territory_partner_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `territory_partner_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `opening_qty` int(11) NOT NULL DEFAULT 0,
  `input_qty` int(11) NOT NULL DEFAULT 0,
  `deduct_qty` int(11) NOT NULL DEFAULT 0,
  `closing_qty` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tp_product` (`territory_partner_id`,`product_id`),
  KEY `idx_tps_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `territory_partner_stock_ledger`
--

DROP TABLE IF EXISTS `territory_partner_stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `territory_partner_stock_ledger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `action` enum('opening','credit','deduct','adjustment','return','internal_transfer_in','internal_transfer_in_reverse') NOT NULL,
  `qty` int(11) NOT NULL,
  `qty_before` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `ref_type` enum('tp_invoice','adjustment','opening','manual_input','demofree','credit_note','internal_transfer') NOT NULL,
  `ref_id` varchar(255) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tpsl_tp` (`territory_partner_id`),
  KEY `idx_tpsl_ref` (`ref_type`)
) ENGINE=InnoDB AUTO_INCREMENT=39451 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `territory_partners`
--

DROP TABLE IF EXISTS `territory_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `territory_partners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tp_id` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `referral_id` varchar(100) DEFAULT NULL,
  `referral_type` varchar(20) DEFAULT NULL,
  `referral_percentage` decimal(5,2) DEFAULT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `branch_line1` varchar(255) DEFAULT NULL,
  `branch_line2` varchar(255) DEFAULT NULL,
  `branch_city` varchar(100) DEFAULT NULL,
  `branch_district` varchar(100) DEFAULT NULL,
  `assigned_district` varchar(100) DEFAULT NULL,
  `branch_state` varchar(100) DEFAULT NULL,
  `branch_country` varchar(100) DEFAULT NULL,
  `branch_pincode` varchar(20) DEFAULT NULL,
  `delivery_line1` varchar(255) DEFAULT NULL,
  `delivery_line2` varchar(255) DEFAULT NULL,
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_district` varchar(100) DEFAULT NULL,
  `delivery_state` varchar(100) DEFAULT NULL,
  `delivery_country` varchar(100) DEFAULT NULL,
  `delivery_pincode` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `allow_self_pickup_napkin` tinyint(1) NOT NULL DEFAULT 0,
  `allow_self_pickup_diaper` tinyint(1) NOT NULL DEFAULT 0,
  `allow_self_pickup` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `updated_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `onboard_ss_id` varchar(50) DEFAULT NULL,
  `stock_initialized` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tp_id` (`tp_id`),
  UNIQUE KEY `uk_tp_mobile` (`mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=286 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tmp_broken_invoices`
--

DROP TABLE IF EXISTS `tmp_broken_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tmp_broken_invoices` (
  `inv_id` varchar(255) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `invoice_total` varchar(255) NOT NULL,
  `receipt_total` decimal(32,0) NOT NULL DEFAULT 0,
  `diff` double NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `top_performar`
--

DROP TABLE IF EXISTS `top_performar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `top_performar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tempid` varchar(255) NOT NULL,
  `particulars` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `per_photo` varchar(255) NOT NULL,
  `posted_date` date NOT NULL,
  `login_username` varchar(255) NOT NULL,
  `login_usertype` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_advance_payment_screenshot_ocr_corrections`
--

DROP TABLE IF EXISTS `tp_advance_payment_screenshot_ocr_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_advance_payment_screenshot_ocr_corrections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `screenshot_id` int(10) unsigned NOT NULL,
  `field` varchar(20) NOT NULL,
  `engine` varchar(20) NOT NULL DEFAULT 'google_vision',
  `wrong_value` varchar(255) DEFAULT NULL,
  `correct_value` varchar(255) NOT NULL,
  `raw_text_hash` char(64) NOT NULL,
  `ocr_raw_text` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tpapsoc_hash` (`raw_text_hash`),
  KEY `idx_tpapsoc_field` (`field`),
  KEY `idx_tpapsoc_engine` (`engine`),
  KEY `fk_tpapsoc_screenshot` (`screenshot_id`),
  CONSTRAINT `fk_tpapsoc_screenshot` FOREIGN KEY (`screenshot_id`) REFERENCES `tp_advance_payment_screenshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=391 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_advance_payment_screenshots`
--

DROP TABLE IF EXISTS `tp_advance_payment_screenshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_advance_payment_screenshots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `detected_amount` decimal(12,2) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `ocr_raw_text` mediumtext DEFAULT NULL,
  `status` enum('accepted','pending_review','rejected') NOT NULL DEFAULT 'pending_review',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tpaps_status` (`status`),
  KEY `idx_tpaps_refnum` (`reference_number`),
  KEY `idx_tpaps_submission` (`submission_id`),
  CONSTRAINT `fk_tpaps_submission` FOREIGN KEY (`submission_id`) REFERENCES `tp_advance_payment_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=570 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_advance_payment_submissions`
--

DROP TABLE IF EXISTS `tp_advance_payment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_advance_payment_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_type` enum('napkin','diaper') NOT NULL DEFAULT 'napkin',
  `approver_type` enum('company','ss') NOT NULL DEFAULT 'company',
  `approver_ss_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_mode` varchar(50) NOT NULL,
  `reference_number` varchar(255) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `source` varchar(10) NOT NULL DEFAULT 'direct',
  `status` enum('draft','pending_review','accepted','rejected') NOT NULL DEFAULT 'draft',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `advance_payment_id` int(10) unsigned DEFAULT NULL,
  `used_for_po_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tpapsub_tp` (`territory_partner_id`),
  KEY `idx_tpapsub_status` (`status`),
  KEY `idx_tpapsub_advpay` (`advance_payment_id`),
  KEY `idx_tpaps_approver` (`approver_type`,`approver_ss_id`),
  CONSTRAINT `fk_tpapsub_advpay` FOREIGN KEY (`advance_payment_id`) REFERENCES `tp_advance_payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=486 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_advance_payments`
--

DROP TABLE IF EXISTS `tp_advance_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_advance_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_type` enum('napkin','diaper') NOT NULL DEFAULT 'napkin',
  `product_type_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `approver_type` enum('company','ss') NOT NULL DEFAULT 'company',
  `approver_ss_id` int(10) unsigned DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_mode` varchar(50) NOT NULL,
  `reference_number` varchar(255) DEFAULT '',
  `bank_name` varchar(255) DEFAULT '',
  `remarks` text DEFAULT NULL,
  `adjusted_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(12,2) NOT NULL,
  `status` enum('active','partially_adjusted','fully_adjusted') NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(100) DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tp_id` (`territory_partner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`payment_date`),
  KEY `idx_tpap_company` (`company_id`),
  KEY `idx_tpap_approver` (`approver_type`,`approver_ss_id`),
  KEY `idx_tpap_ptype` (`territory_partner_id`,`product_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1366 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_courier_amount_requests`
--

DROP TABLE IF EXISTS `tp_courier_amount_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_courier_amount_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_type` enum('napkin','diaper') NOT NULL,
  `total_boxes` int(10) unsigned NOT NULL,
  `total_covers` int(10) unsigned NOT NULL DEFAULT 0,
  `calculated_amount` decimal(10,2) NOT NULL,
  `approved_amount` decimal(10,2) DEFAULT NULL,
  `cart_snapshot` text DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by_bdm_id` int(10) unsigned DEFAULT NULL,
  `reviewed_by_name` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `applied_po_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tcar_tp` (`territory_partner_id`,`status`),
  KEY `idx_tcar_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_courier_payments`
--

DROP TABLE IF EXISTS `tp_courier_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_courier_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_type` enum('napkin','diaper') NOT NULL,
  `total_boxes` int(10) unsigned NOT NULL,
  `total_covers` int(10) unsigned NOT NULL DEFAULT 0,
  `required_amount` decimal(10,2) NOT NULL,
  `detected_amount` decimal(10,2) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `ocr_raw_text` text DEFAULT NULL,
  `image_hash` char(64) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending_review','accepted','rejected') NOT NULL DEFAULT 'pending_review',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `po_id` int(10) unsigned DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tcp_pool` (`territory_partner_id`,`product_type`,`po_id`),
  KEY `idx_tcp_po` (`po_id`),
  KEY `idx_tcp_hash` (`image_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_district_notes`
--

DROP TABLE IF EXISTS `tp_district_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_district_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tp_id` int(11) NOT NULL,
  `district` varchar(150) NOT NULL,
  `note_type` enum('software','tp') NOT NULL DEFAULT 'tp',
  `issue_text` text NOT NULL,
  `priority` enum('high','priority','normal') NOT NULL DEFAULT 'normal',
  `status` enum('open','in_progress','completed') NOT NULL DEFAULT 'open',
  `resolution_note` varchar(500) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tp_created` (`tp_id`,`created_at`),
  KEY `idx_district` (`district`),
  KEY `idx_priority` (`priority`),
  KEY `idx_note_type` (`note_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_id_sequence`
--

DROP TABLE IF EXISTS `tp_id_sequence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_id_sequence` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `last_val` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_inv_sequence`
--

DROP TABLE IF EXISTS `tp_inv_sequence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_inv_sequence` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `source` varchar(10) NOT NULL DEFAULT '',
  `last_val` int(10) unsigned NOT NULL DEFAULT 0,
  `fy` varchar(5) NOT NULL DEFAULT '',
  PRIMARY KEY (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_invoice_advance_log`
--

DROP TABLE IF EXISTS `tp_invoice_advance_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_invoice_advance_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tp_invoice_id` int(10) unsigned NOT NULL,
  `tp_invoice_number` varchar(100) NOT NULL,
  `tp_advance_id` int(10) unsigned NOT NULL,
  `deducted_amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_log_invoice` (`tp_invoice_id`),
  KEY `idx_log_advance` (`tp_advance_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1470 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_invoice_items`
--

DROP TABLE IF EXISTS `tp_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_invoice_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tp_invoice_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `rwpoints` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_tpii_inv` (`tp_invoice_id`),
  CONSTRAINT `fk_tpii_inv` FOREIGN KEY (`tp_invoice_id`) REFERENCES `tp_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3337 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_invoice_receipts`
--

DROP TABLE IF EXISTS `tp_invoice_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_invoice_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tp_invoice_id` int(10) unsigned NOT NULL,
  `invoice_number` varchar(30) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `receipt_date` date NOT NULL,
  `payment_mode` varchar(50) NOT NULL,
  `remarks` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tpinv` (`tp_invoice_id`),
  CONSTRAINT `fk_tprcpt_inv` FOREIGN KEY (`tp_invoice_id`) REFERENCES `tp_invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_invoices`
--

DROP TABLE IF EXISTS `tp_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(30) NOT NULL,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_type` enum('napkin','diaper') NOT NULL DEFAULT 'napkin',
  `approver_type` enum('company','ss') NOT NULL DEFAULT 'company',
  `approver_ss_id` int(10) unsigned DEFAULT NULL,
  `source_location_id` int(10) unsigned DEFAULT NULL,
  `source_cp_id` int(10) unsigned DEFAULT NULL,
  `source_godown_id` int(10) unsigned DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `courier_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rwpoints_enable` tinyint(1) NOT NULL DEFAULT 1,
  `use_default_delivery_address` tinyint(1) NOT NULL DEFAULT 1,
  `custom_delivery_line1` varchar(255) DEFAULT NULL,
  `custom_delivery_line2` varchar(255) DEFAULT NULL,
  `custom_delivery_city` varchar(100) DEFAULT NULL,
  `custom_delivery_district` varchar(100) DEFAULT NULL,
  `custom_delivery_state` varchar(100) DEFAULT NULL,
  `custom_delivery_country` varchar(100) DEFAULT NULL,
  `custom_delivery_pincode` varchar(20) DEFAULT NULL,
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_by_user_type` varchar(30) NOT NULL DEFAULT '',
  `created_by_user_id` varchar(30) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tpi_tp` (`territory_partner_id`),
  KEY `idx_tpi_loc` (`source_location_id`),
  KEY `idx_tpi_creator` (`created_by_user_type`,`created_by_user_id`,`invoice_number`),
  KEY `idx_tpi_approver` (`approver_type`,`approver_ss_id`),
  CONSTRAINT `fk_tpi_loc` FOREIGN KEY (`source_location_id`) REFERENCES `partner_location_nodes` (`id`),
  CONSTRAINT `fk_tpi_tp` FOREIGN KEY (`territory_partner_id`) REFERENCES `territory_partners` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=903 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_orders`
--

DROP TABLE IF EXISTS `tp_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(60) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `new_order` enum('yes','no') NOT NULL,
  `noorder_reason` varchar(500) NOT NULL DEFAULT 'nil',
  `marketing_tool` varchar(500) NOT NULL DEFAULT '',
  `pr_id` int(11) NOT NULL DEFAULT 0,
  `qty` int(11) NOT NULL DEFAULT 0,
  `invoiced_inv_id` varchar(60) DEFAULT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by_ms_id` int(11) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `voided_by_user_type` varchar(50) DEFAULT NULL,
  `voided_by_user_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tp_id_date` (`tp_id`,`order_date`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_new_order` (`new_order`),
  KEY `idx_invoiced_inv_id` (`invoiced_inv_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11081 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_po_screenshot_ocr_corrections`
--

DROP TABLE IF EXISTS `tp_po_screenshot_ocr_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_po_screenshot_ocr_corrections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `screenshot_id` int(10) unsigned NOT NULL,
  `field` varchar(20) NOT NULL,
  `engine` varchar(20) NOT NULL DEFAULT 'google_vision',
  `wrong_value` varchar(255) DEFAULT NULL,
  `correct_value` varchar(255) NOT NULL,
  `raw_text_hash` char(64) NOT NULL,
  `ocr_raw_text` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tppsoc_hash` (`raw_text_hash`),
  KEY `idx_tppsoc_field` (`field`),
  KEY `fk_tppsoc_screenshot` (`screenshot_id`),
  KEY `idx_tppsoc_engine` (`engine`),
  CONSTRAINT `fk_tppsoc_screenshot` FOREIGN KEY (`screenshot_id`) REFERENCES `tp_purchase_order_screenshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_purchase_order_items`
--

DROP TABLE IF EXISTS `tp_purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_purchase_order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `delivery_method` enum('pickup','courier') NOT NULL DEFAULT 'courier',
  `qty` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_tppoi_po` (`po_id`),
  KEY `idx_tppoi_product` (`product_id`),
  CONSTRAINT `fk_tppoi_po` FOREIGN KEY (`po_id`) REFERENCES `tp_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2697 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_purchase_order_screenshots`
--

DROP TABLE IF EXISTS `tp_purchase_order_screenshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_purchase_order_screenshots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned DEFAULT NULL,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `detected_amount` decimal(12,2) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `ocr_raw_text` mediumtext DEFAULT NULL,
  `status` enum('accepted','pending_review','rejected') NOT NULL DEFAULT 'pending_review',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `advance_payment_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tppos_tp` (`territory_partner_id`),
  KEY `idx_tppos_po` (`po_id`),
  KEY `idx_tppos_status` (`status`),
  KEY `idx_tppos_refnum` (`reference_number`),
  KEY `idx_tppos_advpay` (`advance_payment_id`),
  CONSTRAINT `fk_tppos_po` FOREIGN KEY (`po_id`) REFERENCES `tp_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=222 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tp_purchase_orders`
--

DROP TABLE IF EXISTS `tp_purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tp_purchase_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `territory_partner_id` int(10) unsigned NOT NULL,
  `product_type` enum('napkin','diaper') NOT NULL DEFAULT 'napkin',
  `approver_type` enum('company','ss') NOT NULL DEFAULT 'company',
  `approver_ss_id` int(10) unsigned DEFAULT NULL,
  `preferred_cp_id` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `status` enum('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting',
  `excess_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `use_default_delivery_address` tinyint(1) NOT NULL DEFAULT 1,
  `custom_delivery_line1` varchar(255) DEFAULT NULL,
  `custom_delivery_line2` varchar(255) DEFAULT NULL,
  `custom_delivery_city` varchar(100) DEFAULT NULL,
  `custom_delivery_district` varchar(100) DEFAULT NULL,
  `custom_delivery_state` varchar(100) DEFAULT NULL,
  `custom_delivery_country` varchar(100) DEFAULT NULL,
  `custom_delivery_pincode` varchar(20) DEFAULT NULL,
  `tp_invoice_id` int(10) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` varchar(100) DEFAULT NULL,
  `cancel_reason` varchar(500) DEFAULT NULL,
  `notes` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tppo_tp_date` (`territory_partner_id`,`order_date`),
  KEY `idx_tppo_status_date` (`status`,`order_date`),
  KEY `idx_tppo_invoice` (`tp_invoice_id`),
  KEY `idx_tppo_approver` (`approver_type`,`approver_ss_id`)
) ENGINE=InnoDB AUTO_INCREMENT=615 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `track_users`
--

DROP TABLE IF EXISTS `track_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `track_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_track_users_mobile` (`mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_invoice`
--

DROP TABLE IF EXISTS `user_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `source_ms_order_id` varchar(255) DEFAULT NULL,
  `id_only` int(11) NOT NULL,
  `inv_number` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `inv_year` int(11) NOT NULL,
  `sub_total` varchar(255) NOT NULL,
  `discount` varchar(255) NOT NULL,
  `credit` int(11) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL COMMENT 'inner state, outer state',
  `roundoff` varchar(255) NOT NULL,
  `courier_charges` int(11) NOT NULL,
  `rwpoints_enable` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','submitted','cancelled') DEFAULT 'draft',
  `voided_at` datetime DEFAULT NULL,
  `voided_by_user_type` varchar(50) DEFAULT NULL,
  `voided_by_user_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_from_usertype` (`from_user_type`,`from_user_id`),
  KEY `idx_user_invoice_date` (`date`),
  KEY `idx_user_invoice_to_user` (`to_user_type`,`date`),
  KEY `idx_user_invoice_date_total` (`date`,`to_user_type`,`sub_total`),
  KEY `idx_user_invoice_composite` (`to_user_type`,`date`,`sub_total`),
  KEY `idx_ui_date_state` (`date`,`to_user_type`,`from_user_type`),
  KEY `idx_ui_inv_id` (`inv_id`),
  KEY `idx_from_user` (`from_user_type`,`from_user_id`,`date`),
  KEY `idx_source_ms_order_id` (`source_ms_order_id`),
  KEY `idx_ui_to_user_id` (`to_user_type`(50),`to_user_id`(50),`from_user_type`(50),`date`)
) ENGINE=InnoDB AUTO_INCREMENT=59767 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_invoice_items`
--

DROP TABLE IF EXISTS `user_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` varchar(255) NOT NULL,
  `pr_id` int(11) NOT NULL,
  `amount` float NOT NULL,
  `qty` int(11) NOT NULL,
  `gst_percentage` int(11) NOT NULL,
  `gstamount_singlepr` varchar(255) NOT NULL,
  `gstamount_total` varchar(255) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `discount_percentage` varchar(255) NOT NULL,
  `discount_amount` varchar(255) NOT NULL,
  `total` varchar(255) NOT NULL,
  `to_user_type` varchar(255) NOT NULL,
  `to_user_id` varchar(255) NOT NULL,
  `from_user_type` varchar(255) NOT NULL,
  `from_user_id` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `hsn` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `rwpoints` float NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `rwpoints_sls` float NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_date` (`date`,`to_user_type`),
  KEY `idx_uii_inv_pr` (`inv_id`,`pr_id`),
  KEY `idx_invoice_product` (`inv_id`,`pr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=174972 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_return_stock`
--

DROP TABLE IF EXISTS `user_return_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_return_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `returnid` varchar(255) NOT NULL,
  `invnumber` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `subtotal` int(11) NOT NULL,
  `discount` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `from_usertype` varchar(255) NOT NULL COMMENT 'stock returned from',
  `from_userid` varchar(255) NOT NULL,
  `to_usertype` varchar(255) NOT NULL,
  `to_userid` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL COMMENT 'pending, accept, reject',
  `rwpoints_enable` int(11) NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_returnid` (`returnid`)
) ENGINE=InnoDB AUTO_INCREMENT=1273 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_return_stock_items`
--

DROP TABLE IF EXISTS `user_return_stock_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_return_stock_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `returnid` varchar(255) NOT NULL,
  `invnumber` varchar(255) NOT NULL,
  `prid` int(11) NOT NULL,
  `amount` float NOT NULL,
  `qty` int(11) NOT NULL,
  `subtotal` varchar(255) NOT NULL,
  `gst_percentage` varchar(255) NOT NULL,
  `gstamount_total` varchar(255) NOT NULL,
  `total` float NOT NULL,
  `from_usertype` varchar(255) NOT NULL,
  `from_userid` varchar(255) NOT NULL,
  `to_usertype` varchar(255) NOT NULL,
  `to_userid` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `status` varchar(255) NOT NULL COMMENT 'pending, accept, reject',
  `hsn` varchar(255) NOT NULL,
  `damaged_qty` int(11) NOT NULL,
  `rwpoints` float NOT NULL,
  `buyer_gsttype` varchar(255) NOT NULL,
  `gst_type` varchar(255) NOT NULL,
  `rwpoints_sls` float NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_return_product` (`returnid`,`prid`),
  KEY `idx_return_lookup` (`invnumber`,`prid`,`status`),
  KEY `idx_returnid` (`returnid`)
) ENGINE=InnoDB AUTO_INCREMENT=1630 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_profile`
--

DROP TABLE IF EXISTS `users_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_tempid` varchar(255) NOT NULL,
  `usertype` varchar(255) NOT NULL,
  `companyname` varchar(255) NOT NULL,
  `deliveryaddress` varchar(255) NOT NULL,
  `acname` varchar(255) NOT NULL,
  `acnumber` varchar(255) NOT NULL,
  `bankname` varchar(255) NOT NULL,
  `branchname` varchar(255) NOT NULL,
  `ifsc` varchar(255) NOT NULL,
  `upinumber` varchar(255) NOT NULL,
  `pannumber` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=549 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_remapping_audit_report`
--

DROP TABLE IF EXISTS `v_remapping_audit_report`;
/*!50001 DROP VIEW IF EXISTS `v_remapping_audit_report`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_remapping_audit_report` AS SELECT
 1 AS `id`,
  1 AS `admin_user_id`,
  1 AS `from_user_type`,
  1 AS `from_user_id`,
  1 AS `to_user_type`,
  1 AS `to_user_id`,
  1 AS `customer_count`,
  1 AS `action_date`,
  1 AS `action_type`,
  1 AS `action_date_formatted`,
  1 AS `action_time_formatted` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `wa_number_last_account`
--

DROP TABLE IF EXISTS `wa_number_last_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_number_last_account` (
  `wa_number` varchar(20) NOT NULL,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`wa_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_advance_payment_screenshots`
--

DROP TABLE IF EXISTS `wa_po_advance_payment_screenshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_advance_payment_screenshots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `detected_amount` decimal(12,2) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `ocr_raw_text` mediumtext DEFAULT NULL,
  `status` enum('accepted','pending_review','rejected') NOT NULL DEFAULT 'pending_review',
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wpaps2_submission` (`submission_id`),
  KEY `idx_wpaps2_status` (`status`),
  KEY `idx_wpaps2_refnum` (`reference_number`),
  CONSTRAINT `fk_wpaps2_submission` FOREIGN KEY (`submission_id`) REFERENCES `wa_po_advance_payment_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_advance_payment_submissions`
--

DROP TABLE IF EXISTS `wa_po_advance_payment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_advance_payment_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_mode` varchar(50) NOT NULL DEFAULT 'UPI',
  `reference_number` varchar(255) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `status` enum('draft','pending_review','accepted','rejected') NOT NULL DEFAULT 'pending_review',
  `used_for_po_id` int(10) unsigned DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wpaps_user` (`user_category`,`user_id`),
  KEY `idx_wpaps_status` (`status`),
  KEY `idx_wpaps_used_for_po` (`used_for_po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_advance_payments`
--

DROP TABLE IF EXISTS `wa_po_advance_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_advance_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_amount` decimal(12,2) NOT NULL,
  `status` enum('active','fully_adjusted') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wpap_user` (`user_category`,`user_id`),
  KEY `idx_wpap_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_linked_numbers`
--

DROP TABLE IF EXISTS `wa_po_linked_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_linked_numbers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `wa_number` varchar(20) NOT NULL,
  `linked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wpln_cat_user_number` (`user_category`,`user_id`,`wa_number`),
  KEY `idx_wpln_number` (`wa_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_otp_attempts`
--

DROP TABLE IF EXISTS `wa_po_otp_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_otp_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `otp_id` varchar(40) NOT NULL,
  `otp_code_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts_used` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wpoa_otp_id` (`otp_id`),
  KEY `idx_wpoa_user` (`user_category`,`user_id`),
  KEY `idx_wpoa_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_otp_rate_limit`
--

DROP TABLE IF EXISTS `wa_po_otp_rate_limit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_otp_rate_limit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `window_start` datetime NOT NULL,
  `send_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wporl_user_window` (`user_category`,`user_id`,`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_payment_screenshot_ocr_corrections`
--

DROP TABLE IF EXISTS `wa_po_payment_screenshot_ocr_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_payment_screenshot_ocr_corrections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `screenshot_id` int(10) unsigned NOT NULL,
  `field` varchar(20) NOT NULL,
  `engine` varchar(20) NOT NULL DEFAULT 'claude_vision',
  `wrong_value` varchar(255) DEFAULT NULL,
  `correct_value` varchar(255) NOT NULL,
  `raw_text_hash` char(64) NOT NULL,
  `ocr_raw_text` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wppsoc_screenshot` (`screenshot_id`),
  KEY `idx_wppsoc_field` (`field`),
  KEY `idx_wppsoc_engine` (`engine`),
  KEY `idx_wppsoc_hash` (`raw_text_hash`),
  CONSTRAINT `fk_wppsoc_screenshot` FOREIGN KEY (`screenshot_id`) REFERENCES `wa_po_advance_payment_screenshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_purchase_order_items`
--

DROP TABLE IF EXISTS `wa_po_purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_purchase_order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `po_id` int(10) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_wppoi_po` (`po_id`),
  KEY `idx_wppoi_product` (`product_id`),
  CONSTRAINT `fk_wppoi_po` FOREIGN KEY (`po_id`) REFERENCES `wa_po_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_purchase_orders`
--

DROP TABLE IF EXISTS `wa_po_purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_purchase_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `order_date` date NOT NULL,
  `status` enum('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting',
  `excess_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `use_default_delivery_address` tinyint(1) NOT NULL DEFAULT 1,
  `custom_delivery_line1` varchar(255) DEFAULT NULL,
  `custom_delivery_line2` varchar(255) DEFAULT NULL,
  `custom_delivery_city` varchar(100) DEFAULT NULL,
  `custom_delivery_district` varchar(100) DEFAULT NULL,
  `custom_delivery_state` varchar(100) DEFAULT NULL,
  `custom_delivery_country` varchar(100) DEFAULT NULL,
  `custom_delivery_pincode` varchar(20) DEFAULT NULL,
  `idempotency_key` varchar(100) NOT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'whatsapp',
  `notes` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wppo_idempotency` (`idempotency_key`),
  KEY `idx_wppo_user_date` (`user_category`,`user_id`,`order_date`),
  KEY `idx_wppo_status_date` (`status`,`order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_rate_limits`
--

DROP TABLE IF EXISTS `wa_po_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_rate_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rate_key` varchar(191) NOT NULL,
  `window_start` datetime NOT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wprl_key_window` (`rate_key`,`window_start`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wa_po_sessions`
--

DROP TABLE IF EXISTS `wa_po_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_po_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_token` char(64) NOT NULL,
  `wa_number` varchar(20) NOT NULL,
  `user_category` varchar(20) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `conversation_id` varchar(100) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wps_token` (`session_token`),
  KEY `idx_wps_expires` (`expires_at`),
  KEY `idx_wps_user` (`user_category`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_cp_commission_execution_log`
--

DROP TABLE IF EXISTS `wallet_cp_commission_execution_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_cp_commission_execution_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(50) NOT NULL,
  `execution_mode` enum('dry_run','execute') NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `total_cps_processed` int(11) NOT NULL DEFAULT 0,
  `total_credited` int(11) NOT NULL DEFAULT 0,
  `total_already_credited` int(11) NOT NULL DEFAULT 0,
  `total_not_eligible` int(11) NOT NULL DEFAULT 0,
  `total_commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `executed_by_user_id` varchar(50) NOT NULL,
  `executed_by_user_type` varchar(50) NOT NULL,
  `executed_by_user_name` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_rolled_back` tinyint(1) NOT NULL DEFAULT 0,
  `rolled_back_at` timestamp NULL DEFAULT NULL,
  `rolled_back_by_user_id` varchar(50) DEFAULT NULL,
  `rolled_back_by_user_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `execution_id` (`execution_id`),
  KEY `idx_execution_id` (`execution_id`),
  KEY `idx_month_year` (`month_year`),
  KEY `idx_is_rolled_back` (`is_rolled_back`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Execution log for CP wallet monthly commission crediting with rollback tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_monthly_sls_report`
--

DROP TABLE IF EXISTS `wallet_monthly_sls_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_monthly_sls_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `month` varchar(255) NOT NULL,
  `year` int(11) NOT NULL,
  `total_sls_amount` varchar(255) NOT NULL,
  `target_sls_amount` varchar(255) NOT NULL,
  `target_reached` varchar(255) NOT NULL COMMENT 'yes (or) no',
  `refer_by_usertype` varchar(255) NOT NULL,
  `refer_by_userid` varchar(255) NOT NULL,
  `commission_percentage` varchar(255) NOT NULL,
  `commission_amount` varchar(255) NOT NULL,
  `commission_type` varchar(255) NOT NULL,
  `remarks` varchar(500) NOT NULL,
  `execution_id` varchar(50) DEFAULT NULL COMMENT 'Set only for rows inserted via tp-wallet-referral-calculator; NULL means auto-credited by the dashboard trigger',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Transaction creation timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_user_wallet` (`refer_by_usertype`,`refer_by_userid`),
  KEY `idx_commission_type` (`commission_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3207 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_monthly_sls_report_backup_20241204`
--

DROP TABLE IF EXISTS `wallet_monthly_sls_report_backup_20241204`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_monthly_sls_report_backup_20241204` (
  `id` int(11) NOT NULL DEFAULT 0,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `month` varchar(255) NOT NULL,
  `year` int(11) NOT NULL,
  `total_sls_amount` varchar(255) NOT NULL,
  `target_sls_amount` varchar(255) NOT NULL,
  `target_reached` varchar(255) NOT NULL COMMENT 'yes (or) no',
  `refer_by_usertype` varchar(255) NOT NULL,
  `refer_by_userid` varchar(255) NOT NULL,
  `commission_percentage` varchar(255) NOT NULL,
  `commission_amount` varchar(255) NOT NULL,
  `commission_type` varchar(255) NOT NULL,
  `remarks` varchar(500) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Transaction creation timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_monthly_sls_report_backup_20260226`
--

DROP TABLE IF EXISTS `wallet_monthly_sls_report_backup_20260226`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_monthly_sls_report_backup_20260226` (
  `id` int(11) NOT NULL DEFAULT 0,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `month` varchar(255) NOT NULL,
  `year` int(11) NOT NULL,
  `total_sls_amount` varchar(255) NOT NULL,
  `target_sls_amount` varchar(255) NOT NULL,
  `target_reached` varchar(255) NOT NULL COMMENT 'yes (or) no',
  `refer_by_usertype` varchar(255) NOT NULL,
  `refer_by_userid` varchar(255) NOT NULL,
  `commission_percentage` varchar(255) NOT NULL,
  `commission_amount` varchar(255) NOT NULL,
  `commission_type` varchar(255) NOT NULL,
  `remarks` varchar(500) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Transaction creation timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_referral_execution_log`
--

DROP TABLE IF EXISTS `wallet_referral_execution_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_referral_execution_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `execution_id` varchar(50) NOT NULL,
  `execution_mode` enum('dry_run','execute') NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `total_tps_processed` int(11) NOT NULL DEFAULT 0,
  `total_credited` int(11) NOT NULL DEFAULT 0,
  `total_already_credited` int(11) NOT NULL DEFAULT 0,
  `total_not_eligible` int(11) NOT NULL DEFAULT 0,
  `total_commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `executed_by_user_id` varchar(50) NOT NULL,
  `executed_by_user_type` varchar(50) NOT NULL,
  `executed_by_user_name` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_rolled_back` tinyint(1) NOT NULL DEFAULT 0,
  `rolled_back_at` timestamp NULL DEFAULT NULL,
  `rolled_back_by_user_id` varchar(50) DEFAULT NULL,
  `rolled_back_by_user_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `execution_id` (`execution_id`),
  KEY `idx_execution_id` (`execution_id`),
  KEY `idx_month_year` (`month_year`),
  KEY `idx_is_rolled_back` (`is_rolled_back`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Execution log for TP wallet referral commission crediting with rollback tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_withdraw`
--

DROP TABLE IF EXISTS `wallet_withdraw`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_withdraw` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` varchar(255) NOT NULL,
  `req_id` varchar(255) NOT NULL,
  `req_status` varchar(255) NOT NULL,
  `user_type` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `remarks` varchar(255) NOT NULL,
  `updated_date` date NOT NULL,
  `updated_time` time NOT NULL,
  `TDS_percentage` varchar(255) NOT NULL,
  `TDS_deduction` varchar(255) NOT NULL,
  `sent_amount` varchar(255) NOT NULL,
  `acname` varchar(55) NOT NULL,
  `acnumber` varchar(55) NOT NULL,
  `bankname` varchar(55) NOT NULL,
  `ifsc` varchar(55) NOT NULL,
  `pannumber` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=427 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Current Database: `billing0femi9_billingapp`
--

USE `billing0femi9_billingapp`;

--
-- Final view structure for view `v_remapping_audit_report`
--

/*!50001 DROP VIEW IF EXISTS `v_remapping_audit_report`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`billing0femi9`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_remapping_audit_report` AS select `ral`.`id` AS `id`,`ral`.`admin_user_id` AS `admin_user_id`,`ral`.`from_user_type` AS `from_user_type`,`ral`.`from_user_id` AS `from_user_id`,`ral`.`to_user_type` AS `to_user_type`,`ral`.`to_user_id` AS `to_user_id`,`ral`.`customer_count` AS `customer_count`,`ral`.`action_date` AS `action_date`,`ral`.`action_type` AS `action_type`,date_format(`ral`.`action_date`,'%Y-%m-%d') AS `action_date_formatted`,date_format(`ral`.`action_date`,'%H:%i:%s') AS `action_time_formatted` from `remapping_audit_log` `ral` order by `ral`.`action_date` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-16 12:14:41
