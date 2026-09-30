-- Covers + Carton Box stock tracking — production migration
-- Idempotent: safe to run whether these tables already exist (from an
-- earlier deploy) or not at all (fresh install). Mirrors exactly what
-- include/ProductCovers.php and include/CartonBoxes.php self-migrate on
-- first request — this just lets you apply it ahead of time instead.

-- ── product_covers ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_covers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_product_id INT NOT NULL,
    company_godown_id INT NOT NULL,
    warehouse_id INT NULL,
    received_qty INT NOT NULL DEFAULT 0,
    used_qty INT NOT NULL DEFAULT 0,
    damaged_qty INT NOT NULL DEFAULT 0,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pc_key (company_product_id, company_godown_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── product_cover_adjustments ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_cover_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cover_id INT NOT NULL,
    type ENUM('receive','damage','use') NOT NULL,
    reason_id INT NULL,
    qty INT NOT NULL,
    note VARCHAR(255) NULL,
    ref_id VARCHAR(64) NULL,
    entry_date DATE NOT NULL,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pca_cover (cover_id),
    KEY idx_pca_type (type),
    KEY idx_pca_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- If product_cover_adjustments already existed WITHOUT entry_date
-- (i.e. from before this migration), run this block manually:
--   ALTER TABLE product_cover_adjustments ADD COLUMN entry_date DATE NULL AFTER ref_id;
--   UPDATE product_cover_adjustments SET entry_date = DATE(created_at) WHERE entry_date IS NULL;
--   ALTER TABLE product_cover_adjustments MODIFY COLUMN entry_date DATE NOT NULL, ADD KEY idx_pca_date (entry_date);
--   ALTER TABLE product_cover_adjustments MODIFY COLUMN type ENUM('receive','damage','use') NOT NULL;
-- (The app detects and applies this automatically on first request too —
-- this manual block is only needed if you want it done ahead of time.)


-- ── carton_box_types ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS carton_box_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_carton_type_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── carton_box_damage_reason_master ─────────────────────────────────
CREATE TABLE IF NOT EXISTS carton_box_damage_reason_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_carton_reason_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── carton_boxes ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS carton_boxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carton_type_id INT NOT NULL,
    company_godown_id INT NOT NULL,
    warehouse_id INT NULL,
    received_qty INT NOT NULL DEFAULT 0,
    used_qty INT NOT NULL DEFAULT 0,
    damaged_qty INT NOT NULL DEFAULT 0,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cb_key (carton_type_id, company_godown_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── carton_box_adjustments ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS carton_box_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carton_id INT NOT NULL,
    type ENUM('receive','damage','use') NOT NULL,
    reason_id INT NULL,
    qty INT NOT NULL,
    note VARCHAR(255) NULL,
    ref_id VARCHAR(64) NULL,
    entry_date DATE NOT NULL,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cba_carton (carton_id),
    KEY idx_cba_type (type),
    KEY idx_cba_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- If carton_box_adjustments already existed WITHOUT entry_date or the
-- 'use' type (i.e. from before this migration), run this block manually:
--   ALTER TABLE carton_box_adjustments ADD COLUMN entry_date DATE NULL AFTER ref_id;
--   UPDATE carton_box_adjustments SET entry_date = DATE(created_at) WHERE entry_date IS NULL;
--   ALTER TABLE carton_box_adjustments MODIFY COLUMN entry_date DATE NOT NULL, ADD KEY idx_cba_date (entry_date);
--   ALTER TABLE carton_box_adjustments MODIFY COLUMN type ENUM('receive','damage','use') NOT NULL;
-- (The app detects and applies this automatically on first request too —
-- this manual block is only needed if you want it done ahead of time.)
