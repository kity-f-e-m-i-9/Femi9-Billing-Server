<?php include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);

$title="Edit Order";
$manage_url="manage_order_product";
$manage_title="Manage Orders";
$message_title="Orders";

$orderid=$_REQUEST['orderid'];
$select_product_list="select * from ms_orders where order_id='$orderid'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
				
				$shop_id=$result_product_list['shop_id'];
$select_shopcatt="select * from ms_shop where id='$shop_id'";
$fetch_shopcatt=mysqli_query($db_conn,$select_shopcatt);
$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);

// ── Assign To TP — same firka/district-based matching as add_order.php's
// Get Order form, ported here so editing an order can see/change the TP
// assignment instead of only ever wiping it out. See OrderTpBridge.php for
// how a chosen tp_id gets mirrored into the TP's own tp_orders pipeline.
require_once("include/AssignedLocations.php");
require_once("include/OrderTpBridge.php");

$assignedDistricts = getMsAssignedDistricts($db_conn, (int)$markeingSTFID);

$allTalukIds = [];
foreach ($assignedDistricts as $d) {
    foreach ($d['taluks'] as $t) { $allTalukIds[] = $t['id']; }
}

$talukFirkaMap = [];
if (!empty($allTalukIds)) {
    $talukIdList = implode(',', array_map('intval', $allTalukIds));
    $resFirka = $db_conn->query(
        "SELECT id, name, parent_id FROM partner_location_nodes
         WHERE depth=6 AND parent_id IN ($talukIdList) AND is_active=1 ORDER BY name ASC"
    );
    while ($f = mysqli_fetch_assoc($resFirka)) {
        $talukFirkaMap[$f['parent_id']][] = ['id' => (int)$f['id'], 'name' => $f['name']];
    }
}

$firkaTpMap = [];
$allFirkaIds = [];
foreach ($talukFirkaMap as $list) { foreach ($list as $f) { $allFirkaIds[] = $f['id']; } }
if (!empty($allFirkaIds)) {
    $firkaIdList = implode(',', array_map('intval', $allFirkaIds));
    $resTpLoc = $db_conn->query(
        "SELECT tpl.location_id, tp.id AS tp_id, tp.name AS tp_name
         FROM territory_partner_locations tpl
         JOIN territory_partners tp ON tp.id=tpl.territory_partner_id
         WHERE tpl.location_id IN ($firkaIdList) AND tp.is_active=1"
    );
    while ($tr = mysqli_fetch_assoc($resTpLoc)) {
        $firkaTpMap[(int)$tr['location_id']] = ['id' => (int)$tr['tp_id'], 'name' => $tr['tp_name']];
    }
}

$dmHasActiveTp = !empty($firkaTpMap);

// (Shop options themselves are rendered further below from the page's own
// existing "select * from ms_shop where ms_id=..." loop, which already
// carries district_node_id/taluk_node_id/district_name — those get emitted
// as data-district-id/data-taluk-id/data-district attributes on each
// <option> for the JS cascade filter and TP district-text match.)

// Active TPs for the Assign To TP dropdown (filtered client-side by district text match).
$tpList = [];
$rTp = mysqli_query($db_conn, "SELECT id, name, branch_district FROM territory_partners WHERE is_active=1 ORDER BY name ASC");
while ($t = mysqli_fetch_assoc($rTp)) { $tpList[] = $t; }

