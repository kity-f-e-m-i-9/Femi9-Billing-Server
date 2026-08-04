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

    // Payment via advance deduction (net amount = total minus courier)
    $net_amount = round($totalamount - $courier, 2);
    $res_advance = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(deducted_amount),0) AS paid FROM tp_invoice_advance_log WHERE tp_invoice_id='$inv_db_id'"
    ));
    $Total_Receipt = (float)$res_advance['paid'];

    if ($Total_Receipt <= 0) {
        $msgpayment = "<span class='badge badge-style-bordered badge-danger'>Not Paid</span>";
    } elseif ($net_amount > 0 && ($Total_Receipt + 0.01) >= $net_amount) {
        $msgpayment = "<span class='badge badge-style-bordered badge-success'>Fully Paid</span>";
    } else {
        $msgpayment = "<span class='badge badge-style-bordered badge-warning'>Partially Paid</span>";
    }
?>
                                                <tr valign="top">
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo htmlspecialchars($result_bill["invoice_number"]); ?></td>
                                                    <td><?php echo date("d/M/Y", strtotime($result_bill["invoice_date"])); ?></td>
                                                    <td><?php echo inr_format($subtotal, 2); ?></td>
                                                    <td><?php echo inr_format($discount, 2); ?></td>
                                                    <td><?php echo inr_format($totalamount, 2); ?><br/><?php echo $msgpayment; ?></td>
                                                    <td style="white-space:nowrap;"><div style="display:flex;align-items:center;gap:8px;"><a href="purchased-bill-print.php?id=<?php echo base64_encode($result_bill["id"]); ?>" target="_blank" title="Print"><img src="../../assets/images/print32.png"/></a>
                                                    <a href="purchased-bill-print.php?id=<?php echo base64_encode($result_bill["id"]); ?>&whatsapp_share=1" target="_blank" title="Share to WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="#25D366"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39c1.44.79 3.06 1.2 4.71 1.2h.01c5.46 0 9.9-4.45 9.9-9.9C21.91 6.45 17.5 2 12.04 2zm5.8 14.03c-.24.68-1.4 1.32-1.93 1.4-.5.08-1.13.11-1.82-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.79-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.42.75-2.12 1.02-2.41.27-.29.58-.36.78-.36.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.83 2 .9 2.15.07.15.12.32.02.51-.1.19-.15.31-.29.48-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.35 1.46.29.15.46.13.63-.08.17-.21.72-.84.92-1.13.19-.29.38-.24.64-.14.26.1 1.65.78 1.94.92.29.15.48.22.55.34.07.13.07.72-.17 1.4z"/></svg></a></div></td>
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
