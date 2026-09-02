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

require_once __DIR__ . '/../shared/CustomerInvoiceData.php';
require_once __DIR__ . '/../shared/CustomerInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';

$Invoice_ID = base64_decode($_REQUEST['invoiceid'] ?? '');
$tp_id      = (int)$Login_user_IDvl;
$invData    = load_customer_invoice_data($db_conn, $Invoice_ID, $tp_id, $_REQUEST['crcode'] ?? '');
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
// wa.me click-to-chat link carrying a link to the invoice's own PDF (see
// customer-invoice-pdf.php) — opens WhatsApp's own contact/chat picker with
// a prefilled message already selected, so the TP picks who to send it to
// (no fixed number — the customer's on-file mobile number may not even be
// on WhatsApp, or the TP may want to send it to someone else entirely).
// No file attach step, no PDF library in the browser, works on every
// device including desktop, and doesn't depend on the Web Share API (which
// requires HTTPS and isn't reachable at all on this deployment's mobile
// browsers). The TP still has to tap Send themselves — WhatsApp doesn't
// allow a webpage to submit a chat message on the user's behalf.
$__pdf_url   = invoice_share_url('/femi9/billing/territory-partner/customer-invoice-pdf.php', 'customer', $_REQUEST['invoiceid'] ?? '');
$__wa_text   = 'Invoice #' . ($inv['inv_number'] ?? '') . ' from ' . $seller_display_name . ': ' . $__pdf_url;
$__wa_url    = 'https://wa.me/?text=' . rawurlencode($__wa_text);
?>
<div id="invoiceActionBar" class="d-flex flex-wrap justify-content-end">
<button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button>
<a href="<?php echo htmlspecialchars($__wa_url, ENT_QUOTES); ?>" target="_blank" rel="noopener" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">share</i> Share to WhatsApp</a>
<button type="button" onClick="javascript:window.location='customer-invoice-add.php';" class="btn btn-success m-b-xs m-r-xs">+ New Invoice</button>
<button type="button" onClick="javascript:window.location='customer-manage-invoice.php';" class="btn btn-primary m-b-xs m-r-xs">Manage Invoice</button>
</div>

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
        window.location.href = "customer-invoice-print.php?invoiceid=<?php echo urlencode($_REQUEST['invoiceid'] ?? ''); ?>&crcode=" + selectedValue;
    }
});
</script>

<div style="clear:both;"></div>

<div id="divToPrint"><!--Print content start-->
<?php echo render_customer_invoice_html($invData); ?>
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
