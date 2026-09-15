<?php
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);
include("config.php");
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/../shared/TransferInvoiceData.php';
require_once __DIR__ . '/../shared/TransferInvoiceHtml.php';

$transfer_id = (int)($_GET['id'] ?? 0);
if ($transfer_id <= 0) { header("Location: manage-pl-godown-transfers"); exit; }

$invData = load_transfer_invoice_data($db_conn, $transfer_id);
if (!$invData) { header("Location: manage-pl-godown-transfers"); exit; }

$show_carton_cols = $invData['has_carton_data'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Slip <?php echo htmlspecialchars($invData['result_Invoice_Details']['dn_number']); ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
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

            <script>
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

            <table align="right" style="margin:10px 20px;">
                <tr>
                    <td><button type="button" onclick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">
                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">print</i> Print
                    </button></td>
                    <td><button type="button" onclick="window.location='manage-pl-godown-transfers';" class="btn btn-primary m-b-xs m-r-xs">
                        ← All Transfers
                    </button></td>
                </tr>
            </table>
            <div style="clear:both;"></div>

            <div id="divToPrint">
            <div id="divToPrintScroll">
<?php echo render_transfer_invoice_html($invData, $show_carton_cols); ?>
            </div>
            </div>

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
