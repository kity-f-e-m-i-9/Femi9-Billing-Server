<?php include("checksession.php");
include("config.php");
 error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Orders (Product Orders) : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">\
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    <style>
        .tp-status-box { display:flex; flex-direction:column; gap:5px; min-width:150px; padding:2px 0; }
        .tp-status-box .badge { font-size:11px; font-weight:600; padding:4px 9px; border-radius:6px; white-space:nowrap; align-self:flex-start; }
        .tp-status-box .tp-status-pending { font-size:11px; color:#6b7280; }
        .tp-status-box .tp-status-diff { font-size:11px; color:#6b7280; }
        .tp-status-box .tp-status-link a { font-size:11px; font-weight:600; text-decoration:none; }
        .tp-status-box .tp-status-link a:hover { text-decoration:underline; }

        .tp-status-split { display:flex; align-items:flex-start; min-width:220px; }
        .tp-status-col-left, .tp-status-col-right {
            display:flex; flex-direction:column; gap:5px; flex:1;
        }
        .tp-status-col-left { padding-right:12px; }
        .tp-status-col-right { padding-left:12px; border-left:1px solid #e1e0d9; }
    </style>
</head>

<body>


<?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>

    <div class="app align-content-stretch d-flex flex-wrap">
	
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
		
        <div class="app-container">
            
          <?php include("app-header.php");?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
								
								<?php
									if($_REQUEST['frdate']==NULL && $_REQUEST['todate']==NULL)
									{
										$from_date=date("Y-m-d");
										$to_date=date("Y-m-d");

									}else{
										$from_date=$_REQUEST['frdate'];
										$to_date=$_REQUEST['todate'];
									}

// ── Date-range summary cards ────────────────────────────────────────────────
// Get Order vs No Order counts (this DM's own submissions in range).
$getOrderCount = 0; $noOrderCount = 0;
$resGN = $db_conn->query(
    "SELECT new_order, COUNT(DISTINCT order_id) c FROM ms_orders
     WHERE ms_id='" . mysqli_real_escape_string($db_conn, $markeingSTFID) . "' AND order_date BETWEEN '$from_date' AND '$to_date'
     GROUP BY new_order"
);
while ($gr = mysqli_fetch_assoc($resGN)) {
    if ($gr['new_order'] === 'yes') { $getOrderCount = (int)$gr['c']; }
    else { $noOrderCount = (int)$gr['c']; }
}

// Product-wise qty needed across all "Get Order" visits in range (what the
// DM asked for, regardless of whether the TP has invoiced it yet).
$productNeeded = [];
$totalQtyNeeded = 0;
$resPQ = $db_conn->query(
    "SELECT mo.pr_id, p.productName, SUM(mo.qty) totalqty
     FROM ms_orders mo LEFT JOIN products p ON p.id=mo.pr_id
     WHERE mo.ms_id='" . mysqli_real_escape_string($db_conn, $markeingSTFID) . "' AND mo.new_order='yes'
           AND mo.order_date BETWEEN '$from_date' AND '$to_date'
     GROUP BY mo.pr_id ORDER BY totalqty DESC"
);
while ($pr = mysqli_fetch_assoc($resPQ)) {
    $productNeeded[] = ['name' => $pr['productName'] ?: '-', 'qty' => (int)$pr['totalqty']];
    $totalQtyNeeded += (int)$pr['totalqty'];
}

// Which of those visits the TP has actually turned into an invoice.
$invoiceIds = [];
$resInv = $db_conn->query(
    "SELECT DISTINCT t.invoiced_inv_id
     FROM ms_orders o JOIN tp_orders t ON t.order_id=o.order_id
     WHERE o.ms_id='" . mysqli_real_escape_string($db_conn, $markeingSTFID) . "' AND o.new_order='yes'
           AND o.order_date BETWEEN '$from_date' AND '$to_date'
           AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''"
);
while ($ir = mysqli_fetch_assoc($resInv)) { $invoiceIds[] = $ir['invoiced_inv_id']; }

$invoiceCount = count($invoiceIds);
$totalInvoiceValue = 0;
$fullyPaidCount = 0; $fullyPaidValue = 0;
$partiallyPaidCount = 0; $partiallyPaidReceived = 0; $partiallyPaidInvIds = [];
$voidedCount = 0; $voidedValue = 0; $voidedInvIds = [];

if (!empty($invoiceIds)) {
    $idList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $invoiceIds)) . "'";

    $resVal = $db_conn->query("SELECT inv_id, total, status FROM user_invoice WHERE inv_id IN ($idList)");
    $invTotals = []; $invStatus = [];
    while ($vr = mysqli_fetch_assoc($resVal)) {
        $invTotals[$vr['inv_id']] = (float)$vr['total'];
        $invStatus[$vr['inv_id']] = $vr['status'];
        $totalInvoiceValue += (float)$vr['total'];
    }

    $resRec = $db_conn->query("SELECT inv_id, SUM(received) received FROM receipt WHERE inv_id IN ($idList) GROUP BY inv_id");
    $received = [];
    while ($rr = mysqli_fetch_assoc($resRec)) { $received[$rr['inv_id']] = (float)$rr['received']; }

    foreach ($invTotals as $iid => $tot) {
        if (($invStatus[$iid] ?? '') === 'cancelled') {
            $voidedCount++; $voidedValue += $tot; $voidedInvIds[] = $iid;
            continue;
        }
        $rec = $received[$iid] ?? 0;
        if ($rec >= $tot && $tot > 0) {
            $fullyPaidCount++; $fullyPaidValue += $tot;
        } elseif ($rec > 0) {
            $partiallyPaidCount++; $partiallyPaidReceived += $rec; $partiallyPaidInvIds[] = $iid;
        }
    }
}

// Product-wise qty breakdown for a set of invoice ids — used by both the
// Partially Paid and Voided cards' tap-to-expand panels.
function getProductBreakdownForInvoices($db_conn, array $invIds): array {
    if (empty($invIds)) { return []; }
    $idList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $invIds)) . "'";
    $res = $db_conn->query(
        "SELECT uii.pr_id, p.productName, SUM(uii.qty) totalqty
         FROM user_invoice_items uii LEFT JOIN products p ON p.id=uii.pr_id
         WHERE uii.inv_id IN ($idList)
         GROUP BY uii.pr_id ORDER BY totalqty DESC"
    );
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = ['name' => $row['productName'] ?: '-', 'qty' => (int)$row['totalqty']];
    }
    return $out;
}

