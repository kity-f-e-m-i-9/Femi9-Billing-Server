<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser       = "shop";
$displaytitle     = "Manage Invoice - Shop";
$lablenamedisplay = "Shop Name";
$tablename        = "shop";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $displaytitle; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
</head>
<body>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<?php if (isset($_SESSION['successMessage'])) {
    $successMessage = $_SESSION['successMessage']; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>Swal.fire({ icon:'success', title:'Success', text:'<?php echo $successMessage; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['successMessage']); } ?>

<?php if (isset($_SESSION['errorMessage'])) {
    $errorMessage = $_SESSION['errorMessage']; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>Swal.fire({ icon:'error', title:'Warning', text:'<?php echo $errorMessage; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['errorMessage']); } ?>

<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <?php if (isset($_REQUEST['updatedSuccess'])): ?><div class="alert alert-info">Changes saved success.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['deletedDone'])): ?><div class="alert alert-warning">Deleted ! one Invoice details deleted success.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['no_input_stock'])): ?><div class="alert alert-danger">Adding invoices is not available yet. Please contact the company to assign input stock to your account first.</div><?php endif; ?>
                                <h1><table class="headertble"><tr>
                                    <td><?php echo $displaytitle; ?></td>
                                    <td><a href="shop-invoice-add.php" title="Add Invoice">&#10011;</a></td>
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
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                <style>#overflowon{width:100%;overflow-x:scroll !important;height:100%;overflow-y:hidden;}</style>
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
    "SELECT * FROM user_invoice WHERE from_user_id='$Login_user_IDvl' AND from_user_type='$Login_user_TYPEvl' AND to_user_type='$getinvuser' ORDER BY id DESC");
while ($result_product_list = mysqli_fetch_array($fetch_invoices)) {
    $CuSTID       = $result_product_list['to_user_id'];
    $result_cust  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $tablename WHERE temp_id='$CuSTID'"));
    $Cust_Name    = $result_cust['name']          ?? '---';
    $Cust_Mbile   = $result_cust['mobile_number'] ?? '';
    $RowID_encode = base64_encode($result_product_list["id"]);
    $INVID_encode = base64_encode($result_product_list["inv_id"]);

    // Payment status
    $totalamount         = $result_product_list["total"];
    $Total_Receipt_amount = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT SUM(received) FROM receipt WHERE inv_id='" . $result_product_list["inv_id"] . "'"))[0];
    if ($Total_Receipt_amount == 0) {
        $msgpayment = "<span class='badge badge-style-bordered badge-danger'>Not Paid</span>";
    } elseif ($Total_Receipt_amount > 0 && $totalamount == $Total_Receipt_amount) {
        $msgpayment = "<span class='badge badge-style-bordered badge-success'>Fully Paid</span>";
    } else {
        $msgpayment = "<span class='badge badge-style-bordered badge-warning'>partially Paid</span>";
    }

    // Delivery note data
    $Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT * FROM delivery_note WHERE inv_id='" . $result_product_list["inv_id"] . "'"));

    // order-to-invoice.php stamps inv_number = inv_id as a placeholder until
    // the TP types a real number on shop-invoice-add.php's Submit Invoice
    // form. Never show that internal placeholder here as if it were the
    // invoice number — it would look auto-generated to the TP.
    $isPendingInvNumber = ($result_product_list["inv_number"] === $result_product_list["inv_id"]);
    $invNumberDisplay   = $isPendingInvNumber ? "Pending" : htmlspecialchars($result_product_list["inv_number"]);
?>
                                            <tr>
                                                <td><?php echo ++$i; ?></td>

                                                <!-- Invoice number → opens delivery note modal -->
                                                <td>
                                                <?php if ($isPendingInvNumber) { ?>
                                                <span class="badge badge-style-bordered badge-warning">Pending</span>
                                                <?php } else { ?>
                                                <a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#dlModal<?php echo $result_product_list["id"]; ?>">
                                                <?php echo $invNumberDisplay; ?></a>
                                                <?php } ?>
                                                </td>

                                                <!-- Delivery note modal -->
                                                <div class="modal fade" id="dlModal<?php echo $result_product_list["id"]; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog"><div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Delivery Note<br/><?php echo $invNumberDisplay; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="dlnote_action.php">
                                                    <input type="hidden" name="inv_id"     value="<?php echo $result_product_list["inv_id"]; ?>">
                                                    <input type="hidden" name="inv_number" value="<?php echo $invNumberDisplay; ?>">
                                                    <input type="hidden" name="inv_table"  value="shop">
                                                    <div class="example-content" style="padding:20px;">
                                                        <div class="form-floating mb-3"><input type="text"  name="dl_note"          class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['dl_note'] ?? ''); ?>"><label>Delivery Note</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="mode_pmnt"        class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['mode_pmnt'] ?? ''); ?>"><label>Mode/Terms of Payment</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="ref_no"           class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['ref_no'] ?? ''); ?>"><label>Reference No.</label></div>
                                                        <div class="form-floating mb-3"><input type="date"  name="ref_date"         class="form-control" value="<?php echo $Result_DLDetails['ref_date'] ?? ''; ?>"><label>Reference Date</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="ot_ref"           class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['ot_ref'] ?? ''); ?>"><label>Other References</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="order_no"         class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['order_no'] ?? ''); ?>"><label>Buyer's Order No.</label></div>
                                                        <div class="form-floating mb-3"><input type="date"  name="dated"            class="form-control" value="<?php echo $Result_DLDetails['dated'] ?? ''; ?>"><label>Dated</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="dispatch_doc_no"  class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['dispatch_doc_no'] ?? ''); ?>"><label>Dispatch Doc No.</label></div>
                                                        <div class="form-floating mb-3"><input type="date"  name="dlnote_date"      class="form-control" value="<?php echo $Result_DLDetails['dlnote_date'] ?? ''; ?>"><label>Delivery Note Date</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="dispatch_through" class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['dispatch_through'] ?? ''); ?>"><label>Dispatched through</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="destination"      class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['destination'] ?? ''); ?>"><label>Destination</label></div>
                                                        <div class="form-floating mb-3"><input type="text"  name="terms"            class="form-control" value="<?php echo htmlspecialchars($Result_DLDetails['terms'] ?? ''); ?>"><label>Terms of Delivery</label></div>
                                                        <button type="submit" name="UpdateDlNote" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
                                                    </div>
                                                    </form>
                                                </div></div>
                                                </div>

                                                <!-- Customer name + Update badge -->
                                                <td><?php echo htmlspecialchars($Cust_Name); ?><br/>M:&nbsp;<?php echo htmlspecialchars($Cust_Mbile); ?>
                                                <?php
                                                $cnt_ret = mysqli_num_rows(mysqli_query($db_conn,
                                                    "SELECT * FROM user_return_stock_items WHERE invnumber='" . $result_product_list["inv_id"] . "'"));
                                                if ($cnt_ret == 0) {
                                                    echo "<a href='update_customer2.php?invuser=$getinvuser&&InvoiceID=" . $result_product_list["inv_id"] . "' style='text-decoration:none;'><span class='badge badge-style-bordered badge-primary'>Update</span></a>";
                                                } else {
                                                    echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>";
                                                }
                                                ?>
                                                </td>

                                                <td><?php echo date("d/M/Y", strtotime($result_product_list["date"])); ?></td>

                                                <td><?php echo inr_format($result_product_list["total"], 2); ?>
                                                <br/><a href="add-receipt.php?invid=<?php echo $result_product_list["inv_id"]; ?>&&invuser=<?php echo $getinvuser; ?>"><?php echo $msgpayment; ?></a>
                                                </td>

                                                <td>
                                                <?php if ($result_product_list["sub_total"] > 0) { ?>
                                                <a href="shop-invoice-print.php?invoiceid=<?php echo $INVID_encode; ?>" title="Print"><img src="../../assets/images/print32.png"/></a>
                                                <?php } else { ?>
                                                <span class='badge badge-style-bordered badge-danger'>Incomplete</span>
                                                <?php } ?>
                                                </td>

                                                <td>
                                                <a href="shop-invoice-add.php?invuser=<?php echo $getinvuser; ?>&&action=edit&&InvoiceID=<?php echo $INVID_encode; ?>" title="Edit"><img src="../../assets/images/edit-32.png"/></a>
                                                </td>

                                                <td>
                                                <?php if ($result_product_list["sub_total"] > 0) { ?>
                                                <a href="cnote_new.php?invuser=<?php echo $getinvuser; ?>&&InvoiceID=<?php echo $INVID_encode; ?>"><span class="badge badge-warning">Return</span></a>
                                                <?php } else { echo "---"; } ?>
                                                </td>

                                                <td>
                                                <?php if ($result_product_list["sub_total"] == 0) { ?>
                                                <a href="delinvoice.php?invtype=shop&&invuser=<?php echo $getinvuser; ?>&&invid=<?php echo $INVID_encode; ?>" onclick="return confirm('You want to delete confirm?');" title="Delete"><img src="../../assets/images/delete-32.png"/></a>
                                                <?php } else { echo "---"; } ?>
                                                </td>

                                            </tr>
<?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
