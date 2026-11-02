<?php
// TEMPORARY diagnostic script — delete after use.
// Same query as get-ss-tp-products.php but with full error visibility and
// row-level debug output, to pin down why product 14 (330mm XL 6 PCS)
// isn't showing for this SS despite a positive stock row.
include("checksession.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

$tp_id = (int)($_GET['tp_id'] ?? 0);
$ss_id = mysqli_real_escape_string($db_conn, (string)$Login_user_IDvl);
$productType = tpResolveProductType($_GET['product_type'] ?? null);

echo "Login_user_IDvl (raw): [" . $Login_user_IDvl . "]\n";
echo "ss_id (escaped):       [" . $ss_id . "]\n";
echo "tp_id:                 $tp_id\n";
echo "productType:            $productType\n";
echo "-----\n";

if ($tp_id <= 0) { echo "tp_id invalid, exiting.\n"; exit; }

$chk = $db_conn->prepare("SELECT id FROM territory_partners WHERE id=? AND onboard_ss_id=? AND is_active=1 LIMIT 1");
$chk->bind_param("is", $tp_id, $ss_id);
$chk->execute();
$chkRows = $chk->get_result()->num_rows;
echo "TP verify rows: $chkRows\n";
if ($chkRows === 0) { echo "TP verification FAILED, exiting (this is why nothing shows).\n"; exit; }
$chk->close();

echo "-----\n";
echo "Raw stock row for product_id=14 for this ss_id (direct lookup):\n";
$direct = $db_conn->prepare("SELECT * FROM stock WHERE user_type='super_stockiest' AND user_id=? AND product_id=14");
$direct->bind_param("s", $ss_id);
$direct->execute();
$directRows = $direct->get_result()->fetch_all(MYSQLI_ASSOC);
print_r($directRows);

echo "-----\n";
echo "Product 14 row from products table:\n";
$p14 = $db_conn->query("SELECT * FROM products WHERE id=14");
print_r($p14->fetch_assoc());

echo "-----\n";
$sql = "SELECT s.product_id, p.productName, p.hsn,
            s.closing_qty AS available_qty,
            COALESCE(p.stockist_price, p.distributor_price, 0) AS rate
         FROM stock s
         JOIN products p ON p.id = s.product_id
         WHERE s.user_type = 'super_stockiest'
           AND s.user_id = '$ss_id'
           AND s.closing_qty > 0
           AND p.deleted_at IS NULL
           AND " . tpProductTypeSqlFilter($productType) . "
         ORDER BY p.productName ASC";

echo "SQL:\n$sql\n-----\n";

$res = mysqli_query($db_conn, $sql);
if (!$res) {
    echo "QUERY ERROR: " . mysqli_error($db_conn) . "\n";
    exit;
}
echo "Row count: " . mysqli_num_rows($res) . "\n";
$out = [];
while ($row = mysqli_fetch_assoc($res)) {
    $out[] = $row;
    echo $row['product_id'] . " | " . $row['productName'] . " | qty=" . $row['available_qty'] . "\n";
}
