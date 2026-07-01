<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
$PageTitle = "Purchased Bill Copy";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $PageTitle; ?> : <?php echo $business_name; ?></title>
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
                                <h1><table class="headertble"><tr><td><?php echo $PageTitle; ?></td></tr></table></h1>
                            </div>
                        </div>
                    </div>
<?php
$num_rec_per_page = 30;
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$start_from = ($page - 1) * $num_rec_per_page;
$i = $start_from;
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
                                                    <th>Inv Number</th>
                                                    <th>Date</th>
                                                    <th>Sub Total</th>
                                                    <th>Discount</th>
                                                    <th>Total</th>
                                                    <th>Print</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$tp_id_esc = mysqli_real_escape_string($db_conn, $Login_user_IDvl);
$select_bills = "SELECT * FROM tp_invoices WHERE territory_partner_id='$tp_id_esc' ORDER BY id DESC";
$fetch_bills = mysqli_query($db_conn, $select_bills);
while ($result_bill = mysqli_fetch_array($fetch_bills)) {
    $courier     = (float)($result_bill["courier_charges"] ?? 0);
    $discount    = (float)($result_bill["discount_amount"] ?? 0);
    $totalamount = (float)$result_bill["total_amount"];
    $subtotal    = round($totalamount - $courier, 2);
    $inv_db_id   = (int)$result_bill["id"];

    $res_receipt = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(amount),0) AS paid FROM tp_invoice_receipts WHERE tp_invoice_id='$inv_db_id'"
    ));
    $Total_Receipt = (float)$res_receipt['paid'];

    if ($Total_Receipt == 0) {
        $msgpayment = "<span class='badge badge-style-bordered badge-danger'>Not Paid</span>";
    } elseif ($totalamount > 0 && abs($totalamount - $Total_Receipt) < 0.01) {
        $msgpayment = "<span class='badge badge-style-bordered badge-success'>Fully Paid</span>";
    } else {
        $msgpayment = "<span class='badge badge-style-bordered badge-warning'>Partially Paid</span>";
    }
?>
                                                <tr valign="top">
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo htmlspecialchars($result_bill["invoice_number"]); ?></td>
                                                    <td><?php echo date("d/M/Y", strtotime($result_bill["invoice_date"])); ?></td>
                                                    <td><?php echo number_format($subtotal, 2, '.', ''); ?></td>
                                                    <td><?php echo number_format($discount, 2, '.', ''); ?></td>
                                                    <td><?php echo number_format($totalamount, 2, '.', ''); ?><br/><?php echo $msgpayment; ?></td>
                                                    <td><a href="../company/tp-invoice-print.php?id=<?php echo base64_encode($result_bill["id"]); ?>" target="_blank" title="Print"><img src="../../assets/images/print32.png"/></a></td>
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
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
