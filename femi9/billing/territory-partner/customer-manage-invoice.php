<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser       = "customer";
$displaytitle     = "Manage Invoice - Customer";
$lablenamedisplay = "Customer Name";
$tablename        = "customers";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $displaytitle; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
</head>
<body>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<?php if (isset($_SESSION['successMessage'])) {
    $sm = $_SESSION['successMessage']; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>Swal.fire({ icon:'success', title:'Success', text:'<?php echo $sm; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['successMessage']); } ?>

<?php if (isset($_SESSION['errorMessage'])) {
    $em = $_SESSION['errorMessage']; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>Swal.fire({ icon:'error', title:'Warning', text:'<?php echo $em; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['errorMessage']); } ?>

<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar"><?php include("logo.php"); ?><?php include("femi_menu.php"); ?></div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <?php if (isset($_REQUEST['updatedSuccess'])): ?><div class="alert alert-info">Changes saved successfully.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['deletedDone'])): ?><div class="alert alert-warning">Invoice deleted successfully.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['no_input_stock'])): ?><div class="alert alert-danger">Adding invoices is not available yet. Please contact the company to assign input stock to your account first.</div><?php endif; ?>
                                <h1><table class="headertble"><tr>
                                    <td><?php echo $displaytitle; ?></td>
                                    <td><a href="customer-invoice-add.php" title="Add Invoice">&#10011;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>

<?php
$num_rec_per_page = 30;
$page       = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$start_from = ($page - 1) * $num_rec_per_page;
$i          = $start_from;
?>
                    <div class="row"><div class="col"><div class="card"><div class="card-body">
                    <style>#overflowon{width:100%;overflow-x:scroll!important;height:100%;overflow-y:hidden;}</style>
                    <div id="overflowon">
                        <table id="datatable1" class="display" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Invoice Number</th>
                                    <th><?php echo $lablenamedisplay; ?></th>
                                    <th>Invoice Date</th>
                                    <th>Invoice Amount</th>
                                    <th>Print</th>
                                    <th>Edit</th>
                                    <th>Return (Credit&nbsp;Note)</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody>
<?php
$fetch_invoices = mysqli_query($db_conn,
    "SELECT * FROM invoice WHERE user_id='$Login_user_IDvl' AND user_type='$Login_user_TYPEvl' ORDER BY id DESC");
while ($result_inv = mysqli_fetch_array($fetch_invoices)) {
    $CuSTID = $result_inv['customer_id'];
    if ($CuSTID != 0) {
        $resCust = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $tablename WHERE id='$CuSTID' LIMIT 1"));
        $Cust_Name  = htmlspecialchars($resCust['name']   ?? '---');
        $Cust_Mbile = htmlspecialchars($resCust['mobile'] ?? '');
    } else {
        $Cust_Name  = "Walking Customer";
        $Cust_Mbile = '';
    }

    $INVID_encode = base64_encode($result_inv["inv_id"]);

    $totalamount         = (float)$result_inv["total"];
    $Total_Receipt_amount = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT SUM(received) FROM receipt WHERE inv_id='" . $result_inv["inv_id"] . "'"))[0]);
    if ($Total_Receipt_amount == 0) {
        $msgpayment = "<span class='badge badge-style-bordered badge-danger'>Not Paid</span>";
    } elseif ($Total_Receipt_amount > 0 && $totalamount == $Total_Receipt_amount) {
        $msgpayment = "<span class='badge badge-style-bordered badge-success'>Fully Paid</span>";
    } else {
        $msgpayment = "<span class='badge badge-style-bordered badge-warning'>Partially Paid</span>";
    }

    $dlres = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT * FROM delivery_note WHERE inv_id='" . $result_inv["inv_id"] . "' LIMIT 1"));
?>
                                <tr>
                                    <td><?php echo ++$i; ?></td>

                                    <!-- Invoice number → delivery note modal -->
                                    <td>
                                    <a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#dlModal<?php echo $result_inv["id"]; ?>">
                                    <?php echo htmlspecialchars($result_inv["inv_number"]); ?></a>

                                    <div class="modal fade" id="dlModal<?php echo $result_inv["id"]; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog"><div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Delivery Note<br/><?php echo htmlspecialchars($result_inv["inv_number"]); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post" onsubmit="return confirm('Confirm?');" action="dlnote_action.php">
                                        <input type="hidden" name="inv_id"     value="<?php echo $result_inv["inv_id"]; ?>">
                                        <input type="hidden" name="inv_number" value="<?php echo htmlspecialchars($result_inv["inv_number"]); ?>">
                                        <input type="hidden" name="inv_table"  value="customer">
                                        <div class="example-content" style="padding:20px;">
                                            <div class="form-floating mb-3"><input type="text"  name="dl_note"          class="form-control" value="<?php echo htmlspecialchars($dlres['dl_note'] ?? ''); ?>"><label>Delivery Note</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="mode_pmnt"        class="form-control" value="<?php echo htmlspecialchars($dlres['mode_pmnt'] ?? ''); ?>"><label>Mode/Terms of Payment</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="ref_no"           class="form-control" value="<?php echo htmlspecialchars($dlres['ref_no'] ?? ''); ?>"><label>Reference No.</label></div>
                                            <div class="form-floating mb-3"><input type="date"  name="ref_date"         class="form-control" value="<?php echo $dlres['ref_date'] ?? ''; ?>"><label>Reference Date</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="ot_ref"           class="form-control" value="<?php echo htmlspecialchars($dlres['ot_ref'] ?? ''); ?>"><label>Other References</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="order_no"         class="form-control" value="<?php echo htmlspecialchars($dlres['order_no'] ?? ''); ?>"><label>Buyer's Order No.</label></div>
                                            <div class="form-floating mb-3"><input type="date"  name="dated"            class="form-control" value="<?php echo $dlres['dated'] ?? ''; ?>"><label>Dated</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="dispatch_doc_no"  class="form-control" value="<?php echo htmlspecialchars($dlres['dispatch_doc_no'] ?? ''); ?>"><label>Dispatch Doc No.</label></div>
                                            <div class="form-floating mb-3"><input type="date"  name="dlnote_date"      class="form-control" value="<?php echo $dlres['dlnote_date'] ?? ''; ?>"><label>Delivery Note Date</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="dispatch_through" class="form-control" value="<?php echo htmlspecialchars($dlres['dispatch_through'] ?? ''); ?>"><label>Dispatched through</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="destination"      class="form-control" value="<?php echo htmlspecialchars($dlres['destination'] ?? ''); ?>"><label>Destination</label></div>
                                            <div class="form-floating mb-3"><input type="text"  name="terms"            class="form-control" value="<?php echo htmlspecialchars($dlres['terms'] ?? ''); ?>"><label>Terms of Delivery</label></div>
                                            <button type="submit" name="UpdateDlNote" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
                                        </div>
                                        </form>
                                    </div></div>
                                    </div>
                                    </td>

                                    <!-- Customer + Update badge -->
                                    <td><?php echo $Cust_Name; ?><br/>M:&nbsp;<?php echo $Cust_Mbile; ?>
                                    <?php
                                    $cnt_ret = mysqli_num_rows(mysqli_query($db_conn,
                                        "SELECT * FROM user_return_stock_items WHERE invnumber='" . $result_inv["inv_id"] . "'"));
                                    if ($cnt_ret == 0) {
                                        echo "<a href='update_customer3.php?invuser=$getinvuser&&InvoiceID=" . $result_inv["inv_id"] . "' style='text-decoration:none;'><span class='badge badge-style-bordered badge-primary'>Update</span></a>";
                                    } else {
                                        echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>";
                                    }
                                    ?>
                                    </td>

                                    <td><?php echo date("d/M/Y", strtotime($result_inv["date"])); ?></td>

                                    <td><?php echo inr_format($result_inv["total"], 2); ?>
                                    <br/><a href="add-receipt.php?invid=<?php echo urlencode($result_inv["inv_id"]); ?>&&invuser=<?php echo $getinvuser; ?>"><?php echo $msgpayment; ?></a>
                                    </td>

                                    <td style="white-space:nowrap;">
                                    <?php if ($result_inv["sub_total"] > 0): ?>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                    <a href="customer-invoice-print.php?invoiceid=<?php echo $INVID_encode; ?>" title="Print"><img src="../../assets/images/print32.png"/></a>
                                    <button type="button" title="Share to WhatsApp" style="background:none;border:none;padding:0;cursor:pointer;"
                                    data-id="<?php echo $INVID_encode; ?>"
                                    data-mobile="<?php echo $Cust_Mbile ?? ''; ?>"
                                    data-invoice="<?php echo htmlspecialchars($result_inv["inv_number"]); ?>"
                                    onclick="shareCustomerInvoiceDirect(this)"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="#25D366"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39c1.44.79 3.06 1.2 4.71 1.2h.01c5.46 0 9.9-4.45 9.9-9.9C21.91 6.45 17.5 2 12.04 2zm5.8 14.03c-.24.68-1.4 1.32-1.93 1.4-.5.08-1.13.11-1.82-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.79-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.42.75-2.12 1.02-2.41.27-.29.58-.36.78-.36.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.83 2 .9 2.15.07.15.12.32.02.51-.1.19-.15.31-.29.48-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.35 1.46.29.15.46.13.63-.08.17-.21.72-.84.92-1.13.19-.29.38-.24.64-.14.26.1 1.65.78 1.94.92.29.15.48.22.55.34.07.13.07.72-.17 1.4z"/></svg></button>
                                    </div>
                                    <?php else: ?>
                                    <span class="badge badge-style-bordered badge-danger">Incomplete</span>
                                    <?php endif; ?>
                                    </td>

                                    <td><a href="customer-invoice-add.php?invuser=<?php echo $getinvuser; ?>&&action=edit&&InvoiceID=<?php echo $INVID_encode; ?>" title="Edit"><img src="../../assets/images/edit-32.png"/></a></td>

                                    <td>
                                    <?php if ($result_inv["sub_total"] > 0): ?>
                                    <a href="cnote_new.php?invuser=<?php echo $getinvuser; ?>&&InvoiceID=<?php echo $INVID_encode; ?>"><span class="badge badge-warning">Return</span></a>
                                    <?php else: echo "---"; endif; ?>
                                    </td>

                                    <td>
                                    <?php if ($result_inv["sub_total"] == 0): ?>
                                    <a href="delinvoice.php?invtype=customer&&invuser=<?php echo $getinvuser; ?>&&invid=<?php echo $INVID_encode; ?>" onclick="return confirm('Delete this invoice?');" title="Delete"><img src="../../assets/images/delete-32.png"/></a>
                                    <?php else: echo "---"; endif; ?>
                                    </td>
                                </tr>
<?php } ?>
                            </tbody>
                        </table>
                    </div>
                    </div></div></div></div>
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
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
<script src="../../assets/js/whatsapp-invoice-share.js?v=2"></script>
<script>
// Shares a customer invoice straight to WhatsApp from this list — no detour
// through the print page. This click is a real user gesture, so the whole
// async chain below (fetch -> PDF -> alert -> WhatsApp) keeps that gesture's
// permission to open a window, same as the print page's own button.
function shareCustomerInvoiceDirect(btn) {
    var id             = btn.getAttribute('data-id');
    var mobile         = btn.getAttribute('data-mobile');
    var invoiceNumber  = btn.getAttribute('data-invoice');
    var originalHtml   = btn.innerHTML;

    btn.disabled  = true;
    btn.innerHTML = '&hellip;';

    var iframe = document.createElement('iframe');
    iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:900px;height:600px;border:0;';
    iframe.onload = function () {
        var idoc     = iframe.contentDocument;
        var printDiv = idoc && idoc.getElementById('divToPrint');

        if (!printDiv) {
            btn.disabled  = false;
            btn.innerHTML = originalHtml;
            iframe.remove();
            alert('Could not prepare the invoice. Please try again.');
            return;
        }

        btn.disabled  = false;
        btn.innerHTML = originalHtml;

        shareInvoiceToWhatsApp({
            elementId:     'divToPrint',
            doc:           idoc,
            mobile:        mobile,
            invoiceNumber: invoiceNumber,
            fileName:      'Invoice_' + invoiceNumber,
            businessName:  <?php echo json_encode($business_name ?? ''); ?>,
            button:        btn
        });

        setTimeout(function () { iframe.remove(); }, 15000);
    };
    iframe.src = 'customer-invoice-print.php?invoiceid=' + encodeURIComponent(id);
    document.body.appendChild(iframe);
}
</script>
</body>
</html>
