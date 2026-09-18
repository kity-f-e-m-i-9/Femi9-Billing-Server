-- FIFO lot tracking for company-side stock, so Gross Profit can cost each
-- sold unit against the purchase rate it actually came from instead of one
-- blended "rate as of period-end" applied to the whole period.
-- Applied: 2026-09-16

CREATE TABLE stock_lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_type VARCHAR(32) NOT NULL,
  user_id VARCHAR(32) NOT NULL,
  rate DECIMAL(12,6) NOT NULL,
  qty_purchased INT NOT NULL,
  qty_remaining INT NOT NULL,
  purchase_date DATE NOT NULL,
  ref_type ENUM('llp_rate_entry','neksomo_purchase','transfer_in','opening_balance') NOT NULL,
  ref_id VARCHAR(64) NULL,
  created_by VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fifo_lookup (product_id, user_type, user_id, purchase_date, id),
  INDEX idx_qty_remaining (product_id, user_type, user_id, qty_remaining)
);

-- Which lot(s) a single stock_ledger deduct row actually drew from. A
-- stock_lot_id of NULL means the fallback rate-lookup was used because no
-- lot covered that portion of the sale (e.g. pre-migration stock, or a sale
-- source like TP invoices that never calls StockService at all).
CREATE TABLE stock_ledger_lot_consumption (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_ledger_id INT NOT NULL,
  stock_lot_id INT NULL,
  qty_taken INT NOT NULL,
  rate DECIMAL(12,6) NOT NULL,
  INDEX idx_ledger (stock_ledger_id),
  INDEX idx_lot (stock_lot_id)
);