// Whatever TP is currently assigned to this order — shown preselected so
// opening Edit doesn't present a blank field for an order that already has
// a TP. Prefer ms_orders.tp_id, but some older orders never got it backfilled
// even though their tp_orders row (the actual bridge the TP's own
// manage-orders.php reads from) does have one — fall back to that so the
// field doesn't wrongly show blank/unassigned for those.
$currentTpId = (int)($result_product_list['tp_id'] ?? 0);
if ($currentTpId <= 0) {
    $stmtBridgeTp = $db_conn->prepare("SELECT tp_id FROM tp_orders WHERE order_id=? AND tp_id IS NOT NULL ORDER BY id ASC LIMIT 1");
    $stmtBridgeTp->bind_param('s', $orderid);
    $stmtBridgeTp->execute();
    $bridgeTpRow = $stmtBridgeTp->get_result()->fetch_assoc();
    $stmtBridgeTp->close();
    if (!empty($bridgeTpRow['tp_id'])) { $currentTpId = (int)$bridgeTpRow['tp_id']; }
}
$currentTpName = '';
$currentTpInList = false;
if ($currentTpId > 0) {
    foreach ($tpList as $t) {
        if ((int)$t['id'] === $currentTpId) { $currentTpName = $t['name']; $currentTpInList = true; break; }
    }
    if (!$currentTpInList) {
        // TP may have since been deactivated — still surface its name rather
        // than silently dropping the assignment.
        $stmtCurTp = $db_conn->prepare("SELECT name FROM territory_partners WHERE id=? LIMIT 1");
        $stmtCurTp->bind_param('i', $currentTpId);
        $stmtCurTp->execute();
        $rowCurTp = $stmtCurTp->get_result()->fetch_assoc();
        $stmtCurTp->close();
        $currentTpName = $rowCurTp['name'] ?? ('TP #' . $currentTpId);
    }
}

