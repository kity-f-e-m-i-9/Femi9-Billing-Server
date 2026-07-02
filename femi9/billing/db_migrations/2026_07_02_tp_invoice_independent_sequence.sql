-- Give each login (company, super-stockist) its own independent TP invoice
-- number series instead of sharing one connected/continuous counter.
-- Format changes from TP/{fy}/{seq} to TP/{SOURCE}/{fy}/{seq}, e.g. TP/CO/26-27/0001.
-- Applied automatically by femi9/billing/shared/TpInvoiceNumberService.php on
-- first use (self-migrating) — this file is a record of that change, safe to
-- run manually and idempotent.

DROP PROCEDURE IF EXISTS _add_tp_inv_sequence_source;
DELIMITER //
CREATE PROCEDURE _add_tp_inv_sequence_source()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tp_inv_sequence'
      AND COLUMN_NAME  = 'source'
  ) THEN
    ALTER TABLE `tp_inv_sequence` ADD COLUMN `source` VARCHAR(10) NOT NULL DEFAULT '' AFTER `id`;
    UPDATE `tp_inv_sequence` SET `source` = 'CO' WHERE `id` = 1 AND `source` = '';
    ALTER TABLE `tp_inv_sequence` DROP PRIMARY KEY, ADD PRIMARY KEY (`source`);
  END IF;
END //
DELIMITER ;
CALL _add_tp_inv_sequence_source();
DROP PROCEDURE IF EXISTS _add_tp_inv_sequence_source;

INSERT IGNORE INTO `tp_inv_sequence` (`source`, `last_val`, `fy`) VALUES ('SS', 0, '');
