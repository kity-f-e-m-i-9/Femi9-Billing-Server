<?php
include("checksession.php");
include("config.php");
if (isset($_SESSION['reward_notification'])) {
    require_once 'include/invoice-reward-integration.php';
    displayRewardNotification($_SESSION['reward_notification']);
    unset($_SESSION['reward_notification']);
}
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/../shared/ShopInvoiceData.php';
require_once __DIR__ . '/../shared/ShopInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';

$Invoice_ID = base64_decode($_REQUEST['invoiceid'] ?? '');
$tp_id      = (int)$Login_user_IDvl;
$invData    = load_shop_invoice_data($db_conn, $Invoice_ID, $tp_id, $_REQUEST['crcode'] ?? '');
if (!$invData) {
    die('Invoice not found.');
}
extract($invData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar"><?php include("logo.php"); ?><?php include("femi_menu.php"); ?></div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">

<script type="text/javascript">
function PrintDiv() {
    // A popup window (the old approach) gets silently blocked or opened as a
    // tiny unstyled tab on most mobile browsers, and never picks up the
    // page's external stylesheets — only whatever's inside #divToPrint's own
    // <style> block — so mobile prints came out misaligned or didn't appear
    // at all. Printing the current page directly with an @media print rule
    // (below) that hides everything except #divToPrint works identically on
    // desktop and mobile and needs no popup.
    window.print();
}
</script>

<?php
// Signed link to the invoice's own PDF (see shop-invoice-pdf.php) —
// paper/margin/scale query params get appended by the PDF Options dialog
// below so the TP can control the downloaded/shared PDF's layout.
$__pdf_url = invoice_share_url('/femi9/billing/territory-partner/shop-invoice-pdf.php', 'shop', $_REQUEST['invoiceid'] ?? '');
$__wa_text = 'Invoice #' . ($inv['inv_number'] ?? '') . ' from ' . $seller_display_name . ': ';
$__pdf_filename = 'Invoice_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $inv['inv_number'] ?? 'invoice') . '.pdf';
?>
<div id="invoiceActionBar" class="d-flex flex-wrap justify-content-end">
<button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button>
<button type="button" onClick="openPdfOptions();" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">picture_as_pdf</i> PDF / Share</button>
<button type="button" onClick="javascript:window.location='shop-invoice-add.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-success m-b-xs m-r-xs">+ New Invoice</button>
<button type="button" onClick="javascript:window.location='shop-manage-invoice.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-primary m-b-xs m-r-xs">Manage Invoice</button>
</div>

<!-- PDF Options dialog — mirrors the browser Print dialog's own "More
     settings" (paper size / margins / scale) but actually controls the
     server-rendered PDF (shop-invoice-pdf.php), for both the Download and
     Share-to-WhatsApp actions below. -->
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

    // No wa.me link, ever — per explicit instruction, the shop/customer
    // must receive the actual PDF file, never a URL to tap. The PDF is
    // always downloaded to the device first; the native share sheet (where
    // supported) is then offered on top of that download so the TP can
    // pick WhatsApp directly, but the download itself never depends on it.
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
        // Try the native share sheet first (Android Chrome / iOS Safari) —
        // if the TP picks WhatsApp there, it attaches the real file
        // directly. Either way the PDF is already downloaded below, so
        // there's always a local copy to attach manually in WhatsApp
        // Desktop/Web if the share sheet isn't available or gets cancelled.
        if (navigator.share && navigator.canShare && navigator.canShare({files: [file]})) {
            navigator.share({files: [file], text: waText}).catch(function() {
                // User cancelled the share sheet, or it failed — the file
                // is downloaded regardless (below), nothing more to do.
            });
        } else {
            alert('PDF downloaded as ' + fileName + '. Open WhatsApp and attach it from your Downloads.');
        }
        downloadBlob(blob);
    }).catch(function() {
        alert('Could not generate the PDF. Please try again.');
    });
    $('#pdfOptionsModal').modal('hide');
}
</script>

<div style="clear:both;"></div>
<div id="currencySelectWrap" style="width:100%;margin-bottom:10px;text-align:right;">
<select name="currency_code" class="form-control" style="width:150px;max-width:100%;display:inline-block;" id="currencySelect">
<?php if ($result_currency223 == null): ?>
    <option value="" hidden>Currency</option>
<?php else: ?>
    <option hidden><?php echo ucwords($result_currency223['c_name']); ?> - <?php echo ucwords($result_currency223['currency_name']); ?></option>
<?php endif; ?>
    <option value="Default">Default</option>
    <?php
    $fetch_currency = mysqli_query($db_conn, "SELECT * FROM country WHERE currency_name!='' ORDER BY c_name ASC");
    while ($result_currency = mysqli_fetch_array($fetch_currency)) {
    ?>
    <option value="<?php echo base64_encode($result_currency['id']); ?>"><?php echo ucwords($result_currency['c_name']); ?> - <?php echo ucwords($result_currency['currency_name']); ?></option>
    <?php } ?>
</select>
</div>
<script>
document.getElementById("currencySelect").addEventListener("change", function() {
    let selectedValue = this.value;
    if (selectedValue) {
        window.location.href = "shop-invoice-print.php?invoiceid=<?php echo urlencode($_REQUEST['invoiceid'] ?? ''); ?>&crcode=" + selectedValue;
    }
});
</script>

<div style="clear:both;"></div>

<div id="divToPrint"><!--Print content start-->
<?php echo render_shop_invoice_html($invData); ?>
</div><!--divToPrint-->

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
