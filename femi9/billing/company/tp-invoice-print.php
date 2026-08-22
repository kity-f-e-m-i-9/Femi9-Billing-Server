<?php include("checksession.php"); require_once("include/GodownAccess.php"); date_default_timezone_set("Asia/Kolkata");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);
include("config.php");

require_once __DIR__ . '/../shared/TpInvoiceData.php';
require_once __DIR__ . '/../shared/TpInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';

$enc_id = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: manage-tp-invoices"); exit; }

$invData = load_tp_invoice_data($db_conn, $inv_id);
if (!$invData) { header("Location: manage-tp-invoices"); exit; }
extract($invData);

// The regular Print page always shows the carton columns when the invoice
// has carton data — only the WhatsApp-share PDF (tp-invoice-pdf.php) drops
// them, same behavior the old iframe/html2pdf flow had via its `?whatsapp=1`
// flag.
$show_carton_cols = $has_carton_data;
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

                <?php
                // wa.me click-to-chat link carrying a link to the invoice's own PDF
                // (see tp-invoice-pdf.php) — opens the company user's own WhatsApp
                // with the TP's number and a prefilled message already selected;
                // they just tap Send. Same Method-1 pattern as the shop-invoice
                // print page (see territory-partner/shop-invoice-print.php).
                $__wa_digits = preg_replace('/\D/', '', $result_Invoice_Details['tp_mobile'] ?? '');
                $__wa_number = strlen($__wa_digits) === 10 ? '91' . $__wa_digits : $__wa_digits;
                $__pdf_url   = invoice_share_url('/femi9/billing/company/tp-invoice-pdf.php', 'tp', $enc_id);
                $__wa_text   = 'Invoice #' . ($result_Invoice_Details['invoice_number'] ?? '') . ' from ' . ($result_Godown['gname'] ?? '') . ': ' . $__pdf_url;
                $__wa_url    = ($__wa_number ? 'https://wa.me/' . $__wa_number : 'https://wa.me/') . '?text=' . rawurlencode($__wa_text);
                ?>
                <table align="right">
                <tr>
                    <td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
                    <td><a href="<?php echo htmlspecialchars($__wa_url, ENT_QUOTES); ?>" target="_blank" rel="noopener" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">share</i> Share to WhatsApp</a></td>
                    <td><button type="button" onClick="javascript:window.location='add-tp-invoice';" class="btn btn-success m-b-xs m-r-xs">+ New TP Invoice</button></td>
                    <td><button type="button" onClick="javascript:window.location='manage-tp-invoices';" class="btn btn-primary m-b-xs m-r-xs">Manage TP Invoices</button></td>
                </tr>
                </table>

                <br/>
                <div style="clear:both;"></div>

                <div id="divToPrint"><!--Print content start-->
<?php echo render_tp_invoice_html($invData, $show_carton_cols); ?>
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
