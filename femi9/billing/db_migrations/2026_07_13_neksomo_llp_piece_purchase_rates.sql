-- Replaces the static per-product purchase_price / purchase_price_per_piece
-- columns with a date-effective rate history, exactly mirroring
-- neksomo_llp_piece_rates (the Femi9 LLP sale-rate list). A purchase rate
-- takes effect the day it's entered and holds until a later effective_date
-- for the same product supersedes it.
-- Applied: 2026-07-13

CREATE TABLE IF NOT EXISTS neksomo_llp_piece_purchase_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_date (product_id, effective_date),
    KEY idx_product (product_id),
    KEY idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE products
    DROP COLUMN purchase_price,
    DROP COLUMN purchase_price_per_piece;
