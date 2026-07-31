<?php include("checksession.php"); error_reporting(0);
require_once("include/PermissionCheck.php"); requirePermission('ms');?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Marketing Staff > Product Orders : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
	<link href="../../assets/css/vlstyle.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <style>
        /* Product qty cell — hover (desktop) / tap (mobile) reveals the
           per-product breakdown, instead of one column per product. */
        .kpi-card-wrap { position: relative; display:inline-block; }
        .kpi-card-clickable { cursor:pointer; font-weight:700; }
        .kpi-detail-panel {
            display: none;
            position: absolute; top: 100%; right: 0; left: auto; z-index: 30;
            width: max-content; min-width: 220px; max-width: 320px;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
            margin-top: 6px; padding: 8px 12px; max-height: 220px; overflow-y: auto;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .kpi-card-wrap:hover .kpi-detail-panel,
        .kpi-detail-panel.show { display: block; }
        .kpi-detail-panel::before {
            content: ''; position: absolute; top: -6px; right: 20px;
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

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
</head>
<body>
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
                        <?php if (isset($_SESSION['successMessage'])): ?>
                        <div class="row"><div class="col"><div class="alert alert-success"><?=htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']);?></div></div></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): ?>
                        <div class="row"><div class="col"><div class="alert alert-danger"><?=htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']);?></div></div></div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col">
                                <div class="page-description">

								<?php
								if($_REQUEST['frdate']!=NULL)
								{
						$from_date=$_REQUEST['frdate'];
						$to_date=$_REQUEST['todate'];
								}
								else
								{
						$to_date=date("Y-m-d");
						$from_date=date("Y-m-d", strtotime("-2 days", strtotime($to_date)));;	
								}
						
						$se_msid=$_REQUEST['se_msid'];
						//
						$select_msDetails12="select * from marketing_staff where id='$se_msid'";
$fetch_msDetails12=mysqli_query($db_conn,$select_msDetails12);
$result_msDetails12=mysqli_fetch_array($fetch_msDetails12);
						?>
						
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Marketing Staff > Product Orders</td>
									<td>
									<a href="ms_orders_pdf?frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&se_msid=<?=$se_msid;?>" title="Export" target="_blank"><img src="32-pdf.png"></a>
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
<div id="searchleftcont">
<label class="form-label">View</label>
<select id="viewModeSelect" class="form-control" onchange="onViewModeChange(this.value)">
    <option value="company" <?=($se_msid==NULL)?'selected':''?>>All DMs (Company)</option>
    <option value="dm" <?=($se_msid!=NULL)?'selected':''?>>Specific DM</option>
</select>
</div>
<div id="searchleftcont" class="ms-picker-wrap" style="<?=($se_msid==NULL)?'display:none;':''?>">
<label class="form-label">Marketing Staff</label>
<select name="se_msid" id="msPickerSelect" class="form-control">
<?php if($se_msid==NULL){?>
<option value="" hidden="">Select</option>
<?php }else{?>
<option value="<?=$se_msid;?>" hidden=""><?=strtoupper($result_msDetails12['ms_name']);?>, <?=$result_msDetails12['ms_mobile'];?></option>
<?php }?>
<?php $select_msDetailsOPT="select * from marketing_staff order by ms_name asc";
$fetch_msDetailsOPT=mysqli_query($db_conn,$select_msDetailsOPT);
while($result_msDetailsOPT=mysqli_fetch_array($fetch_msDetailsOPT))
{
?>
<option value="<?=$result_msDetailsOPT['id'];?>"><?=strtoupper($result_msDetailsOPT['ms_name']);?>, <?=$result_msDetailsOPT['ms_mobile'];?></option>
<?php }?>
</select>
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
<div id="searchbuttoncont">
<button type="button" onclick="Javascript:window.location='ms_prorders';" style="margin-left:10px;" class="btn btn-primary"><i class="material-icons"></i>Reset</button>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
							
						
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
                              <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Marketing Staff</th>
													<th>Shop Name</th>
													<th>Shop Contact Number</th>
													<th>Address</th>
													<th>Date</th>
													<th>Product Qty</th>
													<th>Invoice</th>
                                                </tr>
                                            </thead>

											<tbody>
<?php
// Only orders with no TP assigned — this report exists so the company can
// pick up DM orders that don't have anyone else already handling them
// (e.g. no TP covers that area yet). Orders already assigned to a TP show
// up in that TP's own field-order pipeline instead, not here.
//
// Everything below is batched once instead of the old per-row (and
// per-row-per-product) queries, which made this page very slow to load.
$whereExtra = " and tp_id IS NULL";
if ($from_date != NULL) { $whereExtra = " and order_date between '$from_date' and '$to_date'" . $whereExtra; }
if ($se_msid != NULL)   { $whereExtra .= " and ms_id='$se_msid'"; }

$allProducts = [];
$resAllProd = mysqli_query($db_conn, "SELECT id, productName FROM products ORDER BY id ASC");
while ($apr = mysqli_fetch_assoc($resAllProd)) { $allProducts[] = $apr; }

$orderIdsInRange = [];
$orderMeta = [];
$qtyMap = [];
$shopIds = [];
$msIds = [];
$resOrders = mysqli_query($db_conn, "SELECT * FROM ms_orders WHERE new_order='yes'$whereExtra ORDER BY order_date DESC, order_id DESC");
while ($orow = mysqli_fetch_assoc($resOrders)) {
    $oid = $orow['order_id'];
    if (!isset($orderMeta[$oid])) {
        $orderMeta[$oid] = $orow;
        $orderIdsInRange[] = $oid;
        if (!empty($orow['shop_id'])) { $shopIds[$orow['shop_id']] = true; }
        if (!empty($orow['ms_id']))   { $msIds[$orow['ms_id']] = true; }
    }
    $qtyMap[$oid][$orow['pr_id']] = (int)$orow['qty'];
}

$shopMeta = [];
if (!empty($shopIds)) {
    $shopIdList = implode(',', array_map('intval', array_keys($shopIds)));
    $resShops = mysqli_query($db_conn, "SELECT * FROM ms_shop WHERE id IN ($shopIdList)");
    while ($srow = mysqli_fetch_assoc($resShops)) { $shopMeta[$srow['id']] = $srow; }
}

$msMeta = [];
if (!empty($msIds)) {
    $msIdList = implode(',', array_map('intval', array_keys($msIds)));
    $resMs = mysqli_query($db_conn, "SELECT * FROM marketing_staff WHERE id IN ($msIdList)");
    while ($mrow = mysqli_fetch_assoc($resMs)) { $msMeta[$mrow['id']] = $mrow; }
}

// Has this order already been picked up and invoiced directly by the company?
$invoicedMap = [];
if (!empty($orderIdsInRange)) {
    $oidList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $orderIdsInRange)) . "'";
    $resInvoiced = mysqli_query($db_conn, "SELECT source_ms_order_id FROM user_invoice WHERE source_ms_order_id IN ($oidList)");
    while ($ir = mysqli_fetch_assoc($resInvoiced)) { $invoicedMap[$ir['source_ms_order_id']] = true; }
}

