<?php
include("checksession.php");
require_once("include/GodownAccess.php");
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");

$tempid = $_REQUEST['tempid'];

$stmt = $db_conn->prepare("SELECT * FROM ot_sales_invoice WHERE tempid = ?");
$stmt->bind_param("s", $tempid);
$stmt->execute();
$result_Invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $db_conn->prepare("SELECT * FROM ot_sales WHERE tempid = ? LIMIT 1");
$stmt->bind_param("s", $tempid);
$stmt->execute();
$result_Invoice_Details = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Godown details
$from_user_id = $result_Invoice_Details['godownid'];
$result_Godown = null;
if (is_godown_allowed($db_conn, (int)$from_user_id)) {
$stmt = $db_conn->prepare("SELECT * FROM company_godown WHERE id = ?");
$stmt->bind_param("s", $from_user_id);
$stmt->execute();
$result_Godown = $stmt->get_result()->fetch_assoc();
$stmt->close();
}

// Delivery note
$Invoice_ID = $result_Invoice['inv_id'] ?? '';
$stmt = $db_conn->prepare("SELECT * FROM delivery_note WHERE inv_id = ? LIMIT 1");
$stmt->bind_param("s", $Invoice_ID);
$stmt->execute();
$Result_DLDetails = $stmt->get_result()->fetch_assoc();
$stmt->close();

$wallet_amount_show = (float) ($result_Invoice['wallet_amount'] ?? 0);
$Courier_Charges    = (float) ($result_Invoice['courier_charges'] ?? 0);
$subtotal           = (float) ($result_Invoice['subtotal'] ?? 0);
$roundoff           = (float) ($result_Invoice['round_off'] ?? 0);

// Always calculate from raw parts: items + courier + roundoff
$gross_before_wallet = $subtotal + $Courier_Charges + $roundoff;

// Net payable = gross - wallet
$Total_amount_show   = $gross_before_wallet - $wallet_amount_show;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice : <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png">
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
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
                    var divToPrint  = document.getElementById('divToPrint');
                    var popupWin    = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
                    popupWin.document.open();
                    popupWin.document.write('<html><body onload="window.print()">' + divToPrint.innerHTML + '</html>');
                    popupWin.document.close();
                }
            </script>

            <table align="right">
                <tr>
                    <td><button type="button" onclick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
                    <td><button type="button" onclick="window.location='ot-sale-add';" class="btn btn-success m-b-xs m-r-xs">+ New Invoice</button></td>
                    <td><button type="button" onclick="window.location='ot-sale-view';" class="btn btn-primary m-b-xs m-r-xs">Manage Invoice</button></td>
                </tr>
            </table>
            <div style="clear:both;"></div>

            <div id="divToPrint">

