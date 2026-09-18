-- Auto Transfer's "default rate" table (pre-fills the Rate to Health
-- Care / Rate to LLP boxes on internal_transfer_auto.php, managed going
-- forward from transfer-price.php). Self-migrates via
-- ensure_auto_transfer_default_rates_table() in AutoTransferDemand.php,
-- but that only creates the table -- it never seeds it. These 22 rows
-- were provided directly and were only ever inserted into local dev,
-- never into production. This file seeds production with the same
-- values.
--
-- Matched by productName rather than a hardcoded id, since product ids
-- can differ between the local dev DB and production. Some Lumi9 Baby
-- Diaper names exist TWICE in products (once with a temp_id starting
-- "NKS-" for Neksomo-internal manufacturing tracking, once without,
-- for the real sellable/transferable product) -- the "NOT LIKE 'NKS-%'"
-- filter is the same one internal_transfer.php's own product picker
-- uses, so this seeds the same row Auto Transfer actually reads.
--
-- Safe to re-run: ON DUPLICATE KEY UPDATE overwrites with the same
-- numbers instead of erroring or duplicating.

CREATE TABLE IF NOT EXISTS auto_transfer_default_rates (
    product_id INT NOT NULL PRIMARY KEY,
    rate_healthcare DECIMAL(10,2) NOT NULL DEFAULT 0,
    rate_llp DECIMAL(10,2) NOT NULL DEFAULT 0,
    updated_by VARCHAR(100) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO auto_transfer_default_rates (product_id, rate_healthcare, rate_llp, updated_by)
SELECT p.id, r.rate_healthcare, r.rate_llp, 'migration-seed'
FROM (
    -- Femi9 Premium Sanitary Napkin sizes
    SELECT '410mm XXL  - Femi9 Premium Sanitary Napkin'          AS productName, 63.00  AS rate_healthcare, 99.00  AS rate_llp
    UNION ALL SELECT '320mm XL - Femi9 Premium Sanitary Napkin',        63.00,  99.00
    UNION ALL SELECT '280mm L - Femi9 Premium Sanitary Napkin',         63.00,  99.00
    UNION ALL SELECT '180mm (30 PCS) - Femi9 Premium Sanitary Napkin',  63.00,  99.00
    UNION ALL SELECT '330mm XL (9 PCS) - Femi9 Premium Sanitary Napkin', 76.00, 99.00
    UNION ALL SELECT '290mm L (9  PCS) - Femi9 Premium Sanitary Napkin', 66.00, 86.00
    UNION ALL SELECT '330mm XL (6 PCS) - Femi9 Premium Sanitary Napkin', 52.00, 66.00
    UNION ALL SELECT '330mm XL (3 PCS) - Femi9 Premium Sanitary Napkin', 28.00, 33.00
    UNION ALL SELECT '290mm L (6 PCS) - Femi9 Premium Sanitary Napkin',  45.00, 57.00
    UNION ALL SELECT '290mm L (3 PCS) - Femi9 Premium Sanitary Napkin',  25.00, 29.00
    -- Lumi9 Baby Diaper sizes
    UNION ALL SELECT 'Lumi9 Baby Diaper NB(3)',   22.00,  23.00
    UNION ALL SELECT 'Lumi9 Baby Diaper NB(24)',  138.00, 155.00
    UNION ALL SELECT 'Lumi9 Baby Diaper NB(54)',  299.00, 328.00
    UNION ALL SELECT 'Lumi9 Baby Diaper S(3)',    23.00,  24.00
    UNION ALL SELECT 'Lumi9 Baby Diaper S(24)',   142.00, 163.00
    UNION ALL SELECT 'Lumi9 Baby Diaper S(54)',   309.00, 348.00
    UNION ALL SELECT 'Lumi9 Baby Diaper M(24)',   159.00, 180.00
    UNION ALL SELECT 'Lumi9 Baby Diaper M(54)',   347.00, 385.00
    UNION ALL SELECT 'Lumi9 Baby Diaper L(24)',   170.00, 193.00
    UNION ALL SELECT 'Lumi9 Baby Diaper L(54)',   371.00, 413.00
    UNION ALL SELECT 'Lumi9 Baby Diaper XL(24)',  184.00, 208.00
    UNION ALL SELECT 'Lumi9 Baby Diaper XL(54)',  404.00, 447.00
) r
INNER JOIN products p
    ON p.productName = r.productName
    AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
ON DUPLICATE KEY UPDATE
    rate_healthcare = VALUES(rate_healthcare),
    rate_llp        = VALUES(rate_llp),
    updated_by      = VALUES(updated_by);
