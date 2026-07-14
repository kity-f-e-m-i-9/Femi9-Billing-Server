-- Records Neksomo's purchases from manufacturers: product, manufacturer,
-- date, quantity (in packs, matching the stock table's unit), cost per
-- piece. Unlike the Femi9 LLP rate lists, each entry here also credits
-- real stock (via StockService::credit(), same mechanism as Add Input
-- Stock) for the NEKSOMO HYGIENE INDUSTRIES godown — stock_ledger_id links
-- back to the stock_ledger row so a deleted entry can cleanly reverse it.
-- Applied: 2026-07-14

CREATE TABLE IF NOT EXISTS neksomo_manufacturer_purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    manufacturer_name VARCHAR(255) NOT NULL,
    purchase_date DATE NOT NULL,
    quantity_packs INT UNSIGNED NOT NULL,
    cost_per_piece DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(12,2) NOT NULL,
    stock_ledger_id INT NULL DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_purchase_date (purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
