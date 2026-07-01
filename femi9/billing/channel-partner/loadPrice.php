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
    case 'super_stockiest':   $mrp = $p['supersstock_price'];      break;
    case 'stockiest':         $mrp = $p['stockist_price'];         break;
    case 'distributor':       $mrp = $p['distributor_price'];      break;
    case 'super_distributor': $mrp = $p['super_distributor_price'];break;
    case 'shop':              $mrp = $p['outlet_price'];           break;
    default:                  $mrp = $p['mrp'];
}
?>
<input type="number" min="0" step="any" id="amount" onKeyup="totalkm()" value="<?php echo $mrp; ?>" name="amount">