foreach ($orderIdsInRange as $orderid) {
$result_product_list = $orderMeta[$orderid];

$shop_id = $result_product_list['shop_id'];
$result_shopcatt = $shopMeta[$shop_id] ?? [];

$ms_id = $result_product_list['ms_id'];
$result_msDetails = $msMeta[$ms_id] ?? [];

$orderProducts = $qtyMap[$orderid] ?? [];
$orderTotalQty = array_sum($orderProducts);
?>

                                               <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=htmlspecialchars($result_msDetails['ms_name'] ?? '');?><br/>
					<?=htmlspecialchars($result_msDetails['ms_mobile'] ?? '');?>
					</td>
					<td><?=htmlspecialchars($result_shopcatt['name'] ?? '');?></td>
					<td><?=htmlspecialchars($result_shopcatt['mobile_number'] ?? '');?></td>
					<td><?=ucwords($result_shopcatt["address"] ?? '');?></td>
					<td><?=date("d/m/Y",strtotime($result_product_list["order_date"]));?></td>
					<td>
						<div class="kpi-card-wrap">
							<div class="kpi-card-clickable"><?=(int)$orderTotalQty;?> qty</div>
							<div class="kpi-detail-panel">
								<div class="kpi-detail-title">Products in this order</div>
								<?php if (empty($orderProducts)): ?>
								<div class="kpi-detail-empty">No products.</div>
								<?php else: foreach ($allProducts as $apr):
									$q = $orderProducts[$apr['id']] ?? 0;
									if ($q <= 0) { continue; }
								?>
								<div class="kpi-detail-row"><span><?=htmlspecialchars($apr['productName']);?></span><span class="kpi-detail-qty"><?=(int)$q;?></span></div>
								<?php endforeach; endif; ?>
							</div>
						</div>
					</td>
					<td>
						<?php if (isset($invoicedMap[$orderid])): ?>
						<a href="ms-order-invoice-add.php?order_id=<?=urlencode($orderid)?>" class="btn btn-sm btn-outline-secondary">View Invoice</a>
						<?php else: ?>
						<a href="ms-order-invoice-add.php?order_id=<?=urlencode($orderid)?>" class="btn btn-sm btn-primary"><i class="material-icons" style="font-size:14px;vertical-align:middle;">receipt_long</i> Invoice</a>
						<?php endif; ?>
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
    <script>
    function onViewModeChange(mode) {
        var wrap = document.querySelector('.ms-picker-wrap');
        var sel  = document.getElementById('msPickerSelect');
        if (mode === 'company') {
            if (wrap) { wrap.style.display = 'none'; }
            if (sel)  { sel.value = ''; }
        } else {
            if (wrap) { wrap.style.display = ''; }
        }
    }

    // Touch devices have no :hover, so tapping a product-qty cell toggles
    // its detail panel open/closed instead. Tapping elsewhere closes it.
    document.addEventListener('click', function(e) {
        var card = e.target.closest('.kpi-card-wrap .kpi-card-clickable');
        if (card) {
            var panel = card.parentElement.querySelector('.kpi-detail-panel');
            if (panel) {
                var isOpen = panel.classList.contains('show');
                document.querySelectorAll('.kpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
                if (!isOpen) { panel.classList.add('show'); }
            }
            e.stopPropagation();
            return;
        }
        document.querySelectorAll('.kpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
    });
    </script>
</body>

</html>