if(isset($_REQUEST['update_no_order']))
{
	
	$update_orderid=$_POST["update_orderid"];

	//Delete old order details
    $delete_old_orders="delete from ms_orders where order_id='$update_orderid'";
	mysqli_query($db_conn,$delete_old_orders);

	$ms_id=$_POST["ms_id"];
	$order_date=$_POST["order_date"];
	$shop_id=$_POST["shop_id"];

	// Whichever location the DM confirmed on submit (original visit location,
	// or their current location if they chose to update it) — see the
	// confirm() prompt in the page's JS. Falls back to NULL if geolocation
	// was never available on either the original visit or this edit.
	$edit_latitude  = (isset($_POST['latitude'])  && $_POST['latitude']  !== '') ? (float)$_POST['latitude']  : null;
	$edit_longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? (float)$_POST['longitude'] : null;

	$marketing_tool=$_POST["marketing_tool"];
	$marketing_tool=RemoveSpecialChar($marketing_tool);

	// Assign To TP — validate against active TPs; invalid/missing means "not
	// assigned" (same validation order_action_get.php applies on a fresh
	// Get Order). Without this the reinsert below would silently null out
	// whatever TP was previously assigned to this order.
	$tp_id = (int)($_POST["tp_id"] ?? 0);
	if ($tp_id > 0) {
		$tp_id_esc = (int)$tp_id;
		$tpCheck = mysqli_query($db_conn, "select id from territory_partners where id='$tp_id_esc' and is_active=1 limit 1");
		if (!$tpCheck || mysqli_num_rows($tpCheck) === 0) { $tp_id = 0; }
	}
	$tp_id_sql = $tp_id > 0 ? "'".$tp_id."'" : "NULL";

	$product_id = implode("#",$_REQUEST['pr_id']);
$qty = implode("#",$_REQUEST['qty']);

$product_id_ex = explode ("#",$product_id);
$qty_ex = explode ("#",$qty);

$number = count($product_id_ex);
$insertedLines = []; // pr_id/qty of the lines actually (re)inserted, for the TP bridge below
for ($i=0; $i<=$number; $i++)
{
     $product_id_value = $product_id_ex[$i];
     $qty_value = $qty_ex[$i];
	 $qty_value = RemoveSpecialChar($qty_value);

	 if($product_id_value!=NULL)
	 {

$select_count_dist="select count(*) as numShop from ms_orders where order_id='$update_orderid' and pr_id='$product_id_value'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
$result_count_dist=mysqli_fetch_array($fetc_count_dist);
if($result_count_dist['numShop']==0)
	{
	$lat_sql = $edit_latitude  !== null ? $edit_latitude  : "NULL";
	$lng_sql = $edit_longitude !== null ? $edit_longitude : "NULL";
        $sql="insert into ms_orders (order_id,shop_id,ms_id,tp_id,order_date,new_order,noorder_reason,marketing_tool,pr_id,qty,latitude,longitude) values ('$update_orderid','$shop_id','$ms_id',$tp_id_sql,'$order_date','yes','nil','$marketing_tool',
		'$product_id_value','$qty_value',$lat_sql,$lng_sql)";
		mysqli_query($db_conn,$sql);
		$insertedLines[] = ['pr_id' => (int)$product_id_value, 'qty' => (int)$qty_value];

	}


	 }

}

	// Mirror into the TP's own tp_orders pipeline, matching what a fresh Get
	// Order does (order_action_get.php / OrderTpBridge.php). Rows already
	// invoiced or voided by the TP are left completely untouched — those are
	// records of something the TP already acted on, not safe to silently
	// rewrite from here. Otherwise, drop the still-pending bridged rows and
	// recreate them from the edited lines/TP, same delete-then-reinsert
	// pattern used for ms_orders above.
	$stmtNonPending = $db_conn->prepare(
		"SELECT COUNT(*) AS n FROM tp_orders WHERE order_id=? AND (invoiced_inv_id IS NOT NULL OR voided_at IS NOT NULL)"
	);
	$stmtNonPending->bind_param('s', $update_orderid);
	$stmtNonPending->execute();
	$hasNonPendingTp = (int)($stmtNonPending->get_result()->fetch_assoc()['n'] ?? 0) > 0;
	$stmtNonPending->close();

	if (!$hasNonPendingTp) {
		$stmtPending = $db_conn->prepare(
			"DELETE FROM tp_orders WHERE order_id=? AND invoiced_inv_id IS NULL AND voided_at IS NULL"
		);
		$stmtPending->bind_param('s', $update_orderid);
		$stmtPending->execute();
		$stmtPending->close();

		if ($tp_id > 0 && !empty($insertedLines)) {
			bridgeOrderToTp($db_conn, $tp_id, $ms_id, $shop_id, $update_orderid, $order_date, $insertedLines);
		}
	}

	$_SESSION['successMessage']="Changes saved successfully!";
	echo "<script>window.location='manage_order_product';</script>";

}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">


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
		table td{padding:5px !important;}
		</style>
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
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
								
                                     <h1>
									<table class="headertble">
									<tr>
									<td><?php echo $title;?></td>
									<td><a href="<?php echo $manage_url;?>" title="<?php echo $manage_title;?>">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title">Basic Input</h5>
                                    </div>--->
                                    <div class="card-body">
									
                               <?php include("validate-scripts.php");?>        
<form method="post" enctype="multipart/form-data" id="editOrderForm" onsubmit="return confirmEditOrderLocation();">

