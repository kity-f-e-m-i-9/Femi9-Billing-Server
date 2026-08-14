-- Replaces the single unverified `payment_screenshot` column (added
-- 2026-07-30) with a child table supporting up to 5 screenshots per order,
-- each independently OCR-verified for a detected amount and payment
-- reference number. Unlinked rows (po_id IS NULL) track a TP's in-progress
-- upload draft before the purchase order itself is submitted.
CREATE TABLE tp_purchase_order_screenshots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  po_id INT UNSIGNED NULL,
  territory_partner_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  detected_amount DECIMAL(12,2) NULL,
  reference_number VARCHAR(255) NULL,
  ocr_raw_text MEDIUMTEXT NULL,
  status ENUM('accepted','pending_review','rejected') NOT NULL DEFAULT 'pending_review',
  rejection_reason VARCHAR(255) NULL,
  reviewed_by VARCHAR(100) NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tppos_tp (territory_partner_id),
  KEY idx_tppos_po (po_id),
  KEY idx_tppos_status (status),
  KEY idx_tppos_refnum (reference_number),
  CONSTRAINT fk_tppos_po FOREIGN KEY (po_id) REFERENCES tp_purchase_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tp_purchase_orders DROP COLUMN payment_screenshot;
