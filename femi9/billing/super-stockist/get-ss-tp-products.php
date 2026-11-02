<?php
include("checksession.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: application/json');

$tp_id = (int)($_GET['tp_id'] ?? 0);
$ss_id = mysqli_real_escape_string($db_conn, (string)$Login_user_IDvl);
$productType = tpResolveProductType($_GET['product_type'] ?? null);

if ($tp_id <= 0) { echo json_encode([]); exit; }

// Verify TP belongs to this SS
$chk = $db_conn->prepare("SELECT id FROM territory_partners WHERE id=? AND onboard_ss_id=? AND is_active=1 LIMIT 1");
$chk->bind_param("is", $tp_id, $ss_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) { echo json_encode([]); exit; }
$chk->close();

// No "p.deleted_at IS NULL" gate here on purpose: Inactive means the
// company stopped supplying/selling this product going forward, but an SS
// who already received stock of it before that still needs to be able to
// dispatch their own existing stock onward to their TPs — same principle
// as products.visible_in_get_order for Marketing Staff's Get Order (see
// db_migrations/2026_10_29_products_visible_in_get_order.sql). The real
// gate here is s.closing_qty > 0 — the SS can only invoice what they
// actually still hold.
$sql = "SELECT s.product_id, p.productName, p.hsn,
            s.closing_qty AS available_qty,
            COALESCE(p.stockist_price, p.distributor_price, 0) AS rate
         FROM stock s
         JOIN products p ON p.id = s.product_id
         WHERE s.user_type = 'super_stockiest'
           AND s.user_id = '$ss_id'
           AND s.closing_qty > 0
           AND " . tpProductTypeSqlFilter($productType) . "
         ORDER BY p.productName ASC";

$res = mysqli_query($db_conn, $sql);
$out = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = [
            'product_id'    => (int)$row['product_id'],
            'productName'   => (string)$row['productName'],
            'hsn'           => (string)($row['hsn'] ?? ''),
            'available_qty' => (int)$row['available_qty'],
            'rate'          => (float)$row['rate'],
        ];
    }
}

echo json_encode($out);
