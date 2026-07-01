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

                                    <td><?php echo number_format($result_inv["total"], 2, '.', ''); ?>
                                    <br/><a href="add-receipt.php?invid=<?php echo urlencode($result_inv["inv_id"]); ?>&&invuser=<?php echo $getinvuser; ?>"><?php echo $msgpayment; ?></a>
                                    </td>

                                    <td>
                                    <?php if ($result_inv["sub_total"] > 0): ?>
                                    <a href="customer-invoice-print.php?invoiceid=<?php echo $INVID_encode; ?>" title="Print"><img src="../../assets/images/print32.png"/></a>
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
</body>
</html>
