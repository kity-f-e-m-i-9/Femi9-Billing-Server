-- Learning table for PO screenshot auto-verification: every time a company
-- reviewer corrects a wrong (or missing) auto-detected amount/reference on a
-- pending_review screenshot, the correction is recorded here keyed by the
-- OCR raw text that produced the wrong read. Future uploads whose OCR text
-- matches a previously-seen bad read reuse the known-correct value instead
-- of repeating the same misclassification indefinitely.
CREATE TABLE tp_po_screenshot_ocr_corrections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  screenshot_id INT UNSIGNED NOT NULL,
  field VARCHAR(20) NOT NULL, -- 'amount' or 'reference'
  wrong_value VARCHAR(255) NULL, -- what auto-detection produced (NULL = detected nothing)
  correct_value VARCHAR(255) NOT NULL, -- what the reviewer confirmed
  raw_text_hash CHAR(64) NOT NULL, -- sha256(ocr_raw_text), for fast exact-match lookup
  ocr_raw_text MEDIUMTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tppsoc_hash (raw_text_hash),
  KEY idx_tppsoc_field (field),
  CONSTRAINT fk_tppsoc_screenshot FOREIGN KEY (screenshot_id) REFERENCES tp_purchase_order_screenshots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
