<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata"); error_reporting(0);
include("config.php");

if (isset($_SESSION['reward_notification'])) {
    require_once 'include/invoice-reward-integration.php';
    displayRewardNotification($_SESSION['reward_notification']);
    unset($_SESSION['reward_notification']);
}

require_once __DIR__ . '/../shared/SuperStockistShopUserInvoiceData.php';
require_once __DIR__ . '/../shared/SuperStockistShopUserInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';

$Invoice_ID = base64_decode($_REQUEST['invoiceid'] ?? '');
$ss_id      = (int)$Login_user_IDvl;
$invData    = load_superstockist_shop_user_invoice_data($db_conn, $Invoice_ID, $ss_id, $_REQUEST['crcode'] ?? '');
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
    // Switched from the old popup-window print (blocked or unstyled on
    // many mobile browsers) to printing the current page directly — the
    // @media print rule in SuperStockistShopUserInvoiceHtml.php hides
    // everything except #divToPrint.
    window.print();
}
</script>

<?php
$__pdf_url = invoice_share_url('/femi9/billing/super-stockist/shop-user-invoice-pdf.php', 'ssshop', $_REQUEST['invoiceid'] ?? '');
$__wa_text = 'Invoice #' . ($inv['inv_number'] ?? '') . ' from ' . ($result_UserProfiles['companyname'] ?? '') . ': ';
$__pdf_filename = 'Invoice_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $inv['inv_number'] ?? 'invoice') . '.pdf';
?>
<div id="invoiceActionBar" class="d-flex flex-wrap justify-content-end">
<button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button>
<button type="button" onClick="openPdfOptions();" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">picture_as_pdf</i> PDF / Share</button>
<button type="button" onClick="javascript:window.location='shop-user-invoice-add.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-success m-b-xs m-r-xs">+ New Invoice</button>
<button type="button" onClick="javascript:window.location='shop-user-manage-invoice.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-primary m-b-xs m-r-xs">Manage Invoice</button>
</div>

<!-- PDF Options dialog — same pattern as territory-partner/shop-invoice-print.php. -->
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
        window.location.href = "shop-user-invoice-print?invoiceid=<?php echo urlencode($_REQUEST['invoiceid'] ?? ''); ?>&crcode=" + selectedValue;
    }
});
</script>

<div style="clear:both;"></div>

<div id="divToPrint"><!--Print content start-->
<?php echo render_superstockist_shop_user_invoice_html($invData); ?>
</div><!--divToPrint-->

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
