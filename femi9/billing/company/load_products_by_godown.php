<?php
include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$godown_id = (int)($_GET['godown_id'] ?? 0);
if ($godown_id <= 0 || !is_godown_allowed($db_conn, $godown_id)) { echo ''; exit; }

$godown_id_esc = mysqli_real_escape_string($db_conn, (string)$godown_id);

$res = mysqli_query($db_conn,
    "SELECT p.id, p.productName, s.closing_qty
     FROM products p
     JOIN stock s ON s.product_id = p.id AND s.user_type = 'company' AND s.user_id = '$godown_id_esc'
     WHERE s.closing_qty > 0
     ORDER BY p.productName"
);

echo '<option value="" hidden>Select Product</option>';
while ($row = mysqli_fetch_assoc($res)) {
    $id   = (int)$row['id'];
    $name = htmlspecialchars($row['productName'], ENT_QUOTES, 'UTF-8');
    $qty  = (int)$row['closing_qty'];
    echo "<option value=\"$id\">$name (Stock: $qty)</option>\n";
}
