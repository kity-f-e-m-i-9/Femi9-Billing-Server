<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
date_default_timezone_set("Asia/Kolkata");

$tempid=base64_decode($_REQUEST['tempid']);
$select_product_list="select * from ot_sales where tempid='$tempid'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
$result_product_list=mysqli_fetch_array($fetch_product_list);

// Company Profile that this sale's return belongs to — shown read-only
// under the Godown (physical) picker, so it's clear at a glance which
// company profile (e.g. FEMI NAYAN LLP) the selected warehouse (G1) feeds.
$stmt_ret_godown = $db_conn->prepare("SELECT gname FROM company_godown WHERE id = ? LIMIT 1");
$stmt_ret_godown->bind_param('i', $result_product_list['godownid']);
$stmt_ret_godown->execute();
$ret_godown_row = $stmt_ret_godown->get_result()->fetch_assoc();
$stmt_ret_godown->close();
$ret_company_profile_name = $ret_godown_row['gname'] ?? '';

// Active physical warehouses — same "Godown (physical)" picker as Add OT Sales,
// so returned stock can be credited back to a specific warehouse instead of
// always inheriting whatever the original sale line was tracked under.
// Restricted to G1 only: OT channel sales are overwhelmingly booked against
// G1 (company_godown FEMI NAYAN LLP) — 32k+ of ~35.6k ot_sales rows — so a
// return picker offering R1/G2/G3/HO would just invite a wrong warehouse
// being selected for stock that was never there.
$stmt_ret_warehouse = $db_conn->prepare("SELECT id, code, name FROM warehouses WHERE is_active = 1 AND code = 'G1' ORDER BY code ASC");
$stmt_ret_warehouse->execute();
$all_return_warehouses = $stmt_ret_warehouse->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ret_warehouse->close();