$partiallyPaidProducts = getProductBreakdownForInvoices($db_conn, $partiallyPaidInvIds);
$voidedProducts = getProductBreakdownForInvoices($db_conn, $voidedInvIds);
						?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Orders <font size="3">(Product Orders)</font></td>
									<td>
									<a href="manager_order_csv?frd=<?=$from_date;?>&&tod=<?=$to_date;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a>
									</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
<div id="searchbuttoncont">
<a href="manage_order_product.php" style="margin-left:10px;" class="btn btn-primary">Reset</a>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>

<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card-wrap">
            <div class="kpi-card kpi-card-clickable" style="--kpi-accent:#6b7280;--kpi-tint:#f0f1f2;">
                <i class="material-icons-outlined kpi-ico">receipt_long</i>
                <div class="kpi-t">Invoices Placed</div>
                <div class="kpi-v"><?php echo (int)$invoiceCount; ?></div>
                <div class="kpi-sub"><?php echo (int)$totalQtyNeeded; ?> products needed &middot; hover / tap for details</div>
            </div>
            <div class="kpi-detail-panel">
                <div class="kpi-detail-title">Products needed</div>
                <?php if (empty($productNeeded)): ?>
                    <div class="kpi-detail-empty">No products in this range.</div>
                <?php else: foreach ($productNeeded as $pn): ?>
                    <div class="kpi-detail-row"><span><?php echo htmlspecialchars($pn['name']); ?></span><span class="kpi-detail-qty"><?php echo (int)$pn['qty']; ?></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card" style="--kpi-accent:#2a78d6;--kpi-tint:#e8f0fc;">
            <i class="material-icons-outlined kpi-ico">payments</i>
            <div class="kpi-t">Total Invoice Value</div>
            <div class="kpi-v">&#8377;<?php echo inr_format($totalInvoiceValue, 2); ?></div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card" style="--kpi-accent:#0ca30c;--kpi-tint:#e6f7e6;">
            <i class="material-icons-outlined kpi-ico">check_circle</i>
            <div class="kpi-t">Fully Paid</div>
            <div class="kpi-v"><?php echo (int)$fullyPaidCount; ?></div>
            <div class="kpi-sub">&#8377;<?php echo inr_format($fullyPaidValue, 2); ?></div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card-wrap">
            <div class="kpi-card kpi-card-clickable" style="--kpi-accent:#eda100;--kpi-tint:#fdf2e0;">
                <i class="material-icons-outlined kpi-ico">hourglass_bottom</i>
                <div class="kpi-t">Partially Paid</div>
                <div class="kpi-v"><?php echo (int)$partiallyPaidCount; ?></div>
                <div class="kpi-sub">&#8377;<?php echo inr_format($partiallyPaidReceived, 2); ?> received &middot; hover / tap for details</div>
            </div>
            <div class="kpi-detail-panel">
                <div class="kpi-detail-title">Products in these invoices</div>
                <?php if (empty($partiallyPaidProducts)): ?>
                    <div class="kpi-detail-empty">No partially paid invoices in this range.</div>
                <?php else: foreach ($partiallyPaidProducts as $pp): ?>
                    <div class="kpi-detail-row"><span><?php echo htmlspecialchars($pp['name']); ?></span><span class="kpi-detail-qty"><?php echo (int)$pp['qty']; ?></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card-wrap">
            <div class="kpi-card kpi-card-clickable" style="--kpi-accent:#d03b3b;--kpi-tint:#fbe6e6;">
                <i class="material-icons-outlined kpi-ico">cancel</i>
                <div class="kpi-t">Voided</div>
                <div class="kpi-v"><?php echo (int)$voidedCount; ?></div>
                <div class="kpi-sub">&#8377;<?php echo inr_format($voidedValue, 2); ?> voided &middot; hover / tap for details</div>
            </div>
            <div class="kpi-detail-panel">
                <div class="kpi-detail-title">Products in voided invoices</div>
                <?php if (empty($voidedProducts)): ?>
                    <div class="kpi-detail-empty">No voided invoices in this range.</div>
                <?php else: foreach ($voidedProducts as $vp): ?>
                    <div class="kpi-detail-row"><span><?php echo htmlspecialchars($vp['name']); ?></span><span class="kpi-detail-qty"><?php echo (int)$vp['qty']; ?></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card" style="--kpi-accent:#4a3aa7;--kpi-tint:#ece9f9;">
            <i class="material-icons-outlined kpi-ico">assignment_turned_in</i>
            <div class="kpi-t">Get Order / No Order</div>
            <div class="kpi-v"><span style="color:#0ca30c;"><?php echo (int)$getOrderCount; ?></span> / <span style="color:#d03b3b;"><?php echo (int)$noOrderCount; ?></span></div>
        </div>
    </div>
