-- ============================================================
-- Add company_godown_warehouses mapping table
-- Date: 2026-09-21
-- Many-to-many between company profiles (company_godown) and physical
-- warehouses. Documentation/manual-apply convenience only — the table
-- is self-migrating via ensure_company_godown_warehouses_table() in
-- company/include/GodownWarehouseMapping.php, so this file is never
-- required to have run. See docs/superpowers/specs/
-- 2026-09-21-warehouse-aware-auto-transfer-design.md.
-- ============================================================

CREATE TABLE IF NOT EXISTS company_godown_warehouses (
    company_godown_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (company_godown_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
