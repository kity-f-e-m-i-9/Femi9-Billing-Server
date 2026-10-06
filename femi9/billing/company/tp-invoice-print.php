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
                // Save-as-PDF from the browser's own print dialog uses the
                // page's <title> at print time as its suggested filename —
                // set to TPName_Invoice_InvoiceNumber instead of the
                // generic "TP Invoice : ..." page title. The TP name's
                // spaces are dropped entirely (joined, not underscored — a
                // two/three-word name shouldn't turn into a run of
                // underscores); the invoice number is kept as-is other than
                // swapping '/' for '-' since '/' can't appear in a filename.
                var printDocTitle = <?php echo json_encode(
                    preg_replace('/[^a-zA-Z0-9]+/', '', trim($result_Invoice_Details['tp_name'] ?? 'TP')) . '_Invoice_' .
                    str_replace(['/', '\\'], '-', trim($result_Invoice_Details['invoice_number'] ?? 'invoice'))
                ); ?>;
                var __originalDocTitle = document.title;
                function PrintDiv() {
                    // Switched from the old popup-window print (blocked or
                    // unstyled on many mobile browsers, see shop-invoice-
                    // print.php's own note) to printing the current page
                    // directly — the @media print rule in TpInvoiceHtml.php
                    // hides everything except #divToPrint. The title swap
                    // keeps the Save-as-PDF suggested filename working the
                    // same way the old popup's own <title> did.
                    document.title = printDocTitle;
                    window.print();
                    document.title = __originalDocTitle;
                }
                </script>

                <?php
                // Signed link to the invoice's own PDF (see tp-invoice-pdf.php)
                // — paper/margin/scale query params get appended by the PDF
                // Options dialog below. Same pattern as the shop-invoice
                // print page (see territory-partner/shop-invoice-print.php).
                $__pdf_url = invoice_share_url('/femi9/billing/company/tp-invoice-pdf.php', 'tp', $enc_id);
                $__wa_text = 'Invoice #' . ($result_Invoice_Details['invoice_number'] ?? '') . ' from ' . ($result_Godown['gname'] ?? '') . ': ';
                $__pdf_filename = preg_replace('/[^a-zA-Z0-9]+/', '', trim($result_Invoice_Details['tp_name'] ?? 'TP')) . '_Invoice_' . str_replace(['/', '\\'], '-', trim($result_Invoice_Details['invoice_number'] ?? 'invoice')) . '.pdf';
                ?>
                <style type="text/css">
                /* This page had no mobile-responsive CSS at all — the action
                   buttons used a <table align="right"> that doesn't wrap, and
                   the 11-column invoice table just got crushed/misaligned on
                   a phone screen instead of reflowing. Same fix already
                   applied to territory-partner/shop-invoice-print.php. */
                @media (max-width: 768px) {
                    #tpInvoiceActionBar { justify-content: center !important; }
                    #tpInvoiceActionBar .btn { flex: 1 1 auto; min-width: 45%; margin: 3px !important; }

                    #divToPrintScroll { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
                    #divToPrintScroll .maincontainar { width: max-content; min-width: 100%; }

                    /* Left at its natural desktop width instead of being
                       stacked full-width, so the on-screen mobile view
                       matches what Print actually produces — the same
                       #divToPrintScroll horizontal scroll above covers this
                       too. Per explicit user correction: stacking read as
                       one field falling below another instead of the
                       two-column layout everyone expects. */
                    .second_containar { min-width: 600px; }

                    #bottom_bank, #bottom_bank table, #sealsign { width: 100% !important; }
                    table[align="right"] { width: 100%; }
                }
                </style>

                <div id="tpInvoiceActionBar" class="d-flex flex-wrap justify-content-end">
                    <button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button>
                    <button type="button" onClick="openPdfOptions();" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">picture_as_pdf</i> PDF / Share</button>
                    <button type="button" onClick="javascript:window.location='add-tp-invoice';" class="btn btn-success m-b-xs m-r-xs">+ New TP Invoice</button>
                    <button type="button" onClick="javascript:window.location='manage-tp-invoices';" class="btn btn-primary m-b-xs m-r-xs">Manage TP Invoices</button>
                </div>

                <br/>
                <div style="clear:both;"></div>

                <!-- PDF Options dialog — same pattern as shop-invoice-print.php. -->
                <div class="modal fade" id="pdfOptionsModal" tabindex="-1" role="dialog" aria-hidden="true">
                  <div class="modal-dialog" role="document" style="max-width:420px;">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">PDF Options</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <div class="form-group">
                          <label for="pdfOptPaper">Paper size</label>
                          <select id="pdfOptPaper" class="form-control">
                            <option value="a4" selected>A4</option>
                            <option value="a5">A5</option>
                            <option value="letter">Letter</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label for="pdfOptMargin">Margins</label>
                          <select id="pdfOptMargin" class="form-control">
                            <option value="6" selected>Default</option>
                            <option value="0">None</option>
                            <option value="custom">Custom</option>
                          </select>
                        </div>
                        <div class="form-group" id="pdfOptMarginCustomWrap" style="display:none;">
                          <label for="pdfOptMarginCustom">Custom margin (mm)</label>
                          <input type="number" id="pdfOptMarginCustom" class="form-control" min="0" max="30" value="6">
                        </div>
                        <div class="form-group m-b-0">
                          <label for="pdfOptScale">Scale: <span id="pdfOptScaleVal">100%</span></label>
                          <input type="range" id="pdfOptScale" min="50" max="150" value="100" step="5" style="width:100%;">
                        </div>
                      </div>
                      <div class="modal-footer flex-wrap justify-content-between">
                        <button type="button" class="btn btn-dark" onclick="pdfOptionsDownload();" style="flex:1 1 auto;margin:3px;">Download PDF</button>
                        <button type="button" class="btn btn-success" onclick="pdfOptionsShareWhatsApp();" style="flex:1 1 auto;margin:3px;"><i class="material-icons" style="font-size:16px;vertical-align:middle;">share</i> Share to WhatsApp</button>
                      </div>
                    </div>
                  </div>
                </div>

                <script>
                function openPdfOptions() {
                    $('#pdfOptionsModal').modal('show');
                }
                document.getElementById('pdfOptMargin').addEventListener('change', function() {
                    document.getElementById('pdfOptMarginCustomWrap').style.display = (this.value === 'custom') ? '' : 'none';
                });
                document.getElementById('pdfOptScale').addEventListener('input', function() {
                    document.getElementById('pdfOptScaleVal').textContent = this.value + '%';
                });

                function pdfOptionsUrl() {
                    var paper     = document.getElementById('pdfOptPaper').value;
                    var marginSel = document.getElementById('pdfOptMargin').value;
                    var margin    = (marginSel === 'custom') ? (document.getElementById('pdfOptMarginCustom').value || 6) : marginSel;
                    var scale     = document.getElementById('pdfOptScale').value;
                    var base      = <?php echo json_encode($__pdf_url); ?>;
                    var sep       = (base.indexOf('?') === -1) ? '?' : '&';
                    return base + sep + 'paper=' + encodeURIComponent(paper) + '&margin=' + encodeURIComponent(margin) + '&scale=' + encodeURIComponent(scale);
                }

                function pdfOptionsDownload() {
                    window.location = pdfOptionsUrl();
                    $('#pdfOptionsModal').modal('hide');
                }

                function pdfOptionsShareWhatsApp() {
                    var url      = pdfOptionsUrl();
                    var waText   = <?php echo json_encode($__wa_text); ?>;
                    var fileName = <?php echo json_encode($__pdf_filename); ?>;

                    function downloadBlob(blob) {
                        var blobUrl = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = blobUrl;
                        a.download = fileName;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        setTimeout(function() { URL.revokeObjectURL(blobUrl); }, 10000);
                    }

                    fetch(url).then(function(res) {
                        if (!res.ok) { throw new Error('PDF fetch failed'); }
                        return res.blob();
                    }).then(function(blob) {
                        var file = new File([blob], fileName, {type: 'application/pdf'});
                        if (navigator.share && navigator.canShare && navigator.canShare({files: [file]})) {
                            navigator.share({files: [file], text: waText}).catch(function() {});
                        } else {
                            alert('PDF downloaded as ' + fileName + '. Open WhatsApp and attach it from your Downloads.');
                        }
                        downloadBlob(blob);
                    }).catch(function() {
                        alert('Could not generate the PDF. Please try again.');
                    });
                    $('#pdfOptionsModal').modal('hide');
                }

                // manage-tp-invoices.php's row-level WhatsApp icon links here
                // with a #pdfShare fragment instead of hitting a raw wa.me
                // link directly, so it opens straight into this same dialog
                // rather than just landing on the plain Print page. Deferred
                // to window 'load' since jQuery/bootstrap (needed by
                // openPdfOptions' modal('show')) are loaded at the bottom of
                // the page, after this script block runs.
                if (window.location.hash === '#pdfShare') {
                    window.addEventListener('load', openPdfOptions);
                }
                </script>

                <div id="divToPrint"><!--Print content start-->
                <div id="divToPrintScroll">
<?php echo render_tp_invoice_html($invData, $show_carton_cols); ?>
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
