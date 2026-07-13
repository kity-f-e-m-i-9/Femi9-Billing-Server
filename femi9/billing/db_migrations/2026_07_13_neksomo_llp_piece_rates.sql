-- Converts neksomo_llp_piece_sales (per-transaction entries) into a rate
-- history / price-list table: one row per product per date a new per-piece
-- rate to Femi Nayan LLP takes effect. A rate stays in force until a later
-- effective_date for the same product supersedes it. No rows exist yet
-- (feature shipped same day), so a straight rename + column change is safe.
-- Applied: 2026-07-13

RENAME TABLE neksomo_llp_piece_sales TO neksomo_llp_piece_rates;

ALTER TABLE neksomo_llp_piece_rates
    DROP COLUMN pieces_qty,
    DROP COLUMN total_amount,
    CHANGE COLUMN sale_date effective_date DATE NOT NULL,
    ADD UNIQUE KEY uniq_product_date (product_id, effective_date);
