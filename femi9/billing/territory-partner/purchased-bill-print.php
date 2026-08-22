<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");

require_once __DIR__ . '/../shared/PurchasedBillData.php';
require_once __DIR__ . '/../shared/PurchasedBillHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';

$enc_id = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: manage-purchase-orders.php"); exit; }

$tp_id   = (int)$Login_user_IDvl;
$billData = load_purchased_bill_data($db_conn, $inv_id, $tp_id);
if (!$billData) { header("Location: manage-purchase-orders.php"); exit; }
extract($billData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Invoice : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
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

<?php
// wa.me click-to-chat link carrying a link to this bill's own PDF (see
// purchased-bill-pdf.php) — opens the TP's own WhatsApp with a prefilled
// message already selected; the TP just taps Send. Unlike the shop-invoice
// share button, the TP viewing this page is the BUYER, not the seller — this
// page has no "customer's mobile number" to prefill (tp_mobile here is the
// TP's OWN number). So the link opens with no number pre-selected, letting
// the TP pick who to forward it to themselves (e.g. their own accountant).
$__pdf_url = invoice_share_url('/femi9/billing/territory-partner/purchased-bill-pdf.php', 'purchase', $enc_id);
$__wa_text = 'Purchase Bill #' . ($result_Invoice_Details['invoice_number'] ?? '') . ': ' . $__pdf_url;
$__wa_url  = 'https://wa.me/' . '?text=' . rawurlencode($__wa_text);
?>
                <table align="right">
                <tr>
                    <td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
                    <td><a href="<?php echo htmlspecialchars($__wa_url, ENT_QUOTES); ?>" target="_blank" rel="noopener" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">share</i> Share to WhatsApp</a></td>
                    <td><button type="button" onClick="javascript:window.location='manage-purchase-orders.php';" class="btn btn-primary m-b-xs m-r-xs">My Purchase Orders</button></td>
                </tr>
                </table>
                <br/>
                <div style="clear:both;"></div>

                <div id="divToPrint"><!--Print content start-->
<?php echo render_purchased_bill_html($billData); ?>
                </div><!--Print content end-->

            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
