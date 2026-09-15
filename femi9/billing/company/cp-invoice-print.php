<?php include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('channel_partner'); date_default_timezone_set("Asia/Kolkata"); error_reporting(0); include("config.php");

require_once __DIR__ . '/../shared/CpInvoiceData.php';
require_once __DIR__ . '/../shared/CpInvoiceHtml.php';

$enc_id = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: cp-today-orders.php"); exit; }

$invData = load_cp_invoice_data($db_conn, $inv_id);
if (!$invData) { header("Location: cp-today-orders.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CP Invoice : <?php echo $business_name; ?></title>
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

                <div id="cpInvoiceActionBar" class="d-flex flex-wrap justify-content-end">
                    <button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button>
                    <button type="button" onClick="javascript:window.location='cp-today-orders.php';" class="btn btn-primary m-b-xs m-r-xs">Back to CP Purchase Orders</button>
                </div>

                <br/>
                <div style="clear:both;"></div>

                <div id="divToPrint">
                <div id="divToPrintScroll">
<?php echo render_cp_invoice_html($invData); ?>
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
