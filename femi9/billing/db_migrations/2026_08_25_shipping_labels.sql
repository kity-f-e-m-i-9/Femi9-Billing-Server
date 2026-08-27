-- Per-box shipping labels for a TP purchase order. One row per physical box
-- (From/To/Count/Source), each with its own list of what's packed inside it
-- (po_shipping_label_items) — see company/shipping-label-print.php.
--
-- Self-migrating guard also lives in that file; this is the real migration
-- record for environments that run migrations explicitly.

CREATE TABLE IF NOT EXISTS po_shipping_labels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  po_id INT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  count_text VARCHAR(20) NOT NULL DEFAULT '',
  source_text VARCHAR(100) NOT NULL DEFAULT '',
  from_address TEXT NULL,
  to_address TEXT NULL,
  note_text VARCHAR(100) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_psl_po (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS po_shipping_label_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  label_id INT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  product_text VARCHAR(255) NOT NULL,
  packs_count INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_psli_label (label_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
