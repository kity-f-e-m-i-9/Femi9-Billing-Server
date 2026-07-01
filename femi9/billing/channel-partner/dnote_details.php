<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$displaytitle = "Debit Note Details";
$returnid = mysqli_real_escape_string($db_conn, base64_decode($_REQUEST['returnid'] ?? ''));

$ret = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_return_stock WHERE returnid='$returnid' LIMIT 1"));
$invid = $ret['invnumber'] ?? '';

// Debit note: TP sent goods back to supplier — invoice is always in user_invoice
$inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$invid' LIMIT 1"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $displaytitle; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <br/>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

<a href="debit-note.php" id="linkbackvl">&#8630;&nbsp;Go Back</a>

<h1><table class="headertble"><tr><td>
    <?php echo $displaytitle; ?><br/>
    <div style="font-size:15px;margin-top:10px;">Invoice Number:-</div>
    <div style="font-size:22px;font-weight:600;color:blue;"><?php echo htmlspecialchars($inv['inv_number'] ?? '---'); ?></div>
</td><td>&nbsp;</td></tr></table></h1>

<div class="card-footer">
<div class="row">
<div class="table-responsive">
<table class="table invoice-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Product Description</th>
            <th>Qty</th>
            <th>MRP</th>
            <th>Amount</th>
            <th>GST</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $rd = 0;
    $TotalAMount123 = 0;
    $items = mysqli_query($db_conn, "SELECT * FROM user_return_stock_items WHERE returnid='$returnid' ORDER BY id DESC");
    while ($item = mysqli_fetch_array($items)) {
        $product = mysqli_fetch_array(mysqli_query($db_conn, "SELECT productName FROM products WHERE id='{$item['prid']}' LIMIT 1"));
        $TotalAMount123 += $item['total'];
    ?>
    <tr>
        <th><?php echo ++$rd; ?></th>
        <td><?php echo htmlspecialchars($product['productName']); ?></td>
        <td><?php echo $item['qty']; ?></td>
        <td>&#8377;<?php echo number_format($item['amount'], 2, '.', ''); ?></td>
        <td align="right"><?php echo number_format($item['subtotal'], 2, '.', ''); ?></td>
        <td><?php echo number_format($item['gstamount_total'], 2, '.', ''); ?> (<?php echo $item['gst_percentage']; ?>%)</td>
        <td align="right"><?php echo number_format($item['total'], 2, '.', ''); ?></td>
    </tr>
    <?php } ?>
    </tbody>
</table>
</div>
</div>

<div class="card-footer">
<div class="row invoice-summary">
    <div class="col-lg-5"></div>
    <div class="col-lg-3">
        <div class="invoice-info">
            <p class="bold">Subtotal <span><input type="number" value="<?php echo $TotalAMount123; ?>" disabled></span></p><br/>
            <p class="bold">Discount <span><input type="number" value="<?php echo $ret['discount'] ?: 0; ?>" disabled></span></p><br/>
            <?php $total_display = $TotalAMount123 - ($ret['discount'] ?: 0); ?>
            <p class="bold">Total <span><input type="number" value="<?php echo $total_display; ?>" disabled></span></p>
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
