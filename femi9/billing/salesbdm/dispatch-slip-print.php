<?php
include("checksession.php");
require_once("../company/include/GodownAccess.php");
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/DispatchSlipSettings.php';

$po_id = (int)($_GET['po_id'] ?? 0);
if (!$po_id) { header("Location: tp-purchase-order"); exit; }

$tpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);

// PO header + TP billing/delivery details
$stmt = $db_conn->prepare("
    SELECT o.id, o.order_date, o.status,
           o.use_default_delivery_address, o.custom_delivery_line1, o.custom_delivery_line2,
           o.custom_delivery_city, o.custom_delivery_district, o.custom_delivery_state,
           o.custom_delivery_country, o.custom_delivery_pincode,
           tp.id AS tp_db_id, tp.name AS tp_name, tp.company_name AS tp_company_name, tp.tp_id AS tp_code,
           tp.mobile AS tp_mobile, tp.gstin AS tp_gstin,
           tp.branch_line1, tp.branch_line2, tp.branch_city, tp.branch_district, tp.branch_state, tp.branch_country, tp.branch_pincode,
           tp.delivery_line1, tp.delivery_line2, tp.delivery_city, tp.delivery_district, tp.delivery_state, tp.delivery_country, tp.delivery_pincode
    FROM tp_purchase_orders o
    JOIN territory_partners tp ON tp.id = o.territory_partner_id
    WHERE o.id = ?
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$po || !in_array((int)$po['tp_db_id'], array_map('intval', $tpIds), true)) {
    header("Location: tp-purchase-order"); exit;
}

