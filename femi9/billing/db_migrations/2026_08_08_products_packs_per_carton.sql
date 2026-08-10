-- Add optional "packs per carton" metadata to products, so a product can
-- display how many sales packs fit in one dispatch carton/box. Distinct from
-- pieces_per_pack (pieces inside one pack) — this is packs inside one carton.
-- Informational only — does not change how qty is stored or moved anywhere else.
-- Applied: 2026-08-08

ALTER TABLE products ADD COLUMN packs_per_carton INT NULL DEFAULT NULL AFTER pieces_per_pack;
