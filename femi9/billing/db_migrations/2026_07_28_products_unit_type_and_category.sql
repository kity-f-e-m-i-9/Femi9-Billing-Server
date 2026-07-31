-- Add selling-unit and product-category metadata to products, so Neksomo can
-- record whether a product is sold as loose pieces or in packs, and whether
-- it's a napkin or diaper item.
-- Applied: 2026-07-28

ALTER TABLE products ADD COLUMN unit_type ENUM('pieces','pack') NOT NULL DEFAULT 'pieces' AFTER pieces_per_pack;
ALTER TABLE products ADD COLUMN category ENUM('napkin','diaper') NULL DEFAULT NULL AFTER unit_type;