// Seller — primary godown this company user can see
$result_Godown = $db_conn->query("SELECT * FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " LIMIT 1")->fetch_assoc();

// Self-migrating: see db_migrations/2026_08_21_products_packs_per_cover.sql
$_ppcCol = $db_conn->query("SHOW COLUMNS FROM products LIKE 'packs_per_cover'");
if ($_ppcCol && $_ppcCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE products ADD COLUMN packs_per_cover INT UNSIGNED NULL AFTER packs_per_carton");
}

// Line items with product + packing details
$stmt2 = $db_conn->prepare("
    SELECT i.qty, i.price,
           p.productName, p.hsn, p.packs_per_carton, p.packs_per_cover
    FROM tp_purchase_order_items i
    JOIN products p ON p.id = i.product_id
    WHERE i.po_id = ?
    ORDER BY i.id
");
$stmt2->bind_param("i", $po_id);
$stmt2->execute();
$po_items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$boxTotals = dispatchSlipComputeBoxes($db_conn, $po_items);
$TotalQty123          = $boxTotals['TotalQty'];
$TotalCartons123      = $boxTotals['TotalCartons'];
$TotalBoxesDisplay123 = $boxTotals['TotalBoxesDisplay'];

// Ship-to address — the PO's one-off address if the TP typed one at
// submission time, otherwise the TP's registered default.
$useDefaultDelivery = (int)$po['use_default_delivery_address'] === 1;
$delivery_parts = $useDefaultDelivery ? array_filter([
    $po['delivery_line1'],
    $po['delivery_line2'],
    implode(', ', array_filter([$po['delivery_city'], $po['delivery_district']])),
    implode(', ', array_filter([$po['delivery_state'], $po['delivery_country']])),
    !empty($po['delivery_pincode']) ? 'Pincode: ' . $po['delivery_pincode'] : '',
]) : array_filter([
    $po['custom_delivery_line1'],
    $po['custom_delivery_line2'],
    implode(', ', array_filter([$po['custom_delivery_city'], $po['custom_delivery_district']])),
    implode(', ', array_filter([$po['custom_delivery_state'], $po['custom_delivery_country']])),
    !empty($po['custom_delivery_pincode']) ? 'Pincode: ' . $po['custom_delivery_pincode'] : '',
]);

// Bill-to address — TP's registered branch/billing address
$branch_parts = array_filter([
    $po['branch_line1'],
    $po['branch_line2'],
    implode(', ', array_filter([$po['branch_city'], $po['branch_district']])),
    implode(', ', array_filter([$po['branch_state'], $po['branch_country']])),
    !empty($po['branch_pincode']) ? 'Pincode: ' . $po['branch_pincode'] : '',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispatch Slip : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php"); ?>
            <?php include("femi_menu.php"); ?>
        </div>
        <div class="app-container">
            <?php include("app-header.php"); ?>
            <div class="app-content">

                <script type="text/javascript">
                function PrintDiv() {
                    var divToPrint = document.getElementById('divToPrint');
                    var popupWin = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
                    popupWin.document.open();
                    popupWin.document.write(
                        '<html><head><style>' +
                        '@page { margin: 0; size: auto; }' +
                        'body { margin: 10mm; }' +
                        '</style></head>' +
                        '<body onload="window.print()">' + divToPrint.innerHTML + '</body></html>'
                    );
                    popupWin.document.close();
                }
                </script>

                <table align="right">
                <tr>
                    <td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
                    <td><button type="button" onClick="javascript:window.location='tp-purchase-order';" class="btn btn-primary m-b-xs m-r-xs">Back to Orders</button></td>
                </tr>
                </table>
                <br/>
                <div style="clear:both;"></div>

                <div id="divToPrint"><!--Print content start-->

<style type="text/css">
.maincontainar{width:100%;height:auto;border:1px solid #000;}
.maincontainar hr{border-bottom:1px solid #000;}

#toptl{width:100%;padding:5px;font-family:arial;font-weight:bold;border-bottom:1px solid #000;text-align:center;font-size:22px;}

.second_containar{width:100%;border-collapse:collapse;}
.second_containar td:nth-child(1){border-right:1px solid #000;padding:0px;}

#second_topvl{width:100%;padding:5px;font-family:arial;border-bottom:1px solid #000;border-collapse:collapse;}
#second_topvl td{padding:5px;}
#border_nbottom td{border-bottom:1px solid #000;}

#noneborder td{border:0px !important;font-family:arial;font-size:14px;line-height:20px;}

.item_list{width:100%;border-top:1px solid #000;border-collapse:collapse;font-family:arial;}
.item_list td{border-right:1px solid #000;padding:5px;font-size:14px;vertical-align:top;}
#bordervl td{border-bottom:1px solid #000;padding:5px;}
#rightlaign{text-align:right;}
#bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}

#cmpname{font-size:17px;font-weight:bold;}
.cusdetaiis{margin-left:10px;font-family:arial;font-size:14px;line-height:20px;}
#shiippingaddress{margin-left:10px;font-family:arial;}

#sealsign{border-collapse:collapse;}
#sealsign td{padding:3px;}
#sealsign tr:nth-child(1){border-top:1px solid #000;}
#sealsign tr td:nth-child(1){border-right:1px solid #000;}

@media print {
    @page { margin: 0; size: auto; }
    body { margin: 10mm; }
}
</style>

<div class="maincontainar">

<table id="toptl">
<tr>
<td>Dispatch Slip</td>
</tr>
</table>

<!------PO DETAILS----->
<table class="second_containar">
<tr valign="top">
<td width="50%">
<table id="noneborder">
<tr valign="top">
<td>
<?php if (!empty($result_Godown['logo'])): ?>
<img src="<?= $result_Godown['logo']; ?>" style="width:150px;margin-right:5px;"/>
<?php endif; ?>
</td>
<td valign="top">
<span id="cmpname"><?= htmlspecialchars($result_Godown['gname'] ?? ''); ?></span><br/>
<?= htmlspecialchars($result_Godown['address_line1'] ?? ''); ?><br/>
<?= htmlspecialchars($result_Godown['address_line2'] ?? ''); ?><br/>
<b>Contact</b> : <?= htmlspecialchars($result_Godown['contact'] ?? ''); ?>
</td>
</tr>
</table>
<hr/>

<p class="cusdetaiis">
Ship to (Consignee):<br/>
<?php if (!empty($po['tp_company_name'])): ?><b><?= htmlspecialchars($po['tp_company_name']); ?></b><br/><?php endif; ?>
<?= htmlspecialchars($po['tp_name']); ?><br/>
<?php if (!empty($po['tp_gstin'])): ?>GSTIN: <?= htmlspecialchars($po['tp_gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?= htmlspecialchars($po['tp_mobile']); ?><br/>
<?= implode('<br/>', array_map('htmlspecialchars', $delivery_parts)); ?>
</p>

<hr/>
<p class="cusdetaiis">
Bill to (Buyer):<br/>
<?php if (!empty($po['tp_company_name'])): ?><b><?= htmlspecialchars($po['tp_company_name']); ?></b><br/><?php endif; ?>
<?= htmlspecialchars($po['tp_name']); ?><br/>
<?php if (!empty($po['tp_gstin'])): ?>GSTIN: <?= htmlspecialchars($po['tp_gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?= htmlspecialchars($po['tp_mobile']); ?><br/>
<?= implode('<br/>', array_map('htmlspecialchars', $branch_parts)); ?>
</p>
</td>

<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>PO #<br/><b><?= htmlspecialchars($po['id']); ?></b></td>
<td>Order Date:<br/><b><?= date("d M Y", strtotime($po['order_date'])); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Status<br/><b><?= htmlspecialchars(ucfirst($po['status'])); ?></b></td>
<td>Dispatched through<br/>&nbsp;</td>
</tr>
</table>
<p id="shiippingaddress">
Terms of Delivery<br/>&nbsp;
</p>
</td>
</tr>
</table>

<!------ITEM DETAILS----->
<table class="item_list">
<tr id="bordervl">
<td>Sl No.</td>
<td>Description of Goods</td>
<td id="rightlaign">HSN/SAC</td>
<td id="rightlaign">Quantity</td>
<td id="rightlaign">Rate</td>
<td id="rightlaign">Packs/Carton</td>
<td id="rightlaign">Cartons</td>
<td id="rightlaign">Box</td>
</tr>

<?php $slno = 0; foreach ($po_items as $item):
    $slno++;
    $qty  = (int)$item['qty'];
    $rate = (float)$item['price'];
?>
<tr>
<td><?= $slno; ?></td>
<td><b><?= htmlspecialchars($item['productName']); ?></b></td>
<td id="rightlaign"><?= htmlspecialchars($item['hsn']); ?></td>
<td id="rightlaign"><?= inr_format($qty, 0); ?> Packs</td>
<td id="rightlaign"><?= inr_format($rate, 2); ?></td>
<td id="rightlaign"><?= ($item['packs_per_carton'] !== null && $item['packs_per_carton'] !== '') ? inr_format((int)$item['packs_per_carton'], 0) : '—'; ?></td>
<td id="rightlaign"><?= $item['carton_display']; ?></td>
<td id="rightlaign"><?= $item['box_display']; ?></td>
</tr>
<?php endforeach; ?>

<tr id="bottombordervl">
<td></td><td></td><td id="rightlaign"><b>Total</b></td>
<td id="rightlaign"><b><?= inr_format($TotalQty123, 0); ?> Packs</b></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?= inr_format($TotalCartons123, 0); ?> ctn</b></td>
<td id="rightlaign"><b><?= $TotalBoxesDisplay123; ?></b></td>
</tr>
</table>
<div style="clear:both;"></div>

<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Checked By</td>
<td align="right">Dispatched By</td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div>
<div align="center">
    This is a Computer Generated Dispatch Slip
</div>

                </div><!--Print content end-->

            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
