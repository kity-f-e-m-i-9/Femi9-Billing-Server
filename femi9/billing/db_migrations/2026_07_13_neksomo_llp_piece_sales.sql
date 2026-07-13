-- Records Neksomo Hygiene Industries' per-piece sales to Femi Nayan LLP.
-- Simple record-keeping table (no stock movement), matching the pattern of
-- internal_transfer (also record-only, no stock side effects).
-- Applied: 2026-07-13

CREATE TABLE IF NOT EXISTS neksomo_llp_piece_sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    sale_date DATE NOT NULL,
    pieces_qty INT UNSIGNED NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_sale_date (sale_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