<input type="hidden" name="update_no_order" value="1">
<input type="hidden" name="update_orderid" value="<?=$orderid;?>">
<input type="hidden" name="ms_id" value="<?=$result_product_list['ms_id'];?>">
<input type="hidden" name="order_date" value="<?=$result_product_list['order_date'];?>">
<input type="hidden" name="latitude"  id="edit_order_latitude"  value="<?=htmlspecialchars($result_product_list['latitude'] ?? '');?>">
<input type="hidden" name="longitude" id="edit_order_longitude" value="<?=htmlspecialchars($result_product_list['longitude'] ?? '');?>">
<div id="editLocationNote" class="text-muted" style="font-size:12.5px;margin-bottom:10px;"></div>

                                        <div class="example-container">
                                            <div class="example-content">
											
											<?php if (!$dmHasActiveTp): ?>
											<div style="margin-bottom:14px; font-size:12px; color:#374151; background:#f3f4f6; padding:8px 12px; border-radius:6px;">No active TP covers your assigned area — this order will be handled directly by the company.</div>
											<?php endif; ?>

											<div id="tpAssignBlock" style="<?=($dmHasActiveTp || $currentTpId > 0) ? '' : 'display:none;'?>">
											<label class="form-label">District Filter</label>
											<select id="district_filter_select" class="form-control" onchange="onDistrictChange(this.value)">
												<option value="">All Districts</option>
												<?php foreach($assignedDistricts as $d): ?>
												<option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
												<?php endforeach; ?>
											</select>
											<br/>

											<label class="form-label">Taluk Filter</label>
											<select id="taluk_filter_select" class="form-control" onchange="onTalukChange(this.value)" disabled>
												<option value="">All Taluks</option>
											</select>
											<br/>

											<label class="form-label">Firka Filter</label>
											<select id="firka_filter_select" class="form-control" onchange="onFirkaChange(this.value)" disabled>
												<option value="">Select Taluk First</option>
											</select>
											<br/>
											</div>

											<label for="exampleInputEmail1" class="form-label">Shop*</label>
    <select name="shop_id" id="shop_select" class="form-control" required="">
	<option value="<?=$result_shopcatt['id'];?>" hidden=""><?=$result_shopcatt['name'];?></option>
	<?php $selectShopCat="select * from ms_shop where ms_id='$markeingSTFID' order by id asc";
	$fetchShopCat=mysqli_query($db_conn,$selectShopCat);
	while($resultShopCat=mysqli_fetch_array($fetchShopCat)){?>
	<option value="<?php echo $resultShopCat['id'];?>"
		data-district-id="<?=(int)($resultShopCat['district_node_id'] ?? 0)?>"
		data-taluk-id="<?=(int)($resultShopCat['taluk_node_id'] ?? 0)?>"
		data-district="<?=htmlspecialchars($resultShopCat['district_name'] ?? '')?>"><?php echo $resultShopCat['name'];?></option>
	<?php  } ?>
	</select>
	<br/>

											<div id="tpSelectBlock" style="<?=($dmHasActiveTp || $currentTpId > 0) ? '' : 'display:none;'?>">
											<label class="form-label">Assign To TP</label>
											<select name="tp_id" id="tp_select" class="form-control">
												<option value="" hidden>Select TP</option>
												<?php foreach($tpList as $t): ?>
												<option value="<?=htmlspecialchars($t['id'])?>"
														data-district="<?=htmlspecialchars($t['branch_district'])?>"
														<?php if ((int)$t['id'] === $currentTpId) echo 'selected'; ?>>
													<?=htmlspecialchars($t['name'])?>
												</option>
												<?php endforeach; ?>
												<?php if ($currentTpId > 0 && !$currentTpInList): ?>
												<option value="<?=$currentTpId?>" selected><?=htmlspecialchars($currentTpName)?> (inactive)</option>
												<?php endif; ?>
											</select>
											<div id="noTpHint" style="display:none; margin-top:4px; font-size:12px; color:#9a6b00;">No TP covers this area yet — this order will go to the company for review instead.</div>
											</div>
											<br/>


			<label class="form-label">Marketing Tool*</label>
            <textarea name="marketing_tool" onkeypress="restrictSpecialChars(event)" class="form-control" required=""><?=$result_product_list['marketing_tool'];?></textarea>
			<br/>		
			
			<?php 
			 $select_product_listGETD="select * from ms_orders where order_id='$orderid' order by id asc";
				$fetch_product_listGETD=mysqli_query($db_conn,$select_product_listGETD);
				$coun_product_listGETD=mysqli_num_rows($fetch_product_listGETD);
				?>
			<script>
        function addRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	if(rowCount < 100){							// limit the user from creating fields more than your limits
		var row = table.insertRow(rowCount);
		var colCount = table.rows[<?=$coun_product_listGETD;?>].cells.length;
		for(var i=0; i<colCount; i++) {
			var newcell = row.insertCell(i);
			newcell.innerHTML = table.rows[<?=$coun_product_listGETD;?>].cells[i].innerHTML;
		}
	}else{
		 alert("Maximum allowed record is 100.");
			   
	}
}
function deleteRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	for(var i=0; i<rowCount; i++) {
		var row = table.rows[i];
		var chkbox = row.cells[0].childNodes[0];
		if(null != chkbox && true == chkbox.checked) {
			if(rowCount <= 1) { 						// limit the user from removing all the fields
				alert("Cannot Remove all Field .");
				break;
			}
			table.deleteRow(i);
			rowCount--;
			i--;
		}
	}
}</script> 
				
				<p> 
					<button type="button" class="btn btn-primary btn-burger" onClick="addRow('dataTable')"><i class="material-icons">add</i></button> 
					<button type="button" class="btn btn-danger btn-burger" onClick="deleteRow('dataTable')"><i class="material-icons">delete_outline</i></button>
				</p>
				
				 <table id="dataTable" border="0">
					
					
				<?php 
				while($result_product_listGETD=mysqli_fetch_array($fetch_product_listGETD))
				{
				//Product Details
				$select_PRDetails="select * from `products` where id='".$result_product_listGETD['pr_id']."'";
				$fetch_PRDetails=mysqli_query($db_conn,$select_PRDetails);
				$result_PRDetails=mysqli_fetch_array($fetch_PRDetails);
				
				?>
				  <tr>
						<td>&nbsp;</td>
						 <td>
							<select name="pr_id[]" class="form-control">
<option value="<?=$result_product_listGETD['pr_id'];?>" hidden=""><?=$result_PRDetails['productName'];?></option>
										</td>

<td><input type="number" placeholder="Qty" min="0" value="<?=$result_product_listGETD['qty'];?>" name="qty[]" class="form-control"/>
						 </td>
                    </tr>
				<?php }?>
					
					
                    <tr>
						<td><input type="checkbox" name="chk[]"/></td>
						 <td>
							<select name="pr_id[]" class="form-control">
