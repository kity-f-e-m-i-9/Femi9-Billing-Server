<?php
include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$godown_id   = (int)($_GET['godown_id'] ?? 0);
$warehouseId = filter_var($_GET['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
if ($godown_id <= 0 || !is_godown_allowed($db_conn, $godown_id)) { echo ''; exit; }

$godown_id_esc = mysqli_real_escape_string($db_conn, (string)$godown_id);

if ($warehouseId !== null) {
    // A specific physical warehouse was picked — show only that warehouse's
    // own closing_qty, since that's exactly what StockService::transferOut()
    // will check at submit time (demofree_action.php). Without this filter,
    // a product split across multiple warehouse rows for the same company
    // profile appeared as duplicate <option> entries, one per row, each
    // showing only that row's partial quantity.
    $warehouseId_esc = (int)$warehouseId;
    $res = mysqli_query($db_conn,
        "SELECT p.id, p.productName, s.closing_qty
         FROM products p
         JOIN stock s ON s.product_id = p.id AND s.user_type = 'company' AND s.user_id = '$godown_id_esc' AND s.warehouse_id = $warehouseId_esc
         WHERE s.closing_qty > 0 AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
         ORDER BY p.productName"
    );
} else {
    // No warehouse selected yet — fall back to the combined total across all
    // warehouses (legacy view), same as before physical warehouses existed.
    $res = mysqli_query($db_conn,
        "SELECT p.id, p.productName, SUM(s.closing_qty) AS closing_qty
         FROM products p
         JOIN stock s ON s.product_id = p.id AND s.user_type = 'company' AND s.user_id = '$godown_id_esc'
         WHERE (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
         GROUP BY p.id, p.productName
         HAVING SUM(s.closing_qty) > 0
         ORDER BY p.productName"
    );
}

echo '<option value="" hidden>Select Product</option>';
while ($row = mysqli_fetch_assoc($res)) {
    $id   = (int)$row['id'];
    $name = htmlspecialchars($row['productName'], ENT_QUOTES, 'UTF-8');
    $qty  = (int)$row['closing_qty'];
    echo "<option value=\"$id\">$name (Stock: $qty)</option>\n";
}
