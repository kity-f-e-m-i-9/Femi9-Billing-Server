<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$prid    = (int)($_GET['q'] ?? 0);
$invuser = $_GET['invuser'] ?? '';

$stmt = $db_conn->prepare("SELECT * FROM products WHERE id=?");
$stmt->bind_param('i', $prid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

switch ($invuser) {
    case 'super_stockiest':   $price = $p['supersstock_price'];      break;
    case 'stockiest':         $price = $p['stockist_price'];         break;
    case 'distributor':       $price = $p['distributor_price'];      break;
    case 'super_distributor': $price = $p['super_distributor_price'];break;
    case 'shop':              $price = $p['outlet_price'];           break;
    default:                  $price = $p['mrp'];
}
$actualMrp = $p['mrp'] ?? 0;
?>
<input type="number" min="0" step="any" id="amount" onKeyup="totalkm()" value="<?php echo $price; ?>" data-mrp="<?php echo $actualMrp; ?>" name="amount">
