-- Add pack-level purchase price plus a stored per-piece purchase value
-- (purchase_price / pieces_per_pack), kept in sync by product-action.php
-- whenever either purchase_price or pieces_per_pack changes.
-- Applied: 2026-07-13

ALTER TABLE products
    ADD COLUMN purchase_price DECIMAL(10,2) NULL DEFAULT NULL AFTER pieces_per_pack,
    ADD COLUMN purchase_price_per_piece DECIMAL(10,4) NULL DEFAULT NULL AFTER purchase_price;
