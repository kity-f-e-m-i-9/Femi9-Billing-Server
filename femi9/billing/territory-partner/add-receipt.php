<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$invid      = $_REQUEST['invid']   ?? '';
$getinvuser = $_REQUEST['invuser'] ?? 'shop';
$backlink   = ($getinvuser === 'shop') ? 'shop-manage-invoice.php' : 'customer-manage-invoice.php';

// Fetch invoice (shop invoices use user_invoice; customer invoices use invoice)
if ($getinvuser === 'customer') {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='$invid' LIMIT 1"));
    if (!$inv) { header("Location: $backlink"); exit; }
    $invoice_total   = (float)$inv['total'];
    $courier_charges = (float)($inv['courier_charges'] ?? 0);
    $cust = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM customers WHERE id='" . ($inv['customer_id'] ?? 0) . "' LIMIT 1"));
    $cust_name   = $cust['name']   ?? 'Walking Customer';
    $cust_mobile = $cust['mobile'] ?? '';
} else {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$invid' LIMIT 1"));
    if (!$inv) { header("Location: $backlink"); exit; }
    $invoice_total   = (float)$inv['total'];
    $courier_charges = (float)($inv['courier_charges'] ?? 0);
    $cust = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM shop WHERE temp_id='" . ($inv['to_user_id'] ?? '') . "' LIMIT 1"));
    $cust_name   = $cust['name']          ?? '';
    $cust_mobile = $cust['mobile_number'] ?? '';
}

// Existing receipts
$total_received = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(received),0) AS s FROM receipt WHERE inv_id='$invid'"))['s']);
$balance_due = max(0, $invoice_total - $total_received);

// DELETE receipt
if (isset($_REQUEST['delreceiptact'])) {
    $rcptid = (int)base64_decode($_REQUEST['rcptid'] ?? '');
    mysqli_query($db_conn, "DELETE FROM receipt WHERE id='$rcptid'");
    echo "<script>window.location='add-receipt.php?invid=" . urlencode($invid) . "&invuser=$getinvuser&DeletedSuccess';</script>";
    exit;
}

