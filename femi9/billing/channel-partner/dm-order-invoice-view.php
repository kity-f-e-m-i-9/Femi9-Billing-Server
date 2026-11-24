<?php
// Read-only invoice view for a CP's own "DM Orders" (dm-orders.php) once an
// order shows the "Invoiced" badge there. The real invoice is always issued
// BY COMPANY from one of its own godowns (company/shop-user-invoice-print.php
// is the company-admin equivalent of this same invoice) — a CP fulfilling a
// Get Order never becomes the invoice's legal seller, fulfilled_by_cp_id is
// only an internal "who sourced this stock" tag — so this page renders the
// exact same company_godown-as-seller invoice, just reachable from the CP's
// own session/login instead of requiring a company one. 2026-11-20, user
// request ("cp ku completed ana invoice kattanum").
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/db-helpers.php';
require_once __DIR__ . '/../shared/number-format-helpers.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

function fmt_gst_pct_cpdmo($v) {
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

$cpId = (int)$Login_user_IDvl;
$order_id = $_REQUEST['order_id'] ?? '';
if ($order_id === '') { header('Location: dm-orders.php'); exit; }

// Ownership check — this order must be one of THIS CP's own DM Orders
// (same assigned_cp_id scoping dm-orders.php's own listing query uses), not
// just any order id someone might type into the URL.
$stmtOwn = $db_conn->prepare("SELECT 1 FROM ms_orders WHERE order_id = ? AND assigned_cp_id = ? LIMIT 1");
$stmtOwn->bind_param('si', $order_id, $cpId);
$stmtOwn->execute();
if (!$stmtOwn->get_result()->fetch_assoc()) {
    $_SESSION['errorMessage'] = 'That order was not found in your DM Orders.';
    header('Location: dm-orders.php');
    exit;
}
$stmtOwn->close();

$stmtInv = $db_conn->prepare("SELECT * FROM user_invoice WHERE source_ms_order_id = ? LIMIT 1");
$stmtInv->bind_param('s', $order_id);
$stmtInv->execute();
$result_Invoice_Details = $stmtInv->get_result()->fetch_assoc();
$stmtInv->close();
if (!$result_Invoice_Details) {
    $_SESSION['errorMessage'] = 'This order has not been invoiced yet.';
    header('Location: dm-orders.php');
    exit;
}
$Invoice_ID = $result_Invoice_Details['inv_id'];

// Customer (shop) details
$customer_id = $result_Invoice_Details['to_user_id'];
$stmtCust = $db_conn->prepare("SELECT * FROM shop WHERE temp_id = ? LIMIT 1");
$stmtCust->bind_param('s', $customer_id);
$stmtCust->execute();
$result_Customer_Details = $stmtCust->get_result()->fetch_assoc() ?: [];
$stmtCust->close();

$state_name = '';
if (!empty($result_Customer_Details['state_id'])) {
    $stmtState = $db_conn->prepare("SELECT st_name FROM state WHERE id = ? LIMIT 1");
    $stmtState->bind_param('i', $result_Customer_Details['state_id']);
    $stmtState->execute();
    $state_name = $stmtState->get_result()->fetch_assoc()['st_name'] ?? '';
    $stmtState->close();
}
$district_name = '';
if (!empty($result_Customer_Details['district_id'])) {
    $stmtDist = $db_conn->prepare("SELECT dist_name FROM district WHERE id = ? LIMIT 1");
    $stmtDist->bind_param('i', $result_Customer_Details['district_id']);
    $stmtDist->execute();
    $district_name = $stmtDist->get_result()->fetch_assoc()['dist_name'] ?? '';
    $stmtDist->close();
}

// Seller (company godown) details — the invoice's real, legal seller,
// regardless of whether this CP or Company physically fulfilled it.
$result_Godown = [];
if (!empty($result_Invoice_Details['from_user_id'])) {
    $stmtGodown = $db_conn->prepare("SELECT * FROM company_godown WHERE id = ? LIMIT 1");
    $stmtGodown->bind_param('i', $result_Invoice_Details['from_user_id']);
    $stmtGodown->execute();
    $result_Godown = $stmtGodown->get_result()->fetch_assoc() ?: [];
    $stmtGodown->close();
}

// GST computed fresh from the product master at print time (inclusive vs
// exclusive tax treatment) — same convention as shop-user-invoice-print.php.
$stmtItems = $db_conn->prepare(
    "SELECT uii.*, p.productName, p.hsn AS p_hsn, p.mrp AS p_mrp, p.gst AS p_gst, p.gst_type AS p_gst_type
     FROM user_invoice_items uii
     JOIN products p ON p.id = uii.pr_id
     WHERE uii.inv_id = ? ORDER BY uii.id DESC"
);
$stmtItems->bind_param('s', $Invoice_ID);
$stmtItems->execute();
$fetch_INVProductDetails = $stmtItems->get_result();
$invoice_items = [];
$TotalAMount123 = 0; $Totalquantity123 = 0; $totalgstamount = 0;
$hsn_totals = []; $hsn_gst_totals = []; $hsn_gst_pct = []; $__inv_gst_pct = 0;
while ($row = $fetch_INVProductDetails->fetch_assoc()) {
    $qty           = (int)$row['qty'];
    $gross_amount  = $qty * (float)$row['amount'];
    $item_disc_amt = (float)$row['discount_amount'];
    $net_amount    = $gross_amount - $item_disc_amt;
    $gst_pct       = (int)$row['p_gst'];
    $gst_type_item = $row['p_gst_type'] ?: 'exclusive';

    if ($gst_type_item === 'inclusive' && $gst_pct > 0) {
        $gross_taxable_value = $gross_amount * 100 / (100 + $gst_pct);
        $taxable_value       = $net_amount * 100 / (100 + $gst_pct);
        $gst_amount          = $net_amount - $taxable_value;
    } else {
        $gross_taxable_value = $gross_amount;
        $taxable_value       = $net_amount;
        $gst_amount          = $net_amount * $gst_pct / 100;
    }
    $row['taxable_value']     = $taxable_value;
    $row['gst_amount']        = $gst_amount;
    $row['taxable_rate']      = $qty > 0 ? $gross_taxable_value / $qty : 0;
    $row['taxable_rate_incl'] = $row['taxable_rate'] + ($gst_pct > 0 ? $row['taxable_rate'] * $gst_pct / 100 : 0);
    $row['gst_pct']           = $gst_pct;
    $row['gst_type_item']     = $gst_type_item;
    $invoice_items[] = $row;

    $TotalAMount123   += $taxable_value;
    $Totalquantity123 += $qty;
    $totalgstamount   += $gst_amount;
    $hsn = $row['p_hsn'] ?: '-';
    $hsn_totals[$hsn]     = ($hsn_totals[$hsn] ?? 0) + $taxable_value;
    $hsn_gst_totals[$hsn] = ($hsn_gst_totals[$hsn] ?? 0) + $gst_amount;
    $hsn_gst_pct[$hsn]    = $gst_pct;
    $__inv_gst_pct        = max($__inv_gst_pct, $gst_pct);
}
$has_gst_product = $totalgstamount > 0;
$invoice_heading = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';
$gsttype = $result_Invoice_Details['gst_type'];
$Currency_symbol = "&#8377;";
$Currency_Name = "INR";
$result = number_to_words_inr($result_Invoice_Details['total'] ?? 0);
$TAXresult = number_to_words_inr($totalgstamount);
$invno = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice : <?php echo $business_name ?? 'Femi9'; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .maincontainar{width:100%;height:auto;border:1px solid #000;}
        .maincontainar hr{border-bottom:1px solid #000;}
        #toptl{width:100%;padding:5px;font-family:arial;font-weight:bold;border-bottom:1px solid #000;text-align:center;font-size:22px;}
        .second_containar{width:100%;border-collapse:collapse;}
        #second_topvl{width:100%;padding:5px;font-family:arial;border-bottom:1px solid #000;border-collapse:collapse;}
        #second_topvl td{padding:5px;}
        #border_nbottom td{border-bottom:1px solid #000;}
        .second_containar td:nth-child(1){border-right:1px solid #000;padding:0px;}
        #noneborder td{border:0px !important;font-family:arial;font-size:14px;line-height:20px;}
        .item_list{width:100%;border-top:1px solid #000;border-collapse:collapse;font-family:arial;}
        .item_list td{border-right:1px solid #000;padding:5px;font-size:14px;vertical-align:top;}
        .item_list td:last-child{border-right:0;padding-right:8px;}
        #bordervl td{border-bottom:1px solid #000;padding:5px;}
        #rightlaign{text-align:right;}
        #bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}
        #cmpname{font-size:17px;font-weight:bold;}
        .cusdetaiis{margin-left:10px;font-family:arial;font-size:14px;line-height:20px;}
        #shiippingaddress{margin-left:10px;font-family:arial;}
        #hsnsac{border-collapse:collapse;}
        #hsnsac tr td{border:1px solid #000;}
        #hsnsac tr td:nth-child(1){border-left:0px;}
        #hsnsac tr td:nth-child(2){border-right:0px;}
        #sealsign{border-collapse:collapse;}
        #sealsign td{padding:3px;}
        #sealsign tr:nth-child(1){border-top:1px solid #000;}
        #sealsign tr td:nth-child(1){border-right:1px solid #000;}
    </style>
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
                    popupWin.document.write('<html><body onload="window.print()">' + divToPrint.innerHTML + '</html>');
                    popupWin.document.close();
                }
                </script>
                <table align="right">
                    <tr>
                        <td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
                        <td><button type="button" onClick="javascript:window.location='dm-orders.php';" class="btn btn-primary m-b-xs m-r-xs">Back to DM Orders</button></td>
                    </tr>
                </table>
                <div style="clear:both;"></div>

                <div id="divToPrint">
                <div class="maincontainar">
                <table id="toptl"><tr><td><?php echo $invoice_heading; ?></td></tr></table>

                <table class="second_containar">
                <tr valign="top">
                <td width="50%">
                <table id="noneborder">
                <tr valign="top">
                <td>
                <?php if (!empty($result_Godown['logo'])): ?>
                <img src="<?php echo htmlspecialchars((string)($result_Godown['logo']), ENT_QUOTES); ?>" style="width:150px;margin-right:5px;"/>
                <?php endif; ?>
                </td>
                <td valign="top">
                <span id="cmpname"><?php echo htmlspecialchars((string)($result_Godown['gname'] ?? ''), ENT_QUOTES); ?></span><br/>
                <?php echo htmlspecialchars((string)($result_Godown['address_line1'] ?? ''), ENT_QUOTES); ?><br/>
                <?php echo htmlspecialchars((string)($result_Godown['address_line2'] ?? ''), ENT_QUOTES); ?><br/>
                <b>GSTIN/UIN :</b> <?php echo htmlspecialchars((string)($result_Godown['gstin'] ?? ''), ENT_QUOTES); ?><br/>
                <b>State Name</b> : <?php echo htmlspecialchars((string)($result_Godown['state'] ?? ''), ENT_QUOTES); ?> <b>Code</b> : <?php echo htmlspecialchars((string)($result_Godown['state_code'] ?? ''), ENT_QUOTES); ?><br/>
                <b>Contact</b> : <?php echo htmlspecialchars((string)($result_Godown['contact'] ?? ''), ENT_QUOTES); ?><br/>
                <b>Email</b> : <?php echo htmlspecialchars((string)($result_Godown['email'] ?? ''), ENT_QUOTES); ?>
                </td>
                </tr>
                </table>
                <hr/>
                <p class="cusdetaiis">
                Consignee (Ship to):<br/>
                <b><?php echo htmlspecialchars((string)ucwords((string)($result_Customer_Details['name'] ?? '')), ENT_QUOTES); ?></b><br/>
                <?php echo htmlspecialchars((string)ucwords((string)($result_Customer_Details['address'] ?? '')), ENT_QUOTES); ?><br/>
                <?php if (!empty($result_Customer_Details['gstin'])): ?>
                GSTIN: <?php echo htmlspecialchars((string)($result_Customer_Details['gstin']), ENT_QUOTES); ?><br/>
                <?php endif; ?>
                Mobile:&nbsp;<?php echo htmlspecialchars((string)($result_Customer_Details['mobile_number'] ?? ''), ENT_QUOTES); ?><br/>
                State : <?php echo htmlspecialchars($state_name); ?>, District: <?php echo htmlspecialchars($district_name); ?>
                </p>
                </td>
                <td valign="top">
                <table id="second_topvl">
                <tr id="border_nbottom">
                <td>Invoice #<br/><b><?php echo htmlspecialchars((string)($result_Invoice_Details['inv_number'] ?? ''), ENT_QUOTES); ?></b></td>
                <td>Invoice Date:<br/><b><?php echo date("d M Y", strtotime($result_Invoice_Details['date'])); ?></b></td>
                </tr>
                </table>
                </td>
                </tr>
                </table>

                <table class="item_list">
                <tr id="bordervl">
                <td>Sl No.</td>
                <td>Description of Goods</td>
                <td id="rightlaign">HSN/SAC</td>
                <td id="rightlaign">Quantity</td>
                <td id="rightlaign">MRP</td>
                <td id="rightlaign">Rate (Excl. Tax)</td>
                <td id="rightlaign">Rate (Incl. Tax)</td>
                <td id="rightlaign">per</td>
                <td id="rightlaign">GST(%)</td>
                <td id="rightlaign">Disc</td>
                <td id="rightlaign">Amount</td>
                </tr>
                <?php foreach ($invoice_items as $result_INVProductDetails): $qty = (int)$result_INVProductDetails['qty']; ?>
                <tr>
                <td><?php echo $invno = $invno + 1; ?></td>
                <td><b><?php echo htmlspecialchars((string)($result_INVProductDetails['productName']), ENT_QUOTES); ?></b><?php echo $result_INVProductDetails['gst_type_item'] === 'inclusive' ? ' <small style="color:#666">(GST incl.)</small>' : ''; ?></td>
                <td id="rightlaign"><?php echo htmlspecialchars((string)($result_INVProductDetails['p_hsn']), ENT_QUOTES); ?></td>
                <td id="rightlaign"><?php echo $qty; ?> Packs</td>
                <td id="rightlaign"><?php echo inr_format($result_INVProductDetails['p_mrp'], 2); ?></td>
                <td id="rightlaign"><?php echo inr_format($result_INVProductDetails['taxable_rate'], 2); ?></td>
                <td id="rightlaign"><?php echo inr_format($result_INVProductDetails['taxable_rate_incl'], 2); ?></td>
                <td id="rightlaign">Packs</td>
                <td id="rightlaign"><?php echo htmlspecialchars((string)($result_INVProductDetails['gst_pct']), ENT_QUOTES); ?>%</td>
                <td id="rightlaign"><?php echo inr_format($result_INVProductDetails['discount_amount'], 2); ?> (<?php echo fmt_gst_pct_cpdmo($result_INVProductDetails['discount_percentage']); ?>%)</td>
                <td id="rightlaign"><?php echo inr_format($result_INVProductDetails['taxable_value'], 2); ?></td>
                </tr>
                <?php endforeach; ?>

                <tr id="bottombordervl">
                <td></td>
                <td id="rightlaign"><b></b></td>
                <td></td>
                <td id="rightlaign"><b><?php echo $Totalquantity123; ?> Packs</b></td>
                <td></td><td></td><td></td><td></td><td></td><td></td>
                <td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($TotalAMount123, 2); ?></b></td>
                </tr>

                <?php
                $__half_pct = fmt_gst_pct_cpdmo($__inv_gst_pct / 2);
                if ($totalgstamount > 0):
                    if ($gsttype === "inner"):
                        $SGST = inr_format($totalgstamount / 2, 2);
                        $CGST = inr_format($totalgstamount / 2, 2);
                ?>
                <tr id="bottombordervl">
                <td></td><td id="rightlaign"><b><i>SGST (<?php echo $__half_pct; ?>%)</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $SGST; ?></b></td>
                </tr>
                <tr id="bottombordervl">
                <td></td><td id="rightlaign"><b><i>CGST (<?php echo $__half_pct; ?>%)</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $CGST; ?></b></td>
                </tr>
                <?php else: ?>
                <tr id="bottombordervl">
                <td></td><td id="rightlaign"><b><i>IGST (<?php echo fmt_gst_pct_cpdmo($__inv_gst_pct); ?>%)</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($totalgstamount, 2); ?></b></td>
                </tr>
                <?php endif; endif; ?>

                <?php if (($result_Invoice_Details['discount'] ?? 0) > 0): ?>
                <tr id="bottombordervl">
                <td></td><td id="rightlaign"><b><i>Discount</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($result_Invoice_Details['discount'], 2); ?></b></td>
                </tr>
                <?php endif; ?>

                <?php if (($result_Invoice_Details['roundoff'] ?? 0) != 0): ?>
                <tr id="bottombordervl">
                <td></td><td id="rightlaign"><b><i>Round off</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($result_Invoice_Details['roundoff'], 2); ?></b></td>
                </tr>
                <?php endif; ?>

                <tr id="bottombordervl">
                <td></td><td id="rightlaign"><b><i>Total</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($result_Invoice_Details['total'], 2); ?></b></td>
                </tr>
                </table>
                <div style="clear:both;"></div>

                <table width="100%">
                <tr><td width="70%">Amount Chargeable (in words)</td><td align="right">E. &amp; O.E</td></tr>
                <tr><td><b><?php echo $Currency_Name; ?> <?php echo ucwords($result); ?> Only</b></td><td></td></tr>
                </table>

                <table width="100%">
                <tr><td style="padding-top:6px;font-size:13px;">Amount Taxable (in words)</td><td align="right" style="font-size:13px;"><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($TotalAMount123, 2); ?></td></tr>
                <tr><td><b><?php echo $Currency_Name; ?> <?php echo $totalgstamount > 0 ? ucwords($TAXresult) : 'Nil'; ?> Only</b></td><td></td></tr>
                </table>

                <?php if ($gsttype === "inner"): ?>
                <table width="100%" id="hsnsac">
                <tr><td align="center">HSN/SAC</td><td align="right">Taxable<br/>Value</td><td align="right" colspan="2">CGST</td><td align="right" colspan="2">SGST/UTGST</td><td align="right">Total<br/>Tax Amount</td></tr>
                <tr><td></td><td></td><td align="right">Rate</td><td align="right">Amount</td><td align="right">Rate</td><td align="right">Amount</td><td></td></tr>
                <?php foreach ($hsn_totals as $hsncode => $hsnamt): $hsn_gst = $hsn_gst_totals[$hsncode] ?? 0; $hsn_half = $hsn_gst / 2; $hsn_rate = ($hsn_gst_pct[$hsncode] ?? 0) / 2; ?>
                <tr>
                <td><?php echo htmlspecialchars($hsncode); ?></td>
                <td align="right"><?php echo inr_format($hsnamt, 2); ?></td>
                <td align="right"><?php echo fmt_gst_pct_cpdmo($hsn_rate); ?>%</td>
                <td align="right"><?php echo inr_format($hsn_half, 2); ?></td>
                <td align="right"><?php echo fmt_gst_pct_cpdmo($hsn_rate); ?>%</td>
                <td align="right"><?php echo inr_format($hsn_half, 2); ?></td>
                <td align="right"><?php echo inr_format($hsn_gst, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                <td align="right"><b>Total&nbsp;</b></td>
                <td align="right"><b><?php echo inr_format($TotalAMount123, 2); ?></b></td>
                <td></td><td align="right"><b><?php echo inr_format($totalgstamount / 2, 2); ?></b></td>
                <td></td><td align="right"><b><?php echo inr_format($totalgstamount / 2, 2); ?></b></td>
                <td align="right"><b><?php echo inr_format($totalgstamount, 2); ?></b></td>
                </tr>
                </table>
                <?php else: ?>
                <table width="100%" id="hsnsac">
                <tr><td align="center">HSN/SAC</td><td align="right">Taxable<br/>Value</td><td align="right">IGST Rate</td><td align="right">IGST Amount</td></tr>
                <?php foreach ($hsn_totals as $hsncode => $hsnamt): $hsn_gst = $hsn_gst_totals[$hsncode] ?? 0; $hsn_rate = $hsn_gst_pct[$hsncode] ?? 0; ?>
                <tr>
                <td><?php echo htmlspecialchars($hsncode); ?></td>
                <td align="right"><?php echo inr_format($hsnamt, 2); ?></td>
                <td align="right"><?php echo fmt_gst_pct_cpdmo($hsn_rate); ?>%</td>
                <td align="right"><?php echo inr_format($hsn_gst, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                <td align="right"><b>Total&nbsp;</b></td>
                <td align="right"><b><?php echo inr_format($TotalAMount123, 2); ?></b></td>
                <td></td><td align="right"><b><?php echo inr_format($totalgstamount, 2); ?></b></td>
                </tr>
                </table>
                <?php endif; ?>

                <table width="100%">
                <tr>
                <td width="50%">
                <?php if ($totalgstamount > 0): ?>
                <div>&nbsp;Tax Amount (in words): <b><?php echo $Currency_Name; ?> <?php echo ucwords($TAXresult); ?> Only</b></div>
                <?php else: ?>
                <div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
                <?php endif; ?>
                <br/>
                <div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>
                </td>
                <td></td>
                </tr>
                </table>

                <table width="100%" id="sealsign">
                <tr>
                <td width="50%" align="left">Customer's Seal and Signature</td>
                <td align="right">for <b><?php echo htmlspecialchars((string)($result_Godown['gname'] ?? ''), ENT_QUOTES); ?></b></td>
                </tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td></td><td align="right">Authorised Signatory</td></tr>
                </table>
                <div style="clear:both;"></div>
                </div>
                <div align="center">This is a Computer Generated Invoice</div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
