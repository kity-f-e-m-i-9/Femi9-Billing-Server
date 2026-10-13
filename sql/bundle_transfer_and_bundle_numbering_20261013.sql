-- Bundle Transfer + per-location bundle numbering (2026-10-13)
-- OPTIONAL: the PHP self-migrates these on first page load. Run this only
-- if you'd rather apply the schema change up front. Safe to run once;
-- the ADD COLUMN lines will error with "Duplicate column" if already applied.

ALTER TABLE raw_material_bundles
  ADD COLUMN transferred_to_bundle_id   INT NULL AFTER carried_from_bundle_id,
  ADD COLUMN transferred_from_bundle_id INT NULL AFTER transferred_to_bundle_id,
  ADD COLUMN transfer_ref_id            VARCHAR(64) NULL AFTER transferred_from_bundle_id,
  ADD COLUMN bundle_no                  INT NULL AFTER transfer_ref_id;

CREATE TABLE IF NOT EXISTS raw_material_bundle_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref_id VARCHAR(64) NOT NULL,
  raw_product_id INT NOT NULL,
  from_company_godown_id INT NOT NULL,
  from_warehouse_id INT NULL,
  to_company_godown_id INT NOT NULL,
  to_warehouse_id INT NULL,
  source_bundle_id INT NOT NULL,
  new_bundle_id INT NOT NULL,
  pieces INT NOT NULL,
  status ENUM('active','undone') NOT NULL DEFAULT 'active',
  created_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  undone_by VARCHAR(100) NULL,
  undone_at TIMESTAMP NULL,
  KEY idx_rmbt_ref (ref_id),
  KEY idx_rmbt_source (source_bundle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backfill display numbers: restart at 1 per (company profile, warehouse),
-- oldest first. Needs MySQL 8+ / MariaDB 10.2+ (window functions).
UPDATE raw_material_bundles b
JOIN (
  SELECT id, ROW_NUMBER() OVER (PARTITION BY company_godown_id, warehouse_id ORDER BY id) AS rn
  FROM raw_material_bundles
) x ON x.id = b.id
SET b.bundle_no = x.rn
WHERE b.bundle_no IS NULL;