</div>

<style>
    .kpi-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 16px 18px 16px 20px; position: relative; overflow: hidden;
        height: 100%; box-shadow: 0 1px 2px rgba(11,11,11,0.03);
    }
    .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: var(--kpi-accent, #667eea); }
    .kpi-card .kpi-ico {
        width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center;
        background: var(--kpi-tint, #eef1fd); color: var(--kpi-accent, #667eea); font-size:16px;
        position:absolute; right:14px; top:14px;
    }
    .kpi-card .kpi-t { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight:600; color: #6b7280; padding-right:38px; }
    .kpi-card .kpi-v { font-size: 22px; font-weight: 700; margin-top: 6px; color: #111827; }
    .kpi-card .kpi-sub { font-size: 11px; color: #6b7280; margin-top: 4px; }
    .kpi-card-clickable { cursor: pointer; }
    .kpi-card-clickable:hover { border-color: var(--kpi-accent, #667eea); }

    /* Desktop: hover on the card reveals the panel as a floating overlay
       (doesn't push other cards' layout). Mobile: no hover, so a tap toggles
       a `.show` class via JS instead (see script below). */
    .kpi-card-wrap { position: relative; }
    .kpi-detail-panel {
        display: none;
        position: absolute; top: 100%; left: 0; right: 0; z-index: 30;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        margin-top: 6px; padding: 8px 12px; max-height: 220px; overflow-y: auto;
        box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    }
    .kpi-card-wrap:hover .kpi-detail-panel,
    .kpi-detail-panel.show { display: block; }
    .kpi-detail-panel::before {
        content: ''; position: absolute; top: -6px; left: 20px;
        width: 11px; height: 11px; background: #fff; border-left: 1px solid #e5e7eb; border-top: 1px solid #e5e7eb;
        transform: rotate(45deg);
    }
    .kpi-detail-title {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        color: #9ca3af; padding-bottom: 6px; margin-bottom: 4px; border-bottom: 1px solid #f0f1f2;
    }
    .kpi-detail-row { display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:12.5px; padding:6px 0; border-bottom:1px solid #f6f6f5; color:#374151; }
    .kpi-detail-row:last-child { border-bottom:none; }
    .kpi-detail-qty {
        font-weight: 700; font-size: 11.5px; color: #374151; background: #f0f1f2;
        padding: 2px 9px; border-radius: 6px; min-width: 20px; text-align: center;
    }
    .kpi-detail-empty { font-size:12px; color:#9ca3af; padding:6px 0; }
</style>
<script>
// Touch devices have no :hover, so tapping a KPI card toggles its detail
// panel open/closed instead. Tapping elsewhere closes any open panel.
document.querySelectorAll('.kpi-card-wrap .kpi-card-clickable').forEach(function(card) {
    card.addEventListener('click', function(e) {
        var panel = card.parentElement.querySelector('.kpi-detail-panel');
        if (!panel) { return; }
        var isOpen = panel.classList.contains('show');
        document.querySelectorAll('.kpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
        if (!isOpen) { panel.classList.add('show'); }
        e.stopPropagation();
    });
});
document.addEventListener('click', function() {
    document.querySelectorAll('.kpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
});
</script>

<?php
//----Continuos Serial Number In Next Page.......................
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; }; 
 $start_from = ($page-1) * $num_rec_per_page; 
$i= $start_from;
//---------------------------------------------------------------
//echo ++$i; 
?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<div style="background:#fff;overflow:scroll;width:100%;">
                              <table id="datatable1" width="100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Shop Name</th>
													<th>Shop Contact Number</th>
													<th>Address</th>
													<th>Date</th>
													<th>Marketing Tool</th>
													
			   <?php $select_prdetails_header="select * from products order by id asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){?>
				<th><?=$result_prdetails_header['productName'];?></th>
				<?php }?>

													<th>Location</th>
													<th>TP Status</th>
													<th>Edit</th>
                                                </tr>
                                            </thead>
											
											<tbody>
<?php 


$select_product_list="select distinct order_id from ms_orders where ms_id='$markeingSTFID' and new_order='yes' and order_date between '$from_date' and '$to_date'";	

$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list12=mysqli_fetch_array($fetch_product_list))
{						

$orderid=$result_product_list12['order_id'];
$select_shopcatt12="select * from ms_orders where order_id='$orderid'";
$fetch_shopcatt12=mysqli_query($db_conn,$select_shopcatt12);
$result_product_list=mysqli_fetch_array($fetch_shopcatt12);										

					
//shop category
$shop_id=$result_product_list['shop_id'];
$select_shopcatt="select * from ms_shop where id='$shop_id'";
$fetch_shopcatt=mysqli_query($db_conn,$select_shopcatt);
$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);

// TP status — what (if anything) happened to this order on the TP side
// after it was assigned (bridged into tp_orders by order_action_get.php).
// Built as a list of lines, then joined into one tidy stacked box, instead
// of inline spans/<br/> tags strung together.
$tpStatusLines = [];
$tpRow = mysqli_fetch_assoc(mysqli_query($db_conn,
    "SELECT tp_id, invoiced_inv_id FROM tp_orders WHERE order_id='" . mysqli_real_escape_string($db_conn, $orderid) . "' LIMIT 1"));
if (!$tpRow) {
    $tpStatusLines[] = "<span class='tp-status-pending'>&mdash;</span>";
} elseif (empty($tpRow['invoiced_inv_id'])) {
    $tpStatusLines[] = "<span class='badge badge-style-bordered badge-warning'>Pending with TP</span>";
} else {
    $invIdEsc = mysqli_real_escape_string($db_conn, $tpRow['invoiced_inv_id']);
    $invRow = mysqli_fetch_assoc(mysqli_query($db_conn, "SELECT status, total FROM user_invoice WHERE inv_id='$invIdEsc' LIMIT 1"));
    $hasReceipt = mysqli_num_rows(mysqli_query($db_conn, "SELECT 1 FROM receipt WHERE inv_id='$invIdEsc' LIMIT 1")) > 0;

    // Same payment-status logic as the TP's own shop-manage-invoice.php
    // (Not Paid / Fully Paid / Partially Paid) so the DM sees exactly
    // what the TP sees.
    $totalReceived = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT SUM(received) FROM receipt WHERE inv_id='$invIdEsc'"))[0] ?? 0);
    $invTotal = (float)($invRow['total'] ?? 0);
    if ($totalReceived <= 0) {
        $paymentLine = "<span class='badge badge-style-bordered badge-danger'>Not Paid</span>";
    } elseif ($totalReceived >= $invTotal) {
        $paymentLine = "<span class='badge badge-style-bordered badge-success'>Fully Paid</span>";
    } else {
        $paymentLine = "<span class='badge badge-style-bordered badge-warning'>Partially Paid</span>"
            . "<div class='tp-status-pending'>&#8377;" . inr_format($totalReceived, 2) . " paid, &#8377;" . inr_format($invTotal - $totalReceived, 2) . " pending</div>";
    }

    $diffParts = [];
    $diffRes = mysqli_query($db_conn,
        "SELECT change_type, COUNT(*) c FROM shop_invoice_change_log WHERE inv_id='$invIdEsc' AND change_type IN ('added','removed','qty_changed') GROUP BY change_type");
    $diffCounts = ['added' => 0, 'removed' => 0, 'qty_changed' => 0];
    while ($dr = mysqli_fetch_assoc($diffRes)) { $diffCounts[$dr['change_type']] = (int)$dr['c']; }
    if ($diffCounts['added'] > 0)       { $diffParts[] = '+' . $diffCounts['added'] . ' added'; }
    if ($diffCounts['removed'] > 0)     { $diffParts[] = '-' . $diffCounts['removed'] . ' removed'; }
    if ($diffCounts['qty_changed'] > 0) { $diffParts[] = $diffCounts['qty_changed'] . ' qty changed'; }
    $diffLine = !empty($diffParts) ? "<div class='tp-status-diff'>" . htmlspecialchars(implode(', ', $diffParts)) . "</div>" : '';

    $viewDetailsLink = "<div class='tp-status-link'><a href='order-invoice-status.php?order_id=" . urlencode($orderid) . "' target='_blank'>View Details &rarr;</a></div>";

    if (($invRow['status'] ?? '') === 'cancelled') {
        $tpStatusLines[] = "<span class='badge badge-style-bordered badge-danger'>Voided</span>";
        if ($diffLine) { $tpStatusLines[] = $diffLine; }
        $tpStatusLines[] = $viewDetailsLink;
    } elseif ($hasReceipt) {
        // Left column: payment status. Right column: billed amount, View
        // Details, then what changed from the DM's original order.
        $rightCol = "<span class='badge badge-style-bordered badge-success'>Billed &#8377;" . inr_format($invRow['total'] ?? 0, 2) . "</span>"
            . $viewDetailsLink . $diffLine;
        $tpStatusLines[] = "<div class='tp-status-split'>"
            . "<div class='tp-status-col-left'>" . $paymentLine . "</div>"
            . "<div class='tp-status-col-right'>" . $rightCol . "</div>"
            . "</div>";
    } else {
        $tpStatusLines[] = "<span class='badge badge-style-bordered badge-primary'>Invoice in Progress</span>";
        if ($diffLine) { $tpStatusLines[] = $diffLine; }
        $tpStatusLines[] = $viewDetailsLink;
    }
}
$tpStatusHtml = "<div class='tp-status-box'>" . implode('', $tpStatusLines) . "</div>";
?>
                                            
                                               <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$result_shopcatt['name'];?></td>
					<td><?=$result_shopcatt['mobile_number'];?></td>
					<td><?=ucwords($result_shopcatt["address"]);?></td>
					
					<td><?=date("d/m/Y",strtotime($result_product_list["order_date"]));?></td>
					<td><?=$result_product_list["marketing_tool"];?></td>
					
					
					<!------------------------PRODUCT WISE SALES QTY------------------------------->
				<?php $select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){
					
					$prid_header=$result_prdetails_header['id'];
					
					//SALES QTY
					$select_SUM_QTY="select qty from ms_orders where order_id='".$result_product_list['order_id']."' and pr_id='$prid_header'";
					$fetch_SUM_QTY=mysqli_query($db_conn,$select_SUM_QTY);
					$result_SUM_QTY=mysqli_fetch_array($fetch_SUM_QTY);
					if($result_SUM_QTY['qty']!=NULL){ $showQty=$result_SUM_QTY['qty'];}else{$showQty="0";}
						
				?>
				<td><b><?=$showQty;?></b></td>
				<?php }?>
				<!-------------------------------------------------------------------->

					<td>
					<?php if($result_product_list["latitude"]!=NULL && $result_product_list["longitude"]!=NULL){?>
					<a href="https://www.google.com/maps?q=<?=$result_product_list["latitude"];?>,<?=$result_product_list["longitude"];?>" target="_blank" class="btn btn-sm btn-secondary">View Location</a>
					<?php }else{ echo "---"; }?>
					</td>

					<td><?=$tpStatusHtml;?></td>

			<td>
			<a href="edit_order_product.php?orderid=<?php echo $orderid;?>&&actionupdate" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Details">
			<img src="../../assets/images/edit-32.png"/></a>
			</td>
													
	
                                                </tr>
                                           
										<?php }?>
										
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

    <!-- Javascripts -->
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
	<script src="../../assets/plugins/lightbox/fslightbox.js"></script>
	<script src="../../assets/js/pages/lightbox.js"></script>
</body>

</html>