// ADD receipt
if (isset($_POST['addreceipt'])) {
    $received      = (float)($_POST['receivedamount']  ?? 0);
    $receivable    = (float)($_POST['receivableamount'] ?? 0);
    $receipt_method  = mysqli_real_escape_string($db_conn, $_POST['receipt_method']  ?? '');
    $receipt_remarks = mysqli_real_escape_string($db_conn, $_POST['receipt_remarks'] ?? '');
    $receipt_date  = date("Y-m-d");

    function geraHashReceipt($qtd) {
        $chars = '123456789ABCDEFGHJKLMNPQRS';
        $len = strlen($chars) - 1;
        $h = '';
        for ($x = 0; $x < $qtd; $x++) { $h .= $chars[rand(0, $len)]; }
        return $h;
    }
    $receiptid = geraHashReceipt(10) . '/RCPT/' . date('dmygis');
    $balance   = $receivable - $received;

    // Determine from/to fields based on invoice type
    if ($getinvuser === 'customer') {
        $from_utype = $inv['user_type'] ?? $Login_user_TYPEvl;
        $from_uid   = $inv['user_id']   ?? $Login_user_IDvl;
        $to_utype   = 'customer';
        $to_uid     = $inv['customer_id'] ?? '';
    } else {
        $from_utype = $inv['from_user_type'] ?? $Login_user_TYPEvl;
        $from_uid   = $inv['from_user_id']   ?? $Login_user_IDvl;
        $to_utype   = $inv['to_user_type']   ?? 'shop';
        $to_uid     = $inv['to_user_id']     ?? '';
    }

    if ($received > 0 && $received <= $balance_due) {
        mysqli_query($db_conn, "INSERT INTO receipt
            (receiptid,inv_id,invoice_amount,received,receivable,date,from_user_type,from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks,payment_type)
            VALUES ('$receiptid','$invid','$invoice_total','$received','$balance','$receipt_date',
            '$from_utype','$from_uid','$to_utype','$to_uid',
            '$receipt_method','$receipt_remarks','regular')");
        echo "<script>window.location='add-receipt.php?invid=" . urlencode($invid) . "&invuser=$getinvuser&ReceiptAddedSuc';</script>";
    } else {
        echo "<script>window.location='add-receipt.php?invid=" . urlencode($invid) . "&invuser=$getinvuser&InvalidAmount';</script>";
    }
    exit;
}

// Refresh after POST
$total_received = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(received),0) AS s FROM receipt WHERE inv_id='$invid'"))['s']);
$balance_due = max(0, $invoice_total - $total_received);
$receipts    = mysqli_query($db_conn, "SELECT * FROM receipt WHERE inv_id='$invid' ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt : <?php echo $business_name; ?></title>
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
    <div class="app-sidebar"><?php include("logo.php"); ?><?php include("femi_menu.php"); ?></div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row"><div class="col"><div class="page-description">
                        <h1><table class="headertble"><tr>
                            <td>Receipt</td>
                            <td><a href="<?php echo $backlink; ?>" title="Go Back">&#9776;</a></td>
                        </tr></table></h1>
                    </div></div></div>

<?php if (isset($_REQUEST['ReceiptAddedSuc'])): ?><div class="alert alert-success">Receipt added successfully.</div><?php endif; ?>
<?php if (isset($_REQUEST['DeletedSuccess'])): ?><div class="alert alert-success">Receipt deleted.</div><?php endif; ?>
<?php if (isset($_REQUEST['InvalidAmount'])): ?><div class="alert alert-danger">Invalid amount. Must be &gt; 0 and &le; balance due.</div><?php endif; ?>

                    <div class="row"><div class="col-md-12"><div class="card"><div class="card-body">

                        <!-- Invoice details -->
                        <table class="table table-bordered" style="margin-bottom:20px;">
                            <thead><tr><th>Invoice No.</th><th><?php echo ($getinvuser === 'customer') ? 'Customer' : 'Shop'; ?></th><th>Date</th><th>Total</th><th>Received</th><th>Balance Due</th></tr></thead>
                            <tbody><tr>
                                <td><?php echo htmlspecialchars($inv['inv_number']); ?></td>
                                <td><?php echo htmlspecialchars($cust_name); ?><br/><small>M: <?php echo htmlspecialchars($cust_mobile); ?></small></td>
                                <td><?php echo date("d/M/Y", strtotime($inv['date'])); ?></td>
                                <td>&#8377;<?php echo number_format($invoice_total, 2); ?></td>
                                <td style="color:green;font-weight:bold;">&#8377;<?php echo number_format($total_received, 2); ?></td>
                                <td style="color:<?php echo $balance_due > 0 ? 'red' : 'green'; ?>;font-weight:bold;">&#8377;<?php echo number_format($balance_due, 2); ?></td>
                            </tr></tbody>
                        </table>

                        <!-- Receipt history -->
                        <?php if (mysqli_num_rows($receipts) > 0): ?>
                        <h5 style="margin-bottom:12px;">Receipt History</h5>
                        <table class="table table-bordered" style="margin-bottom:24px;">
                            <thead><tr><th>#</th><th>Date</th><th>Amount</th><th>Method</th><th>Remarks</th><th></th></tr></thead>
                            <tbody>
                            <?php $ri = 0; mysqli_data_seek($receipts, 0); while ($r = mysqli_fetch_array($receipts)) { ?>
                            <tr>
                                <td><?php echo ++$ri; ?></td>
                                <td><?php echo date("d/m/Y", strtotime($r['date'])); ?></td>
                                <td><b>&#8377;<?php echo number_format((float)$r['received'], 2); ?></b></td>
                                <td><?php echo htmlspecialchars($r['receipt_method']); ?></td>
                                <td><?php echo htmlspecialchars($r['receipt_remarks']); ?></td>
                                <td><?php if (($r['payment_type'] ?? '') !== 'credit_note'): ?><a href="add-receipt.php?invid=<?php echo urlencode($invid); ?>&invuser=<?php echo $getinvuser; ?>&delreceiptact=1&rcptid=<?php echo base64_encode($r['id']); ?>" onclick="return confirm('Delete this receipt?');" style="color:red;">Remove</a><?php else: ?><span style="color:#888;font-size:12px;">CN Credit</span><?php endif; ?></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <!-- Add receipt form -->
                        <?php if ($balance_due > 0): ?>
                        <h5 style="margin-bottom:12px;">Add Receipt</h5>
                        <form method="post" onsubmit="return confirm('Submit receipt?');">
                        <input type="hidden" name="invid"           value="<?php echo $invid; ?>">
                        <input type="hidden" name="invuser"         value="<?php echo $getinvuser; ?>">
                        <input type="hidden" name="receivableamount" id="receivableamount" value="<?php echo $balance_due; ?>">
                        <div class="example-container"><div class="example-content">

                        <label class="form-label">Balance Due</label>
                        <input type="number" class="form-control" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" disabled style="margin-bottom:12px;">

                        <label class="form-label">Received Amount*</label>
                        <input type="number" name="receivedamount" id="receivedamount" min="0.01" max="<?php echo $balance_due; ?>" step="0.01" required class="form-control" onkeyup="calcBalance()" style="margin-bottom:12px;">

                        <label class="form-label">Remaining After This</label>
                        <input type="number" id="remaining" class="form-control" readonly style="margin-bottom:12px;">

                        <label class="form-label">Payment Method*</label>
                        <select name="receipt_method" required class="form-control" style="margin-bottom:12px;">
                            <option value="" hidden>Select</option>
                            <option>Cash</option>
                            <option>UPI</option>
                            <option>Bank Transfer</option>
                            <option>Deposit</option>
                        </select>

                        <label class="form-label">Remarks*</label>
                        <textarea name="receipt_remarks" required class="form-control" style="margin-bottom:12px;"></textarea>

                        <button type="submit" name="addreceipt" class="btn btn-primary"><i class="material-icons">add</i>Add Receipt</button>
                        </div></div>
                        </form>
                        <script>
                        function calcBalance() {
                            var bal = <?php echo $balance_due; ?>;
                            var rec = parseFloat(document.getElementById('receivedamount').value) || 0;
                            var rem = Math.max(0, bal - rec);
                            document.getElementById('remaining').value = rem.toFixed(2);
                        }
                        </script>
                        <?php else: ?>
                        <div class="alert alert-success"><i class="material-icons">check_circle</i>&nbsp;Invoice fully paid.</div>
                        <?php endif; ?>

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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
