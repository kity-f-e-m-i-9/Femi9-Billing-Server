-- Adds piece-level remainder tracking to the shared `stock` table.
--
-- stock.closing_qty stays the pack-based source of truth for the whole app
-- (unchanged). extra_pieces holds loose pieces that haven't yet accumulated
-- into a whole pack — e.g. buying 2 pieces of a 5-piece pack doesn't credit
-- any packs yet, it just raises extra_pieces to 2; a later purchase of 4 more
-- pieces (2+4=6) rolls one whole pack into closing_qty and leaves
-- extra_pieces at 1. Total pieces on hand for a product is always
-- `closing_qty * pieces_per_pack + extra_pieces`.
--
-- Only ever written by the Neksomo "Purchase from Manufacturer" flow today;
-- every other login/flow leaves it at its default of 0, so this is a safe,
-- purely additive column for the shared table.
--
-- Idempotent — safe to run multiple times.

DROP PROCEDURE IF EXISTS _stock_add_extra_pieces;
DELIMITER //
CREATE PROCEDURE _stock_add_extra_pieces()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'stock'
      AND COLUMN_NAME  = 'extra_pieces'
  ) THEN
    ALTER TABLE `stock`
      ADD COLUMN `extra_pieces` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `closing_qty`;
  END IF;
END //
DELIMITER ;
CALL _stock_add_extra_pieces();
DROP PROCEDURE IF EXISTS _stock_add_extra_pieces;
