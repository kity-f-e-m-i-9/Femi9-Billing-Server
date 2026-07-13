-- Add optional "pieces per pack" metadata to products, so a pack/box/case
-- product can display how many individual pieces it contains. Informational
-- only — does not change how qty is stored or moved anywhere else.
-- Applied: 2026-07-13

ALTER TABLE products ADD COLUMN pieces_per_pack INT NULL DEFAULT NULL AFTER productName;
