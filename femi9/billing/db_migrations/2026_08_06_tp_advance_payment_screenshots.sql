-- Standalone TP advance-payment submissions: a TP submits proof of a
-- payment made to the company that is NOT tied to any purchase order,
-- expecting it to be added to their advance balance once verified.
--
-- Separate from tp_purchase_order_screenshots (rather than reusing it with
-- po_id always NULL) because several existing queries already treat every
-- po_id IS NULL row in that table as an in-progress PO draft
-- (acceptedTotalFor() in upload-po-screenshot.php, add-purchase-order.php's
-- draft-resume query, remove-po-screenshot.php) — a dedicated table avoids
-- auditing/patching all of those and matches this codebase's existing
-- pattern of one table per screenshot use-case.
CREATE TABLE tp_advance_payment_screenshots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  territory_partner_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,

  -- Submitted by the TP at upload time (this feature collects these
  -- upfront, unlike the PO-screenshot flow which only infers them from
  -- OCR/vision and lets a reviewer confirm them).
  submitted_amount DECIMAL(12,2) NOT NULL,
  submitted_payment_date DATE NOT NULL,
  submitted_payment_mode VARCHAR(50) NOT NULL,
  submitted_reference_number VARCHAR(255) NOT NULL,
  submitted_note VARCHAR(500) NULL,

  -- Automatic verification output (Claude vision / Google Vision fallback),
  -- same shape as tp_purchase_order_screenshots for consistency.
  detected_amount DECIMAL(12,2) NULL,
  reference_number VARCHAR(255) NULL,
  ocr_raw_text MEDIUMTEXT NULL,
  status ENUM('accepted','pending_review','rejected') NOT NULL DEFAULT 'pending_review',
  rejection_reason VARCHAR(255) NULL,
  reviewed_by VARCHAR(100) NULL,
  reviewed_at TIMESTAMP NULL,

  -- Set once approved and auto-inserted into tp_advance_payments.
  advance_payment_id INT UNSIGNED NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tpaps_tp (territory_partner_id),
  KEY idx_tpaps_status (status),
  KEY idx_tpaps_refnum (reference_number),
  KEY idx_tpaps_advpay (advance_payment_id),
  CONSTRAINT fk_tpaps_advpay FOREIGN KEY (advance_payment_id) REFERENCES tp_advance_payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
