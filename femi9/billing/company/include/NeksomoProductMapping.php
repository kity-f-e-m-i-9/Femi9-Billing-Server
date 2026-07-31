<?php
/**
 * Links a Neksomo piece-product (products.temp_id LIKE 'NKS-%') to one or more
 * company pack-variant products of the same physical item (e.g. the "330mm"
 * piece product maps to the 330mm 9pc/6pc/3pc pack SKUs). One-to-many by design
 * — a mapping is manually curated via neksomo-product-map.php, not inferred.
 */

// Self-migrating: safe to call on every request that needs the table.
function ensure_neksomo_product_mapping_table($db_conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS neksomo_product_mapping (
            id INT AUTO_INCREMENT PRIMARY KEY,
            neksomo_product_id INT NOT NULL,
            company_product_id INT NOT NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_mapping (neksomo_product_id, company_product_id),
            KEY idx_neksomo_product (neksomo_product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

// company_product_id[] currently mapped to a given neksomo product.
function get_neksomo_product_mapping($db_conn, $neksomoProductId) {
    ensure_neksomo_product_mapping_table($db_conn);
    $stmt = $db_conn->prepare("SELECT company_product_id FROM neksomo_product_mapping WHERE neksomo_product_id = ?");
    $stmt->bind_param('i', $neksomoProductId);
    $stmt->execute();
    $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'company_product_id'));
    $stmt->close();
    return $ids;
}
