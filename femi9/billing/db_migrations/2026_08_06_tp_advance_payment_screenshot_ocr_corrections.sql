-- Corrections/learning table for standalone TP advance-payment screenshot
-- verification, mirroring tp_po_screenshot_ocr_corrections (same shape,
-- engine-tagged, few-shot source for ClaudeVisionService). Kept separate
-- from the PO-screenshot corrections table since these submissions are a
-- distinct verification context.
CREATE TABLE tp_advance_payment_screenshot_ocr_corrections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  screenshot_id INT UNSIGNED NOT NULL,
  field VARCHAR(20) NOT NULL,
  engine VARCHAR(20) NOT NULL DEFAULT 'google_vision',
  wrong_value VARCHAR(255) NULL,
  correct_value VARCHAR(255) NOT NULL,
  raw_text_hash CHAR(64) NOT NULL,
  ocr_raw_text MEDIUMTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tpapsoc_hash (raw_text_hash),
  KEY idx_tpapsoc_field (field),
  KEY idx_tpapsoc_engine (engine),
  CONSTRAINT fk_tpapsoc_screenshot FOREIGN KEY (screenshot_id) REFERENCES tp_advance_payment_screenshots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