if(isset($_REQUEST['addReturn']))
{
	$tempid=$_REQUEST['tempid'];
	$godownid=$_REQUEST['godownid'];
	
	$date=date("Y-m-d",strtotime($_REQUEST['date']));
	$product_id=$_REQUEST['product_id'];
	$qty=$_REQUEST['qty'];

//GET SALES QTY
$select_sls_details="select * from ot_sales where tempid='$tempid' and prid='$product_id'";
$fetch_sls_details=mysqli_query($db_conn,$select_sls_details);
$result_sls_details=mysqli_fetch_array($fetch_sls_details);
$Total_sls_qty=$result_sls_details['qty'];
$buyer_gsttype=$result_sls_details['buyer_gsttype'];
$hsn=$result_sls_details['hsn'];
$gst_type=$result_sls_details['gst_type']; /*inner, outer*/
// Which physical warehouse gets the returned stock back — TP/admin picks it
// on this form now (like the Godown dropdown on Add OT Sales), instead of
// always inheriting the original sale line's warehouse_id. Falls back to
// the sale's own warehouse when the form leaves it unselected.
$returnWarehouseId = isset($_REQUEST['warehouse_id']) && $_REQUEST['warehouse_id'] !== ''
    ? (int)$_REQUEST['warehouse_id']
    : null;
$lineWarehouseId = $returnWarehouseId !== null
    ? $returnWarehouseId
    : ($result_sls_details['warehouse_id'] !== null ? (int)$result_sls_details['warehouse_id'] : null);

//gstin
/*
$buyer_GSTIN=$result_sls_details['gst_number'];
$buyer_GSTIN_count=strlen($buyer_GSTIN);
if($buyer_GSTIN_count==15){ $buyer_gsttype="register";}else {$buyer_gsttype="unregister";}
*/

if($qty<=$Total_sls_qty)
{
	
$select_count_product="select count(*) as numCountRcds from ot_sales_return where tempid='$tempid' and prid='$product_id'";
$fetch_count_product=mysqli_query($db_conn,$select_count_product);
$result_count_product=mysqli_fetch_array($fetch_count_product);
if($result_count_product['numCountRcds']==0)
	{
		$price              = (float) $result_sls_details['price'];
		$total_return_amount = $price * (int)$qty;
		$stockService        = new StockService($db_conn);
		$createdBy           = $_SESSION['LOGIN_USER'] ?? 'system';
		$godownid_s          = (string) $godownid;
		$product_id_i        = (int) $product_id;
		$qty_i               = (int) $qty;

		$db_conn->begin_transaction();
		try {
			// Insert return record
			$stmt = $db_conn->prepare(
				"INSERT INTO ot_sales_return
					(tempid, prid, qty, return_date, godownid, warehouse_id,
					 buyer_gsttype, price, total, hsn, gst_type)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
			);
			$stmt->bind_param(
				'siissisddss',
				$tempid, $product_id_i, $qty_i, $date, $godownid_s, $lineWarehouseId,
				$buyer_gsttype, $price, $total_return_amount, $hsn, $gst_type
			);
			$stmt->execute();
			$stmt->close();

			// Reverse OT sale stock via StockService (FOR UPDATE lock + ledger entry)
			$reverseResult = $stockService->otReverse(
				$product_id_i, $Login_user_TYPEvl, $godownid_s,
				$qty_i, $tempid, $createdBy,
				true, // externalTransaction
				$lineWarehouseId
			);

			if (!$reverseResult['success']) {
				// No stock row exists yet for this product in the chosen
				// warehouse (e.g. it never had this product input there) —
				// don't silently commit a return whose stock never actually
				// landed anywhere.
				throw new \RuntimeException('No stock row for this product in the selected warehouse.');
			}

			$db_conn->commit();
			echo "<script>window.location='ot-sale-return.php?tempid=".base64_encode($tempid)."&&AddedSucc';</script>";

		} catch (\Throwable $e) {
			$db_conn->rollback();
			error_log("ot-sale-return error: " . $e->getMessage());
			echo "<script>window.location='ot-sale-return.php?tempid=".base64_encode($tempid)."&&StockError';</script>";
		}
	}else{
		echo "<script>window.location='ot-sale-return.php?tempid=".base64_encode($tempid)."&&Alreadyexists';</script>";
	}
	
	
	
}else{
	
	echo "<script>window.location='ot-sale-return.php?tempid=".base64_encode($tempid)."&&InvalidQTY';</script>";
}


}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add OT Sales Return : <?php echo $business_name;?></title>
	<style>
	.form-control{margin-top:5px;margin-bottom:5px;}
	</style>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
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
									<td>Add OT Sales Return</td>
									<td><a href="ot-sale-view" title="Manage Input Stock">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
								
								
								<?php if(isset($_REQUEST['AddedSucc'])){?><div class="alert alert-success">OT Sales Return added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-danger">Deleted success.</div><?php }?>
								
								<?php if(isset($_REQUEST['InvalidQTY'])){?><div class="alert alert-warning">Warning! Return qty are more than Sales qty.</div><?php }?>
								
								<?php if(isset($_REQUEST['Alreadyexists'])){?><div class="alert alert-warning">Warning! This product return details already exists.</div><?php }?>

								<?php if(isset($_REQUEST['StockError'])){?><div class="alert alert-danger">Could not add return — check that the selected Godown (physical) already has stock for this product, then try again.</div><?php }?>
								
								
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                   <div class="card-header">
                                        <h5 class="card-title">Order Number : <?=$result_product_list['order_number'];?></h5>
                                    </div>
                                    <div class="card-body">
                                       
	 <?php include("validate-scripts.php");?>	 
	 
	 <?php if($result_product_list["amount_received"]==0){?>
<form method="post" enctype="multipart/form-data" onsubmit="return confirm('Add this return? Stock will be credited back to the selected Godown (physical) immediately.');">
<input type="hidden" name="tempid" value="<?=$tempid?>">
<input type="hidden" name="godownid" value="<?=$result_product_list['godownid'];?>">

                                        <div class="example-container">
                                        <div class="example-content">
										
<label class="form-label">Return Date*</label>
<input type="date" required="" name="date" value="<?php echo date("Y-m-d");?>" class="form-control">
          

            <select required="" name="product_id" id="productSelect" class="form-control" required="" onchange="updateReturnQtyMax()">
<option value="" hidden="">Select Product</option>
<?php
// Products already returned for this invoice are left out here — the
// duplicate check below still blocks a resubmit either way, but hiding
// them stops a product from being offered for "return" a second time
// once it's already gone back through the proper stock-reversing flow.
$select_product_listSD="select * from ot_sales where tempid='$tempid' and prid not in (select prid from ot_sales_return where tempid='$tempid')";
$fetch_product_listSD=mysqli_query($db_conn,$select_product_listSD);
while($result_product_listSD=mysqli_fetch_array($fetch_product_listSD))
{

$select_product_list12="select * from products where id='".$result_product_listSD['prid']."'";
$fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
$result_product_list12=mysqli_fetch_array($fetch_product_list12);
?>
<option value="<?=$result_product_list12['id'];?>" data-maxqty="<?=(int)$result_product_listSD['qty'];?>"><?=$result_product_list12['productName'];?> (Invoiced Qty: <?=(int)$result_product_listSD['qty'];?>)</option>
<?php }?>
</select>
<input type="number" placeholder="Return Qty" min="0" id="returnQtyInput" name="qty" class="form-control" required=""/>
<small id="returnQtyMaxHint" class="form-text text-muted"></small>
<script>
function updateReturnQtyMax() {
    var sel  = document.getElementById('productSelect');
    var opt  = sel.options[sel.selectedIndex];
    var qty  = document.getElementById('returnQtyInput');
    var hint = document.getElementById('returnQtyMaxHint');
    var max  = opt ? opt.getAttribute('data-maxqty') : null;
    if (max) {
        qty.setAttribute('max', max);
        qty.setAttribute('placeholder', 'Max: ' + max);
        hint.textContent = 'Maximum return qty for this product in this invoice: ' + max;
    } else {
        qty.removeAttribute('max');
        qty.setAttribute('placeholder', 'Return Qty');
        hint.textContent = '';
    }
}
</script>

<label class="form-label">Godown (physical)</label>
<select required name="warehouse_id" class="form-control">
    <?php foreach ($all_return_warehouses as $wh): ?>
        <option value="<?= (int)$wh['id'] ?>" selected>
            <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
        </option>
    <?php endforeach; ?>
</select>
<small class="form-text text-muted">Company Profile: <strong><?= htmlspecialchars($ret_company_profile_name, ENT_QUOTES, 'UTF-8') ?></strong></small>
<br/>

<button type="submit" name="addReturn" class="btn btn-primary"><i class="material-icons">add</i>Add</button>
												
                                            </div>
                                        </div>
										</form>
	 <?php }?>
										
										<br/>
										
										
										<table class="table">
											<thead>
											<tr>
											<th>Product Name</th>
											<th>Return Qty</th>
											<?php if($result_product_list["amount_received"]==0){?>
											<th>Return Date</th><?php }?>
											</tr>
											</thead>
											
											
											<tbody>
		<?php $select_RtnDetails="select * from ot_sales_return where tempid='$tempid' order by id asc";
				$fetch_RtnDetails=mysqli_query($db_conn,$select_RtnDetails);
										while($result_RtnDetails=mysqli_fetch_array($fetch_RtnDetails))
										{
											
		$select_product_list12SD="select * from products where id='".$result_RtnDetails['prid']."'";
$fetch_product_list12SD=mysqli_query($db_conn,$select_product_list12SD);
$result_product_list12SD=mysqli_fetch_array($fetch_product_list12SD);

$pass_rtnid=base64_encode($result_RtnDetails['id']);
$pass_tempid=base64_encode($tempid);
										?>
                                                <tr>
                                                    <td><?php echo $result_product_list12SD["productName"];?></td>
													<td><?php echo $result_RtnDetails["qty"];?></td>
													<td><?php echo date("d/m/Y",strtotime($result_RtnDetails["return_date"]));?></td>
													
													
													<?php if($result_product_list["amount_received"]==0){?>
																										<td>
													    <div class="actions-group">
													        <a href="ot-return-delete.php?id=<?=$pass_rtnid;?>&&tempid=<?=$pass_tempid;?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
													    </div>
													</td>
													<?php }?>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>