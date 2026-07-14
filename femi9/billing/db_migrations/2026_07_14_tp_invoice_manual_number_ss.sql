-- Super Stockist TP invoices: switch invoice_number from auto-generated
-- (TP/SS{id}/{fy}/{seq}) to manually typed, with duplicate checking scoped
-- to the creating account instead of the whole tp_invoices table.
--
-- tp_invoices.invoice_number previously had a database-wide UNIQUE constraint
-- shared by Company and every Super Stockist account (see
-- shared/TpInvoiceNumberService.php). That constraint is dropped here and
-- replaced with two tracking columns (created_by_user_type, created_by_user_id)
-- plus a non-unique lookup index, so duplicate checks can be scoped per
-- account the same way user_invoice.inv_number already is (from_user_type +
-- from_user_id). Company's flow keeps auto-generating via the 'CO' series
-- and is unaffected.
--
-- Applied automatically by femi9/billing/super-stockist/tp-invoice-action.php
-- on first use (self-migrating) — this file is a record of that change, safe
-- to run manually and idempotent.

DROP PROCEDURE IF EXISTS _tp_invoices_manual_number_migration;
DELIMITER //
CREATE PROCEDURE _tp_invoices_manual_number_migration()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tp_invoices'
      AND COLUMN_NAME  = 'created_by_user_type'
  ) THEN
    ALTER TABLE `tp_invoices`
      ADD COLUMN `created_by_user_type` VARCHAR(30) NOT NULL DEFAULT '' AFTER `created_by`,
      ADD COLUMN `created_by_user_id`   VARCHAR(30) NOT NULL DEFAULT '' AFTER `created_by_user_type`;

    ALTER TABLE `tp_invoices`
      ADD INDEX `idx_tpi_creator` (`created_by_user_type`, `created_by_user_id`, `invoice_number`);

    -- Backfill existing rows from the old embedded-prefix format so history
    -- stays attributable to the account that created it.
    UPDATE `tp_invoices`
      SET `created_by_user_type` = 'super_stockiest',
          `created_by_user_id`   = SUBSTRING_INDEX(SUBSTRING(`invoice_number`, 6), '/', 1)
      WHERE `invoice_number` LIKE 'TP/SS%';

    UPDATE `tp_invoices`
      SET `created_by_user_type` = 'company'
      WHERE `created_by_user_type` = '';

    IF EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'tp_invoices'
        AND INDEX_NAME   = 'uk_tp_inv_number'
    ) THEN
      ALTER TABLE `tp_invoices` DROP INDEX `uk_tp_inv_number`;
    END IF;
  END IF;
END //
DELIMITER ;
CALL _tp_invoices_manual_number_migration();
DROP PROCEDURE IF EXISTS _tp_invoices_manual_number_migration;
