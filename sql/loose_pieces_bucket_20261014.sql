-- Loose-pieces bucket (2026-10-14)
-- OPTIONAL: the PHP self-migrates these on first use. Run only if you want
-- the schema applied up front. ADD COLUMN lines error with "Duplicate
-- column" if already applied — that just means it's done.

ALTER TABLE raw_material_bundles
  ADD COLUMN to_bucket_pieces INT NULL AFTER bundle_no;

ALTER TABLE raw_material_bundle_draws
  ADD COLUMN bucket_id INT NULL AFTER bundle_id;

CREATE TABLE IF NOT EXISTS raw_material_bucket (
  id INT AUTO_INCREMENT PRIMARY KEY,
  raw_product_id INT NOT NULL,
  company_godown_id INT NOT NULL,
  warehouse_id INT NULL,
  pieces INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rmbk_key (raw_product_id, company_godown_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS raw_material_bucket_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bucket_id INT NOT NULL,
  type ENUM('close_in','conversion_out','reopen_out','undo_in') NOT NULL,
  qty INT NOT NULL,
  bundle_id INT NULL,
  ref_id VARCHAR(64) NULL,
  created_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rmbke_bucket (bucket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
