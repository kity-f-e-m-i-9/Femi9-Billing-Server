<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid = base64_decode($_REQUEST['returnid'] ?? '');
$returnid = mysqli_real_escape_string($db_conn, $returnid);

$ret = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_return_stock WHERE returnid='$returnid'"));
$invid = $ret['invnumber'];
$user  = $ret['from_usertype'];

$inv_table = ($user === 'customer') ? 'invoice' : 'user_invoice';
$inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $inv_table WHERE inv_id='$invid'"));

$displaytitle = "Stock Return (Credit Note) Details";
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

<a href="manage-return.php" id="linkbackvl">&#8630;&nbsp;Go Back</a>

<h1><table class="headertble"><tr><td>
    <?php echo $displaytitle; ?><br/>
    <div style="font-size:15px;margin-top:10px;">Invoice Number:-</div>
    <div style="font-size:22px;font-weight:600;color:blue;"><?php echo $inv['inv_number'] ?? '---'; ?></div>
    <div style="font-size:12px;margin-top:5px;"><?php echo $user; ?></div>
</td><td>&nbsp;</td></tr></table></h1>

<?php if (isset($_REQUEST['DeleteSuccess'])) { ?><div class="alert alert-danger">Success! Deleted</div><?php } ?>

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
            <th></th>
        </tr>
    </thead>
    <tbody>
<?php
$TotalAMount123 = 0;
$rd = 0;
$items = mysqli_query($db_conn, "SELECT * FROM user_return_stock_items WHERE returnid='$returnid' ORDER BY id DESC");
$count_products_return = mysqli_num_rows($items);
while ($item = mysqli_fetch_array($items)) {
    $product = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM products WHERE id='{$item['prid']}'"));
    $TotalAMount123 += $item['total'];
    $ItemRowid  = base64_encode($item['id']);
    $enc_returnid = $_REQUEST['returnid'];
?>
    <tr>
        <th><?php echo ++$rd; ?></th>
        <td><?php echo $product['productName']; ?></td>
        <td><?php echo $item['qty']; ?></td>
        <td>&#8377;<?php echo inr_format($item['amount'], 2); ?></td>
        <td align="right"><?php echo inr_format($item['subtotal'], 2); ?></td>
        <td><?php echo inr_format($item['gstamount_total'], 2); ?> (<?php echo $item['gst_percentage']; ?>%)</td>
        <td align="right"><?php echo inr_format($item['total'], 2); ?></td>
        <td>
            <a href="cnote_delete.php?returnid=<?php echo $enc_returnid; ?>&rowid=<?php echo $ItemRowid; ?>&redirurl=cnote_details"
               onclick="return confirm('Delete this item?');">
                <span class="badge bg-danger">Remove</span>
            </a>
        </td>
    </tr>
<?php } ?>
    </tbody>
</table>
</div>
</div>

<div class="card-footer">
<div class="row invoice-summary">
    <?php if ($count_products_return == 0) { ?>
    <div class="col-lg-12" style="text-align:center;">
        <a href="cnote_del.php?returnid=<?php echo $_REQUEST['returnid']; ?>"
           class="btn btn-primary badge badge-style-bordered badge-danger"
           onclick="return confirm('Delete this credit note?');">Delete Credit Note</a>
    </div>
    <?php } ?>

    <div class="col-lg-5"></div>

    <script>
    function totalamount() {
        var s = document.getElementById('subtotal').value;
        var d = document.getElementById('discount').value;
        document.getElementById('outputTotalamount').value = (s*1) - (d*1);
    }
    </script>

    <?php if ($ret['status'] == 'pending' && $count_products_return > 0) { ?>
    <div class="col-lg-3">
        <div class="invoice-info">
            <form action="cnote_finish.php" method="post" enctype="multipart/form-data"
                  onsubmit="return confirm('Complete this return?');">
                <input type="hidden" name="returnid" value="<?php echo $_REQUEST['returnid']; ?>">
                <input type="hidden" name="SubTotal" value="<?php echo $TotalAMount123; ?>">

                <p class="bold">Subtotal
                    <span><input type="number" min="0" value="<?php echo $TotalAMount123; ?>" id="subtotal" disabled></span>
                </p><br/>
                <p class="bold">Discount
                    <span>
                        <input type="number" onkeyup="totalamount()" id="discount"
                               value="<?php echo $ret['discount'] ?: 0; ?>" min="0" name="discount" required>
                    </span>
                </p><br/>
                <p class="bold">Total
                    <span>
                        <?php $total_display = $TotalAMount123 - ($ret['discount'] ?: 0); ?>
                        <input type="number" min="0" value="<?php echo $total_display; ?>"
                               id="outputTotalamount" disabled>
                    </span>
                </p>
                <div style="clear:both;"></div>
                <div class="invoice-info-actions">
                    <button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">
                        Complete Return
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php } ?>
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
