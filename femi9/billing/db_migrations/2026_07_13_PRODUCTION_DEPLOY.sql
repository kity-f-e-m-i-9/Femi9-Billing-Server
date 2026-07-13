-- ═══════════════════════════════════════════════════════════════════════════
-- Consolidated one-shot deploy: everything needed to bring production's
-- database in line with the Neksomo / Femi9-LLP-rate / pieces-per-pack
-- features already live in the deployed code. Run this ONCE against
-- production. Supersedes running the 7 individual dated migration files —
-- this skips the now-pointless add-then-drop of purchase_price columns and
-- goes straight to final state.
-- ═══════════════════════════════════════════════════════════════════════════

-- 1) Neksomo login (Company portal, scoped to NEKSOMO HYGIENE INDUSTRIES via
--    GodownAccess.php / PermissionCheck.php). Guarded so re-running this
--    script never creates a duplicate account.
INSERT INTO admin_log
    (username, password, usertype, state, dash, report, company_profile, users_demo,
     reward_points, demo_free, manage_return, debit_note, stock_request, products,
     add_input_stock, manage_input_stock, add_input_stock_users, manage_input_stock_users,
     ot_channels, location, ss, st, dt, sdt, shop, cus, ms, unassigned, remap,
     partner_location, channel_partner, territory_partner, stock_transfers,
     users_network, payment_entry, manage_payment_entry, consolidated_payment_entry,
     bonus_calculator, manage_bonus_points)
SELECT '9715059715', 'Neksomo@2026', 'neksomo', 0, 0, 0, 0, 0,
     0, 0, 0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0
WHERE NOT EXISTS (SELECT 1 FROM admin_log WHERE username = '9715059715');

-- 2) Pieces-per-pack on products (optional int, informational).
ALTER TABLE products ADD COLUMN pieces_per_pack INT NULL DEFAULT NULL AFTER productName;

-- 3) Backfill pieces_per_pack for the 13 known products, matched by name
--    (not id, since ids can differ between environments). 7 were parsed
--    from an explicit "(N PCS)" in the title; the other 6 were provided
--    directly since their titles don't state a count.
UPDATE products SET pieces_per_pack = 5  WHERE productName = '410mm XXL  - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 10 WHERE productName = '320mm XL - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 12 WHERE productName = '280mm L - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 30 WHERE productName = '180mm (30 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 6  WHERE productName = 'Combo pack - Femi9 Sanitary Napkins';
UPDATE products SET pieces_per_pack = 3  WHERE productName = 'Trial Pack 320mm - Femi9 Sanitary Napkins';
UPDATE products SET pieces_per_pack = 4  WHERE productName = 'Trial Pack  280mm - Femi9 Sanitary Napkins';
UPDATE products SET pieces_per_pack = 9  WHERE productName = '330mm XL (9 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 9  WHERE productName = '290mm L (9  PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 6  WHERE productName = '330mm XL (6 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 3  WHERE productName = '330mm XL (3 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 6  WHERE productName = '290mm L (6 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 3  WHERE productName = '290mm L (3 PCS) - Femi9 Premium Sanitary Napkin';

-- 4) Femi9 LLP sale-rate list (per-piece price Neksomo sells to Femi Nayan
--    LLP, date-effective).
CREATE TABLE IF NOT EXISTS neksomo_llp_piece_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_date (product_id, effective_date),
    KEY idx_product (product_id),
    KEY idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Femi9 LLP purchase-rate list (per-piece cost Neksomo pays, date-effective).
CREATE TABLE IF NOT EXISTS neksomo_llp_piece_purchase_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_date (product_id, effective_date),
    KEY idx_product (product_id),
    KEY idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify
SELECT username, usertype FROM admin_log WHERE usertype = 'neksomo';
SELECT id, productName, pieces_per_pack FROM products ORDER BY id;
SHOW TABLES LIKE 'neksomo_llp%';