<style type="text/css">
.maincontainar{width:100%;height:auto;border:1px solid #000;}
.maincontainar hr{border-bottom:1px solid #000;}
#toptl{width:100%;padding:5px;font-family:arial;font-weight:bold;border-bottom:1px solid #000;text-align:center;font-size:22px;}
.second_containar{width:100%;}
#second_topvl{width:100%;padding:5px;font-family:arial;border-bottom:1px solid #000;border-collapse:collapse;}
#second_topvl td{padding:5px;}
#border_nbottom td{border-bottom:1px solid #000;}
.second_containar{width:100%;border-collapse:collapse;}
.second_containar td:nth-child(1){border-right:1px solid #000;padding:0px;}
#noneborder td{border:0px !important;font-family:arial;font-size:14px;line-height:20px;}
.item_list{width:100%;border-top:1px solid #000;border-collapse:collapse;font-family:arial;}
.item_list td{border-right:1px solid #000;padding:5px;font-size:14px;vertical-align:top;}
#bordervl td{border-bottom:1px solid #000;padding:5px;}
#rightlaign{text-align:right;}
#bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}
.amount_word{font-family:arial;padding:4px;border-bottom:1px solid #000;}
.amount_payable{font-family:arial;padding:4px;border-bottom:1px solid #000;text-align:right;}
#bottom_bank{font-family:arial;width:100%;border-bottom:1px solid #000;}
#bottom_bank tr td:nth-child(1){border-right:1px solid #000;}
#bottom_bank table td{border:0px !important;}
#vlnotes{font-family:arial;width:100%;}
#vlnotes tr td:nth-child(1){border-right:1px solid #000;width:35%;}
#cmpname{font-size:17px;font-weight:bold;}
.cusdetaiis{margin-left:10px;font-family:arial;font-size:14px;line-height:20px;}
#shiippingaddress{margin-left:10px;font-family:arial;}
#pageno{font-family:arial;padding:20px 0px 20px 0px;}
#hsnsac{border-collapse:collapse;}
#hsnsac tr td{border:1px solid #000;}
#hsnsac tr td:nth-child(1){border-left:0px;}
#hsnsac tr td:nth-child(2){border-right:0px;}
#sealsign{border-collapse:collapse;}
#sealsign td{padding:3px;}
#sealsign tr:nth-child(1){border-top:1px solid #000;}
#sealsign tr td:nth-child(1){border-right:1px solid #000;}
.wallet-deduction{color:#c0392b;}
</style>

<div class="maincontainar">

    <table id="toptl">
        <tr><td>Bill of Supply</td></tr>
    </table>

    <!-- INVOICE HEADER -->
    <table class="second_containar">
        <tr valign="top">
            <!-- Left: Company + Customer -->
            <td width="50%">
                <table id="noneborder">
                    <tr valign="top">
                        <td>
                            <?php if (!empty($result_Godown['logo'])): ?>
                                <img src="<?= htmlspecialchars($result_Godown['logo'], ENT_QUOTES) ?>" style="width:150px;margin-right:5px;">
                            <?php endif; ?>
                        </td>
                        <td valign="top">
                            <span id="cmpname"><?= htmlspecialchars($result_Godown['gname'], ENT_QUOTES) ?></span><br>
                            <?= htmlspecialchars($result_Godown['address_line1'], ENT_QUOTES) ?><br>
                            <?= htmlspecialchars($result_Godown['address_line2'], ENT_QUOTES) ?><br>
                            <b>GSTIN/UIN :</b> <?= htmlspecialchars($result_Godown['gstin'], ENT_QUOTES) ?><br>
                            <b>State Name</b> : <?= htmlspecialchars($result_Godown['state'], ENT_QUOTES) ?>
                            <b>Code</b> : <?= htmlspecialchars($result_Godown['state_code'], ENT_QUOTES) ?><br>
                            <b>Contact</b> : <?= htmlspecialchars($result_Godown['contact'], ENT_QUOTES) ?><br>
                            <b>Email</b> : <?= htmlspecialchars($result_Godown['email'], ENT_QUOTES) ?>
                        </td>
                    </tr>
                </table>
                <hr>
                <p class="cusdetaiis">
                    Consignee (Ship to):<br>
                    <b><?= htmlspecialchars($result_Invoice_Details['customer_name'], ENT_QUOTES) ?></b><br>
                    <?php if (!empty($result_Invoice_Details['gst_number'])): ?>
                        GSTIN: <?= htmlspecialchars($result_Invoice_Details['gst_number'], ENT_QUOTES) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($result_Invoice_Details['customer_address'], ENT_QUOTES) ?>
                </p>
                <hr>
                <p class="cusdetaiis">
                    Buyer (Bill to):<br>
                    <b><?= htmlspecialchars(ucwords($result_Invoice_Details['customer_name']), ENT_QUOTES) ?></b><br>
                    <?php if (!empty($result_Invoice_Details['gst_number'])): ?>
                        GSTIN: <?= htmlspecialchars($result_Invoice_Details['gst_number'], ENT_QUOTES) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($result_Invoice_Details['shipping_address'], ENT_QUOTES) ?>
                </p>
            </td>

            <!-- Right: Invoice Meta -->
            <td valign="top">
                <table id="second_topvl">
                    <tr id="border_nbottom">
                        <td>Invoice #<br><b><?= htmlspecialchars($result_Invoice['inv_number'], ENT_QUOTES) ?></b></td>
                        <td>Invoice Date:<br>
                            <b><?php if (!empty($result_Invoice_Details['date'])) echo date("d M Y", strtotime($result_Invoice_Details['date'])); ?></b>
                        </td>
                    </tr>
                    <tr id="border_nbottom" valign="top">
                        <td height="50">Delivery Note<br><?= htmlspecialchars($Result_DLDetails['dl_note'] ?? '', ENT_QUOTES) ?></td>
                        <td>Mode/Terms of Payment<br><?= htmlspecialchars($Result_DLDetails['mode_pmnt'] ?? '', ENT_QUOTES) ?></td>
                    </tr>
                    <tr id="border_nbottom" valign="top">
                        <td height="50">Reference No. & Date<br>
                            <?php
                            if (!empty($Result_DLDetails['ref_no'])) echo htmlspecialchars($Result_DLDetails['ref_no'], ENT_QUOTES) . ', ';
                            if (!empty($Result_DLDetails['ref_date'])) echo date("d/m/Y", strtotime($Result_DLDetails['ref_date']));
                            ?>
                        </td>
                        <td>Other References<br><?= htmlspecialchars($Result_DLDetails['ot_ref'] ?? '', ENT_QUOTES) ?></td>
                    </tr>
                    <tr id="border_nbottom" valign="top">
                        <td height="50">Buyer's Order No.<br>
                            <?= htmlspecialchars($result_Invoice_Details['order_number'] ?? '', ENT_QUOTES) ?>
                        </td>
                        <td>Dated<br>
                            <?php
                            if (!empty($result_Invoice_Details['order_date']) && $result_Invoice_Details['order_date'] !== '1991-01-01')
                                echo date("d/m/Y", strtotime($result_Invoice_Details['order_date']));
                            ?>
                        </td>
                    </tr>
                    <tr id="border_nbottom" valign="top">
                        <td height="50">Dispatch Doc No.<br><?= htmlspecialchars($Result_DLDetails['dispatch_doc_no'] ?? '', ENT_QUOTES) ?></td>
                        <td>Delivery Note Date<br>
                            <?php
                            if (!empty($Result_DLDetails['dlnote_date']))
                                echo date("d/m/Y", strtotime($Result_DLDetails['dlnote_date']));
                            ?>
                        </td>
                    </tr>
                    <tr id="border_nbottom" valign="top">
                        <td height="50">Dispatched through<br><?= htmlspecialchars($Result_DLDetails['dispatch_through'] ?? '', ENT_QUOTES) ?></td>
                        <td>Destination<br><?= htmlspecialchars($Result_DLDetails['destination'] ?? '', ENT_QUOTES) ?></td>
                    </tr>
                </table>
                <p id="shiippingaddress">
                    Terms of Delivery<br>
                    <?= htmlspecialchars($Result_DLDetails['terms'] ?? '', ENT_QUOTES) ?>
                </p>
            </td>
        </tr>
    </table>

    <!-- ITEM LIST -->
    <table class="item_list">
        <tr id="bordervl">
            <td>Sl No.</td>
            <td>Description of Goods</td>
            <td id="rightlaign">HSN/SAC</td>
            <td id="rightlaign">Quantity</td>
            <td id="rightlaign">MRP</td>
            <td id="rightlaign">Rate</td>
            <td id="rightlaign">per</td>
            <td id="rightlaign">GST(%)</td>
            <td id="rightlaign">Disc</td>
            <td id="rightlaign">Amount</td>
        </tr>

        <?php
        $ots              = 0;
        $TotalAMount123   = 0;
        $Totalquantity123 = 0;

        $stmt = $db_conn->prepare("SELECT os.*, p.productName, p.hsn, p.mrp, p.gst AS product_gst
                                   FROM ot_sales os
                                   LEFT JOIN products p ON p.id = os.prid
                                   WHERE os.tempid = ?
                                   ORDER BY os.id DESC");
        $stmt->bind_param("s", $tempid);
        $stmt->execute();
        $res_items = $stmt->get_result();

        while ($row = $res_items->fetch_assoc()):
            $ots++;
            $TotalAMount      = ($row['qty'] * $row['price']) - $row['discount'];
            $TotalAMount123  += $TotalAMount;
            $Totalquantity123 += $row['qty'];
            $discountPercentage = ($row['qty'] * $row['price']) > 0
                ? ($row['discount'] / ($row['qty'] * $row['price'])) * 100
                : 0;
        ?>
        <tr>
            <td><?= $ots ?></td>
            <td><b><?= htmlspecialchars($row['productName'], ENT_QUOTES) ?></b></td>
            <td id="rightlaign"><?= htmlspecialchars($row['hsn'], ENT_QUOTES) ?></td>
            <td id="rightlaign"><?= (int)$row['qty'] ?> Packs</td>
            <td id="rightlaign"><?= number_format($row['mrp'], 2, '.', '') ?></td>
            <td id="rightlaign"><?= number_format($row['price'], 2, '.', '') ?></td>
            <td id="rightlaign">Packs</td>
            <td id="rightlaign"><?= $row['gst'] ?>%</td>
            <td id="rightlaign">
                <?= number_format($row['discount'], 2, '.', '') ?>
                (<?= number_format($discountPercentage, 2, '.', '') ?>%)
            </td>
            <td id="rightlaign"><?= number_format($TotalAMount, 2, '.', '') ?></td>
        </tr>
        <?php endwhile; $stmt->close(); ?>

        <!-- Empty spacer row -->
        <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>

        <!-- Items subtotal row -->
        <tr id="bottombordervl">
            <td></td>
            <td id="rightlaign"><b></b></td>
            <td></td>
            <td id="rightlaign"><b><?= $Totalquantity123 ?> Packs</b></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign"><b>&#8377; <?= number_format($TotalAMount123, 2, '.', '') ?></b></td>
        </tr>

        <!-- GST rows -->
        <?php
        $totalgstamount = (float) ($result_Invoice_Details['gst_amount'] ?? 0);
        if ($totalgstamount > 0):
            $SGST = number_format($totalgstamount / 2, 2, '.', '');
            $CGST = number_format($totalgstamount / 2, 2, '.', '');
        ?>
        <tr id="bottombordervl">
            <td></td><td id="rightlaign"><b><i>SGST</i></b></td>
            <td></td><td id="rightlaign"></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign"><b>&#8377; <?= $SGST ?></b></td>
        </tr>
        <tr id="bottombordervl">
            <td></td><td id="rightlaign"><b><i>CGST</i></b></td>
            <td></td><td id="rightlaign"></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign"><b>&#8377; <?= $CGST ?></b></td>
        </tr>
        <?php endif; ?>

        <!-- Courier Charges row -->
        <?php if ($Courier_Charges != 0): ?>
        <tr id="bottombordervl">
            <td></td><td id="rightlaign"><b><i>Courier Charges</i></b></td>
            <td></td><td id="rightlaign"></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign"><b>&#8377; <?= number_format($Courier_Charges, 2, '.', '') ?></b></td>
        </tr>
        <?php endif; ?>

        <!-- Round off row -->
        <?php if (!empty($result_Invoice['round_off']) && $result_Invoice['round_off'] != 0): ?>
        <tr id="bottombordervl">
            <td></td><td id="rightlaign"><b><i>Round off</i></b></td>
            <td></td><td id="rightlaign"></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign"><b>&#8377; <?= number_format($result_Invoice['round_off'], 2, '.', '') ?></b></td>
        </tr>
        <?php endif; ?>

        <!-- Gross Total row (only shown when wallet was applied) -->
        <?php if ($wallet_amount_show > 0): ?>
        <tr id="bottombordervl">
            <td></td><td id="rightlaign"><b><i>Gross Total</i></b></td>
            <td></td><td id="rightlaign"></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign"><b>&#8377; <?= number_format($gross_before_wallet, 2, '.', '') ?></b></td>
        </tr>

        <!-- Wallet Deduction row -->
        <tr id="bottombordervl">
            <td></td>
            <td id="rightlaign"><b><i class="wallet-deduction">Wallet Deduction</i></b></td>
            <td></td><td id="rightlaign"></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign" class="wallet-deduction"><b>- &#8377; <?= number_format($wallet_amount_show, 2, '.', '') ?></b></td>
        </tr>
        <?php endif; ?>

        <!-- Net Payable Total -->
        <tr id="bottombordervl">
            <td></td><td id="rightlaign"><b><i>Total</i></b></td>
            <td></td><td id="rightlaign"></td>
            <td></td><td></td><td></td><td></td><td></td>
            <td id="rightlaign"><b>&#8377; <?= number_format($Total_amount_show, 2, '.', '') ?></b></td>
        </tr>

    </table>
    <div style="clear:both;"></div>

    <!-- AMOUNT IN WORDS -->
    <?php
    function amountToWords(float $number): string {
        $no       = floor($number);
        $words    = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five',
                     '6'=>'six','7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten',
                     '11'=>'eleven','12'=>'twelve','13'=>'thirteen','14'=>'fourteen',
                     '15'=>'fifteen','16'=>'sixteen','17'=>'seventeen','18'=>'eighteen',
                     '19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty',
                     '50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
        $digits   = ['','hundred','thousand','lakh','crore'];
        $digits_1 = strlen((string)$no);
        $i        = 0;
        $str      = [];

        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $num     = floor($no % $divider);
            $no      = floor($no / $divider);
            $i      += ($divider == 10) ? 1 : 2;
            if ($num) {
                $plural  = (($counter = count($str)) && $num > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[]   = ($num < 21)
                    ? $words[$num] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                    : $words[floor($num/10)*10] . ' ' . $words[$num%10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else {
                $str[] = null;
            }
        }
        return implode('', array_reverse($str));
    }

    $amountWords = amountToWords($Total_amount_show);
    $taxWords    = amountToWords($totalgstamount);
    ?>

    <table width="100%">
        <tr>
            <td width="70%">Amount Chargeable (in words)</td>
            <td align="right">E. &amp; O.E</td>
        </tr>
        <tr>
            <td><b>INR <?= ucwords(trim($amountWords)) ?> Only</b></td>
            <td></td>
        </tr>
    </table>

    <!-- HSN WISE TOTAL -->
    <table width="100%" id="hsnsac">
        <tr>
            <td width="70%" align="center">HSN/SAC</td>
            <td align="right">Taxable Value</td>
        </tr>
        <?php
        $stmt = $db_conn->prepare("SELECT DISTINCT hsn FROM ot_sales WHERE tempid = ?");
        $stmt->bind_param("s", $tempid);
        $stmt->execute();
        $hsn_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt_hsn_sum = $db_conn->prepare("SELECT SUM(total) FROM ot_sales WHERE tempid = ? AND hsn = ?");
        foreach ($hsn_rows as $hrow):
            $hsncode = $hrow['hsn'];
            $stmt_hsn_sum->bind_param("ss", $tempid, $hsncode);
            $stmt_hsn_sum->execute();
            $hsn_total = $stmt_hsn_sum->get_result()->fetch_row()[0];
        ?>
        <tr>
            <td><?= htmlspecialchars($hsncode, ENT_QUOTES) ?></td>
            <td align="right"><?= number_format($hsn_total, 2, '.', '') ?></td>
        </tr>
        <?php endforeach; $stmt_hsn_sum->close(); ?>

        <?php
        $stmt = $db_conn->prepare("SELECT SUM(total) FROM ot_sales WHERE tempid = ?");
        $stmt->bind_param("s", $tempid);
        $stmt->execute();
        $hsn_grand = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        ?>
        <tr>
            <td align="right"><b>Total&nbsp;</b></td>
            <td align="right"><b><?= number_format($hsn_grand, 2, '.', '') ?></b></td>
        </tr>
    </table>

    <!-- TAX AMOUNT IN WORDS + BANK DETAILS -->
    <table width="100%">
        <tr>
            <td width="50%">
                <?php if ($totalgstamount > 0): ?>
                    <div>&nbsp;Tax Amount (in words): <b>INR <?= ucwords(trim($taxWords)) ?> Only</b></div>
                <?php else: ?>
                    <div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
                <?php endif; ?>
                <br>
                <div style="width:99%;margin:0 auto;">
                    <u>Declaration:</u><br>
                    We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.
                </div>
            </td>
            <td>
                <table align="right">
                    <tr><td>A/c Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['acname'] ?? '', ENT_QUOTES) ?></td></tr>
                    <tr><td>A/c Number</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['acnumber'] ?? '', ENT_QUOTES) ?></td></tr>
                    <tr><td>Bank Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['bankname'] ?? '', ENT_QUOTES) ?></td></tr>
                    <tr><td>Branch Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['branchname'] ?? '', ENT_QUOTES) ?></td></tr>
                    <tr><td>IFS Code</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['ifsc'] ?? '', ENT_QUOTES) ?></td></tr>
                    <tr><td>UPI Number</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['upinumber'] ?? '', ENT_QUOTES) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table width="100%" id="sealsign">
        <tr>
            <td width="50%" align="left">Customer's Seal and Signature</td>
            <td align="right">for <b><?= htmlspecialchars($result_Godown['gname'], ENT_QUOTES) ?></b></td>
        </tr>
        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr>
            <td></td>
            <td align="right">Authorised Signatory</td>
        </tr>
    </table>

    <div style="clear:both;"></div>
</div><!-- maincontainar -->

<div align="center">This is a Computer Generated Invoice</div>

            </div><!-- divToPrint -->

        </div><!-- app-content -->
    </div><!-- app-container -->
</div><!-- app -->

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