<option value="" hidden="">Select Product</option>
<?php $select_product_list12="select * from products";
										$fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
										while($result_product_list12=mysqli_fetch_array($fetch_product_list12))
										{
											?>
<option value="<?=$result_product_list12['id'];?>"><?=$result_product_list12['productName'];?></option>
										<?php }?>
										</td>

<td><input type="number" placeholder="Qty" min="0" name="qty[]" class="form-control"/>
						 </td>
                    </tr>
                </table>
				<br/>	
			
	<button type="submit" name="update_no_order" class="btn btn-primary">
	<i class="material-icons">update</i>Update</button>
												
                                            </div>
                                        </div>
										</form>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
    // ── Detect current location on this Edit page, separately from the
    // original visit's location already sitting in the hidden fields —
    // on submit, ask the DM which one to save (see confirmEditOrderLocation).
    var editCapturedLat = null;
    var editCapturedLng = null;
    var editOrigLat = document.getElementById('edit_order_latitude').value;
    var editOrigLng = document.getElementById('edit_order_longitude').value;
    var editLocationConfirmed = false;

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function (position) {
                editCapturedLat = position.coords.latitude;
                editCapturedLng = position.coords.longitude;
                var noteEl = document.getElementById('editLocationNote');
                if (noteEl) {
                    noteEl.textContent = '📍 Current location detected — you\'ll be asked whether to save it when you click Update.';
                }
            },
            function () { /* denied/unavailable — silently keep original location on submit */ },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    }

    function confirmEditOrderLocation() {
        if (editLocationConfirmed) { return true; } // already answered, let the real submit through
        if (editCapturedLat === null || editCapturedLng === null) { return true; } // nothing new detected — submit with original location as-is

        var useCurrent = confirm('Update order location to your current location?\n\nOK = use your current location\nCancel = keep the original Get Order location');
        if (useCurrent) {
            document.getElementById('edit_order_latitude').value = editCapturedLat;
            document.getElementById('edit_order_longitude').value = editCapturedLng;
        }
        // Cancel leaves the hidden fields at their original PHP-rendered values.

        editLocationConfirmed = true;
        document.getElementById('editOrderForm').submit();
        return false; // stop this submit attempt; the programmatic one above carries it through
    }

    // ── Assign To TP — ported from add_order.php's Get Order form: District →
    // Taluk → Firka cascade sourced from partner_location_nodes /
    // territory_partner_locations (not shop free-text), with a firka pick
    // auto-detecting and locking the TP field. This page's shop list is a
    // flat <select> rather than add_order's client-rebuilt list, so the
    // filters here just narrow/select within that same flat list instead of
    // replacing its options outright (kept simpler since there is no shop
    // geolocation-distance widget on this page to also keep in sync).
    var msDistrictData = <?php echo json_encode(array_column($assignedDistricts, null, 'id')); ?>;
    var talukFirkaData = <?php echo json_encode($talukFirkaMap); ?>;
    var firkaTpData    = <?php echo json_encode($firkaTpMap); ?>;
    var currentShopDistrict = <?php echo json_encode($result_shopcatt['district_name'] ?? ''); ?>;
    var currentShopDistrictId = <?php echo json_encode((string)($result_shopcatt['district_node_id'] ?? '')); ?>;
    var currentShopTalukId    = <?php echo json_encode((string)($result_shopcatt['taluk_node_id'] ?? '')); ?>;
    var currentTpIdJs = <?php echo (int)$currentTpId; ?>;

    var allShops = [];
    document.querySelectorAll('#shop_select option[data-district-id]').forEach(function(opt) {
        allShops.push({
            id: opt.value,
            text: opt.textContent.trim(),
            districtId: opt.getAttribute('data-district-id'),
            talukId: opt.getAttribute('data-taluk-id'),
            district: opt.getAttribute('data-district')
        });
    });

    var allTPs = [];
    var tpSelectEl = document.getElementById('tp_select');
    if (tpSelectEl) {
        document.querySelectorAll('#tp_select option[data-district]').forEach(function(opt) {
            allTPs.push({
                id: opt.value,
                text: opt.textContent.trim(),
                district: (opt.getAttribute('data-district') || '').trim().toLowerCase()
            });
        });
    }

    function rebuildTPs(district) {
        var select = document.getElementById('tp_select');
        if (!select) { return; }
        var currentVal = select.value;
        select.innerHTML = '';
        var blank = document.createElement('option');
        blank.value = ''; blank.hidden = true; blank.textContent = 'Select TP';
        select.appendChild(blank);

        var normDistrict = (district || '').trim().toLowerCase();
        allTPs.forEach(function(t) {
            if (!normDistrict || t.district === normDistrict) {
                var o = document.createElement('option');
                o.value = t.id; o.textContent = t.text;
                select.appendChild(o);
            }
        });

        var hasCurrent = Array.prototype.some.call(select.options, function(o) { return o.value === currentVal; });
        if (hasCurrent) { select.value = currentVal; }

        var hint = document.getElementById('noTpHint');
        if (hint) { hint.style.display = (select.options.length <= 1) ? 'block' : 'none'; }
    }

    if (tpSelectEl) {
        var shopSelectEl = document.getElementById('shop_select');
        if (shopSelectEl) {
            shopSelectEl.addEventListener('change', function() {
                // Skip while a Firka pick has auto-detected and locked the TP.
                if (tpSelectEl.style.pointerEvents === 'none') { return; }
                var opt = this.options[this.selectedIndex];
                rebuildTPs(opt ? opt.getAttribute('data-district') : '');
            });
        }
    }

    function resetFirkaAndTp() {
        var firkaSel = document.getElementById('firka_filter_select');
        if (!firkaSel) { return; }
        firkaSel.innerHTML = '<option value="">Select Taluk First</option>';
        firkaSel.disabled = true;
        unlockTpSelect();
    }

    function unlockTpSelect() {
        var tp = document.getElementById('tp_select');
        if (!tp) { return; }
        tp.style.pointerEvents = '';
        rebuildTPs('');
    }

    function onDistrictChange(districtId) {
        var talukSel = document.getElementById('taluk_filter_select');
        talukSel.innerHTML = '<option value="">All Taluks</option>';
        talukSel.disabled = !districtId;

        var d = msDistrictData[districtId];
        if (d) {
            d.taluks.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t.id; o.textContent = t.name;
                talukSel.appendChild(o);
            });
        }

        resetFirkaAndTp();
    }

    function onTalukChange(talukId) {
        var firkaSel = document.getElementById('firka_filter_select');
        if (firkaSel) {
            firkaSel.innerHTML = '<option value="">All Firkas</option>';
            firkaSel.disabled = !talukId;
            var firkas = talukFirkaData[talukId] || [];
            firkas.forEach(function(f) {
                var o = document.createElement('option');
                o.value = f.id; o.textContent = f.name;
                firkaSel.appendChild(o);
            });
        }
        unlockTpSelect();
    }

    function onFirkaChange(firkaId) {
        var tpSel = document.getElementById('tp_select');
        if (!tpSel) { return; }
        var tp = firkaTpData[firkaId];
        if (!tp) { unlockTpSelect(); return; }

        // Auto-detect the TP covering this firka and lock the field — same
        // behavior as add_order.php's Get Order form.
        var hasOpt = Array.prototype.some.call(tpSel.options, function(o) { return o.value == tp.id; });
        if (!hasOpt) {
            var o = document.createElement('option');
            o.value = tp.id; o.textContent = tp.name;
            tpSel.appendChild(o);
        }
        tpSel.value = tp.id;
        tpSel.style.pointerEvents = 'none';
    }

    // Pre-fill District → Taluk → Firka filters from the shop this order
    // already belongs to, so opening Edit shows where the order actually is
    // instead of blank filters the DM has to re-pick by hand. Populated
    // directly (not via onDistrictChange/onTalukChange) because those also
    // reset the Firka/TP selection — here we want to land on the Firka that
    // matches the order's CURRENT TP, not wipe it.
    (function() {
        var districtSel = document.getElementById('district_filter_select');
        var talukSel     = document.getElementById('taluk_filter_select');
        var firkaSel     = document.getElementById('firka_filter_select');
        if (!districtSel || !talukSel || !firkaSel) { return; }

        if (currentShopDistrictId && msDistrictData[currentShopDistrictId]) {
            districtSel.value = currentShopDistrictId;

            var d = msDistrictData[currentShopDistrictId];
            d.taluks.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t.id; o.textContent = t.name;
                talukSel.appendChild(o);
            });
            talukSel.disabled = false;

            if (currentShopTalukId) {
                talukSel.value = currentShopTalukId;

                var firkas = talukFirkaData[currentShopTalukId] || [];
                var matchedFirkaId = '';
                firkas.forEach(function(f) {
                    var o = document.createElement('option');
                    o.value = f.id; o.textContent = f.name;
                    firkaSel.appendChild(o);
                    var tp = firkaTpData[f.id];
                    if (currentTpIdJs > 0 && tp && Number(tp.id) === currentTpIdJs) {
                        matchedFirkaId = f.id;
                    }
                });
                firkaSel.disabled = false;

                if (matchedFirkaId) {
                    firkaSel.value = matchedFirkaId;
                    // Lock the TP field to match, same as picking this Firka
                    // by hand would — without going through onFirkaChange's
                    // unlockTpSelect() fallback path.
                    if (tpSelectEl) { tpSelectEl.style.pointerEvents = 'none'; }
                }
            }
        }

        // Only re-scope the plain TP dropdown by district when nothing above
        // already locked it to a specific Firka's TP, and no TP is assigned
        // yet — otherwise this would silently filter out (and blank) the
        // order's existing assignment before the DM has touched anything.
        if (tpSelectEl && !tpSelectEl.value) {
            rebuildTPs(currentShopDistrict);
        }
    })();
    </script>
</body>

</html>