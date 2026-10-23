<?php
// Shared between ot-sale-import-template.php (builds the Product dropdown)
// and ot-sale-import-action.php (resolves a typed/picked product back to a
// real product id) — kept in one place so the label the template shows and
// the label the importer matches against can never drift apart.

// Trims the "- Femi9 Premium Sanitary Napkin" / "- Femi9 Sanitary Napkins"
// brand suffix every napkin product name carries, so what the user sees/picks
// is short (e.g. "330mm XL (SW-9 PCS)" instead of "330mm XL (SW-9 PCS) -
// Femi9 Premium Sanitary Napkin"). Diaper products have no such suffix and
// pass through unchanged.
function ot_import_short_product_name(string $name): string {
    return trim(preg_replace('/\s*-\s*Femi9\s+(Premium\s+)?Sanitary\s+Napkins?\s*$/i', '', $name));
}

// Every active, sellable product (same scope ot-sale-add.php's manual "Add
// Sale" form offers), each with its short_name pre-computed.
function ot_import_active_products(mysqli $db_conn): array {
    $products = [];
    $res = $db_conn->query(
        "SELECT id, productName, outlet_price, gst, gst_type, hsn, category FROM products
         WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)
         ORDER BY productName ASC"
    );
    while ($row = $res->fetch_assoc()) {
        $row['short_name'] = ot_import_short_product_name($row['productName']);
        $products[] = $row;
    }
    return $products;
}
