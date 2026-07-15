-- Neksomo "Purchase from Manufacturer": switch quantity entry from packs
-- (quantity_packs * pieces_per_pack * cost_per_piece) to a direct pieces
-- count (quantity_pieces * cost_per_piece) — the manufacturer purchase is
-- naturally piece-counted, unlike downstream stock movements which stay
-- pack-denominated throughout the rest of the app (stock.closing_qty etc.
-- are all whole packs). quantity_packs is kept and still populated
-- (quantity_pieces / pieces_per_pack, always a whole number because the app
-- validates entered pieces are a multiple of the product's pack size) — it's
-- what actually gets credited to on-hand stock via StockService.
--
-- total_cost was always pieces * cost_per_piece under the hood (quantity_packs
-- * pieces_per_pack * cost_per_piece == quantity_pieces * cost_per_piece), so
-- existing total_cost values are already correct and untouched by this
-- migration — only quantity_pieces is newly backfilled for display purposes.
--
-- Idempotent — safe to run multiple times.

DROP PROCEDURE IF EXISTS _neksomo_purchase_items_pieces_migration;
DELIMITER //
CREATE PROCEDURE _neksomo_purchase_items_pieces_migration()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'neksomo_purchase_items'
      AND COLUMN_NAME  = 'quantity_pieces'
  ) THEN
    ALTER TABLE `neksomo_purchase_items`
      ADD COLUMN `quantity_pieces` INT UNSIGNED NULL AFTER `quantity_packs`;

    UPDATE `neksomo_purchase_items` npi
      JOIN `products` p ON p.id = npi.product_id
      SET npi.`quantity_pieces` = npi.`quantity_packs` * GREATEST(p.`pieces_per_pack`, 1)
      WHERE npi.`quantity_pieces` IS NULL;

    -- Any orphaned rows with no matching product (shouldn't happen, FK-safe
    -- guard) fall back to treating quantity_packs as already piece-count.
    UPDATE `neksomo_purchase_items`
      SET `quantity_pieces` = `quantity_packs`
      WHERE `quantity_pieces` IS NULL;
  END IF;
END //
DELIMITER ;
CALL _neksomo_purchase_items_pieces_migration();
DROP PROCEDURE IF EXISTS _neksomo_purchase_items_pieces_migration;
