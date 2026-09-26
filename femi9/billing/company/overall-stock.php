<?php include("checksession.php"); require_once("include/GodownAccess.php");
require_once("include/PermissionCheck.php"); requirePermission('products');
error_reporting(0);
// Pulled from the Neksomo menu — a purpose-built stock view is coming for that login.
if (is_neksomo_login($db_conn)) { header("Location: dashboard.php"); exit; }
$user_type_Loginvl="company";

// Warehouse code/name lookup, used both to label per-warehouse cards below
// and to build the warehouse filter checkboxes.
$warehouseNames = [];
$whRes = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($whRes && ($whRow = $whRes->fetch_assoc())) {
    $warehouseNames[(int)$whRow['id']] = $whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '');
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
    <title>Overall Stocks : <?php echo $business_name;?></title>

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

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

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
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
								<?php
								$select_sumclosing12="select sum(closing_qty) from stock where user_type='$user_type_Loginvl'
										and product_id in (select id from products where temp_id not like 'NKS-%' or temp_id is null)";
										$Fetch_sumclosing12=mysqli_query($db_conn,$select_sumclosing12);
										$Result_sumclosing12=mysqli_fetch_array($Fetch_sumclosing12);
										?>
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Overall Stocks : <?=$Result_sumclosing12[0];?> (Qty)</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>

						<form method="post" enctype="multipart/form-data" action="overstock_datewise" id="datewiseFilterForm" onsubmit="return validateGodownChecks();">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" class="form-control">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" class="form-control">
</div>
<div id="searchleftcont">
<label class="form-label">Company Profile <span style="color:red;">*</span></label>
							   <div class="ms-dropdown" style="position:relative;">
							   <div class="form-control" id="godownDropdownToggle" onclick="toggleGodownDropdown(event)" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
							   <span id="godownDropdownLabel" style="color:#6c757d;">Select</span>
							   <i class="material-icons" style="font-size:20px;">arrow_drop_down</i>
							   </div>
							   <div id="godownDropdownPanel" style="display:none;position:absolute;z-index:20;background:#fff;border:1px solid #ced4da;border-radius:4px;padding:8px 12px;margin-top:2px;min-width:100%;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
							   <?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						   <label style="font-weight:normal;display:flex;align-items:center;gap:6px;padding:5px 0;white-space:nowrap;margin:0;">
						   <input type="checkbox" name="godownid[]" value="<?=$result_Godown['id'];?>" class="godown-check" onchange="updateGodownDropdownLabel()"> <?=$result_Godown['gname'];?>
						   </label>
							   <?php }?>
							   </div>
							   </div>
</div>

<div id="searchleftcont">
<label class="form-label">Warehouse</label>
							   <div class="ms-dropdown" style="position:relative;">
							   <div class="form-control" id="warehouseDropdownToggle" onclick="toggleWarehouseDropdown(event)" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
							   <span id="warehouseDropdownLabel" style="color:#6c757d;">All</span>
							   <i class="material-icons" style="font-size:20px;">arrow_drop_down</i>
							   </div>
							   <div id="warehouseDropdownPanel" style="display:none;position:absolute;z-index:20;background:#fff;border:1px solid #ced4da;border-radius:4px;padding:8px 12px;margin-top:2px;min-width:100%;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
							   <label style="font-weight:normal;display:flex;align-items:center;gap:6px;padding:5px 0;white-space:nowrap;margin:0;">
							   <input type="checkbox" name="warehouseid[]" value="unassigned" class="warehouse-check"> Unassigned
							   </label>
							   <?php foreach ($warehouseNames as $whId => $whLabel): ?>
							   <label style="font-weight:normal;display:flex;align-items:center;gap:6px;padding:5px 0;white-space:nowrap;margin:0;">
							   <input type="checkbox" name="warehouseid[]" value="<?=(int)$whId;?>" class="warehouse-check"> <?=htmlspecialchars($whLabel, ENT_QUOTES, 'UTF-8');?>
							   </label>
							   <?php endforeach; ?>
							   </div>
							   </div>
</div>

<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
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

						<!-- Product / Godown filters — client-side, applied across all cards below -->
						<div class="row">
							<div class="col">
								<div class="card">
									<div class="card-body">
										<div class="row g-3 align-items-end">
											<div class="col-md-5">
												<label class="form-label">Filter by Product</label>
												<input type="text" id="productFilterInput" class="form-control" placeholder="Type a product name…">
											</div>
											<div class="col-md-7">
												<label class="form-label">Filter by Godown</label>
												<div>
													<label style="font-weight:normal;display:inline-flex;align-items:center;gap:4px;margin-right:14px;">
														<input type="checkbox" class="warehouse-filter-check" value="all" checked> All
													</label>
													<?php foreach ($warehouseNames as $whId => $whLabel): ?>
													<label style="font-weight:normal;display:inline-flex;align-items:center;gap:4px;margin-right:14px;">
														<input type="checkbox" class="warehouse-filter-check" value="wh-<?=(int)$whId;?>" checked> <?=htmlspecialchars($whLabel, ENT_QUOTES, 'UTF-8');?>
													</label>
													<?php endforeach; ?>
													<label style="font-weight:normal;display:inline-flex;align-items:center;gap:4px;">
														<input type="checkbox" class="warehouse-filter-check" value="unassigned" checked> Unassigned
													</label>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<?php
						//get Godown Details
$select_Godowndetails="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
while($result_Godown=mysqli_fetch_array($fetch_Godowndetails))
{
$user_id_Loginvl=$result_Godown['id'];

// Pull every stock row for this godown (no GROUP BY — each warehouse's row
// stays separate so it can render as its own card), keyed by warehouse
// bucket. "" (empty string key) is the Unassigned bucket (warehouse_id IS NULL).
$warehouseBuckets = [];   // bucketKey => ['label' => ..., 'rows' => [productId => row]]
$warehouseBuckets[''] = ['label' => 'Unassigned', 'rows' => []];
foreach ($warehouseNames as $whId => $whLabel) {
    $warehouseBuckets[(string)$whId] = ['label' => $whLabel, 'rows' => []];
}

// Excludes Neksomo's raw piece-native placeholder products (temp_id LIKE
// 'NKS-%') — these are internal purchase-tracking rows (see
// neksomo-manufacturer-purchase-action.php's neksomo_credit_pieces()), not
// something any company-facing stock report should surface; the mapped
// normal company product is what should show here (see NeksomoStockBridge.php).
$select_OPStock="select product_id, warehouse_id, opening_qty, opening_date, input_qty, sales_qty, closing_qty, extra_pieces
    from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'
    and product_id in (select id from products where temp_id not like 'NKS-%' or temp_id is null)";
$Fetch_OPStock=mysqli_query($db_conn,$select_OPStock);
while($Result_OPStock=mysqli_fetch_array($Fetch_OPStock))
{
    $StockProductID=$Result_OPStock['product_id'];
    $bucketKey = $Result_OPStock['warehouse_id'] !== null ? (string)$Result_OPStock['warehouse_id'] : '';
    if (!isset($warehouseBuckets[$bucketKey])) {
        // Row references a warehouse that's since been deactivated/removed —
        // still show its stock rather than silently dropping it.
        $warehouseBuckets[$bucketKey] = ['label' => "Godown #$bucketKey", 'rows' => []];
    }
    $warehouseBuckets[$bucketKey]['rows'][$StockProductID] = $Result_OPStock;
}

// Render the Unassigned bucket first (it's where every pre-warehouse row
// already lives, and where Internal Transfer / Movement to CP still show —
// see below), then each real warehouse in code order.
foreach ($warehouseBuckets as $bucketKey => $bucket) {
    if (empty($bucket['rows']) && $bucketKey !== '') continue; // skip empty warehouse cards entirely
    $isUnassignedBucket = ($bucketKey === '');
    $cardFilterClass = $isUnassignedBucket ? 'unassigned' : 'wh-' . (int)$bucketKey;
?>
						<div class="row wh-card" data-warehouse="<?=$cardFilterClass;?>">
							<div class="col">
								<div class="card">
									<div class="card-body">
										<h1><?=$result_Godown['gname'];?> &mdash; <?=htmlspecialchars($bucket['label'], ENT_QUOTES, 'UTF-8');?></h1>
										<div style="background:#fff;overflow:scroll;width:100%;">
										<table class="table">
											<thead>
												<tr>
													<th>Product Name</th>
													<th>Opening Stock Qty</th>
													<th>Opening Stock Date</th>
													<th style="text-align:right;">Input Stock Qty</th>
													<th style="text-align:right;">Sales Qty</th>
													<th style="text-align:right;">Internal Transfer Qty</th>
													<th style="text-align:right;">Movement to CP</th>
													<th style="text-align:right;">Closing Qty</th>
													<?php if (is_neksomo_login($db_conn)): ?>
													<th style="text-align:right;">Closing Qty (Pieces)</th>
													<?php endif; ?>
												</tr>
											</thead>
											<tbody>
<?php
$total_closing_pieces=0;
$total_closing_qty_shown=0;
$total_intrn_transfer=0;
$total_sent_other=0;
foreach ($bucket['rows'] as $StockProductID => $Result_OPStock) {
    $select_productDetils="select * from products where id='$StockProductID'";
    $Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
    $Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
    if ($Result_productDetils["productName"]==NULL) continue;

    // Always the godown's own real stock.closing_qty — no addition from
    // Neksomo's pending pool. Tracked per godown by its own pack quantity,
    // same as every other company profile on this page.
    $ClosingStock=(int)$Result_OPStock['closing_qty'];
    $PiecesPerPack=max((int)($Result_productDetils['pieces_per_pack'] ?? 1), 1);
    $ExtraPieces=(int)($Result_OPStock['extra_pieces'] ?? 0);
    $ClosingStockPieces=($ClosingStock*$PiecesPerPack)+$ExtraPieces;
    $total_closing_pieces+=$ClosingStockPieces;
    $total_closing_qty_shown+=$ClosingStock;

    // Internal Transfer Qty and Movement to CP are computed from tables
    // (internal_transfer, pl_godown_transfer_items) that have no
    // warehouse_id — they can't be attributed to a specific physical
    // warehouse, so they only appear on the Unassigned card; real
    // warehouse cards show a dash.
    $IntrnTransferQty = 0;
    $MovementToCP = 0;
    $DfdQty = 0;
    if ($isUnassignedBucket) {
        // Internal transfer broken out from the internal_transfer table itself
        // (send_from side, cumulative to date).
        $select_intrnQty="select sum(qty) from internal_transfer where product_id='$StockProductID' and send_from='$user_id_Loginvl'";
        $Fetch_intrnQty=mysqli_query($db_conn,$select_intrnQty);
        $IntrnTransferQty=(int)(mysqli_fetch_row($Fetch_intrnQty)[0] ?? 0);
        $total_intrn_transfer+=$IntrnTransferQty;

        // Demo/Free/Damage folded into Sales Qty (goods that left as demo/
        // free/damage grouped with sales rather than shown separately).
        $select_dfdQty="select sum(qty) from demofreedamage where product_id='$StockProductID' and userid='$user_id_Loginvl'";
        $Fetch_dfdQty=mysqli_query($db_conn,$select_dfdQty);
        $DfdQty=(int)(mysqli_fetch_row($Fetch_dfdQty)[0] ?? 0);

        // "Movement to CP" — stock physically transferred from this godown to
        // a Channel Partner via add-godown-to-location.php (pl-godown-
        // transfer-action.php, transfer_type='godown_to_location'), distinct
        // from the invoiced CP sales already counted in Sales Qty above.
        $select_plt2cpQty="select sum(i.quantity) from pl_godown_transfer_items i inner join pl_godown_transfers t on t.id=i.transfer_id where t.transfer_type='godown_to_location' and i.product_id='$StockProductID' and t.godown_id='$user_id_Loginvl'";
        $Fetch_plt2cpQty=mysqli_query($db_conn,$select_plt2cpQty);
        $MovementToCP=(int)(mysqli_fetch_row($Fetch_plt2cpQty)[0] ?? 0);
        $total_sent_other+=$MovementToCP;
    }
    $SalesQtyShown=(int)$Result_OPStock['sales_qty']+$DfdQty;
?>
												<tr class="product-row" data-product-name="<?php echo htmlspecialchars(strtolower($Result_productDetils['productName']), ENT_QUOTES, 'UTF-8'); ?>">
													<td><?php echo $Result_productDetils["productName"];?></td>
													<td><?php echo $Result_OPStock['opening_qty'];?></td>
													<td><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>
													<td align="right"><?php echo $Result_OPStock['input_qty'];?></td>
													<td align="right"><?php echo $SalesQtyShown;?></td>
													<td align="right"><?php echo $isUnassignedBucket ? $IntrnTransferQty : '&mdash;';?></td>
													<td align="right"><?php echo $isUnassignedBucket ? $MovementToCP : '&mdash;';?></td>
													<td align="right"><b><?php echo $ClosingStock;?></b></td>
													<?php if (is_neksomo_login($db_conn)): ?>
													<td align="right"><b><?php echo $ClosingStockPieces;?></b></td>
													<?php endif; ?>
												</tr>
<?php
}

?>
											</tbody>
											<tfoot>
												<tr>
													<td colspan="5" style="text-align:right;">Total</td>
													<td align="right"><b><?=$isUnassignedBucket ? $total_intrn_transfer : '&mdash;';?></b></td>
													<td align="right"><b><?=$isUnassignedBucket ? $total_sent_other : '&mdash;';?></b></td>
													<td align="right"><b><?=$total_closing_qty_shown;?></b></td>
													<?php if (is_neksomo_login($db_conn)): ?>
													<td align="right"><b><?=$total_closing_pieces;?></b></td>
													<?php endif; ?>
												</tr>
											</tfoot>
										</table>
										</div>
									</div>
								</div>
							</div>
						</div>
<?php
}
}
?>

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
    function validateGodownChecks() {
        var checked = document.querySelectorAll('.godown-check:checked');
        if (checked.length === 0) {
            alert('Select at least one Company Profile.');
            return false;
        }
        return true;
    }

    function toggleGodownDropdown(e) {
        e.stopPropagation();
        var panel = document.getElementById('godownDropdownPanel');
        panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
    }

    function updateGodownDropdownLabel() {
        var checked = document.querySelectorAll('.godown-check:checked');
        var label = document.getElementById('godownDropdownLabel');
        if (checked.length === 0) {
            label.textContent = 'Select';
            label.style.color = '#6c757d';
        } else if (checked.length === 1) {
            label.textContent = checked[0].closest('label').textContent.trim();
            label.style.color = '#000';
        } else {
            label.textContent = checked.length + ' companies selected';
            label.style.color = '#000';
        }
    }

    function toggleWarehouseDropdown(e) {
        e.stopPropagation();
        var panel = document.getElementById('warehouseDropdownPanel');
        panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
    }

    document.querySelectorAll('.warehouse-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var checked = document.querySelectorAll('.warehouse-check:checked');
            var label = document.getElementById('warehouseDropdownLabel');
            if (checked.length === 0) {
                label.textContent = 'All';
                label.style.color = '#6c757d';
            } else if (checked.length === 1) {
                label.textContent = checked[0].closest('label').textContent.trim();
                label.style.color = '#000';
            } else {
                label.textContent = checked.length + ' warehouses selected';
                label.style.color = '#000';
            }
        });
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.ms-dropdown').forEach(function (dropdown) {
            if (!dropdown.contains(e.target)) {
                var panel = dropdown.querySelector('[id$="DropdownPanel"]');
                if (panel) panel.style.display = 'none';
            }
        });
    });

    document.getElementById('godownDropdownPanel').addEventListener('click', function (e) {
        e.stopPropagation();
    });
    document.getElementById('warehouseDropdownPanel').addEventListener('click', function (e) {
        e.stopPropagation();
    });

    /* ── Product / Godown filters — purely client-side over the already-
       rendered cards, no page reload. ── */
    function applyStockFilters() {
        var searchTerm = (document.getElementById('productFilterInput').value || '').trim().toLowerCase();
        var checkedWarehouses = Array.prototype.slice.call(document.querySelectorAll('.warehouse-filter-check:checked')).map(function (cb) { return cb.value; });
        var allChecked = checkedWarehouses.indexOf('all') !== -1;

        document.querySelectorAll('.wh-card').forEach(function (card) {
            var wh = card.getAttribute('data-warehouse');
            var warehouseVisible = allChecked || checkedWarehouses.indexOf(wh) !== -1;
            if (!warehouseVisible) {
                card.style.display = 'none';
                return;
            }
            card.style.display = '';

            // Within a visible card, filter individual product rows by name;
            // hide the whole card if the search term matches nothing in it.
            var anyRowVisible = !searchTerm;
            card.querySelectorAll('.product-row').forEach(function (row) {
                var name = row.getAttribute('data-product-name') || '';
                var matches = !searchTerm || name.indexOf(searchTerm) !== -1;
                row.style.display = matches ? '' : 'none';
                if (matches) anyRowVisible = true;
            });
            if (searchTerm) {
                card.style.display = anyRowVisible ? '' : 'none';
            }
        });
    }

    document.getElementById('productFilterInput').addEventListener('input', applyStockFilters);
    document.querySelectorAll('.warehouse-filter-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (this.value === 'all' && this.checked) {
                // "All" overrides individual selections back to checked
                document.querySelectorAll('.warehouse-filter-check').forEach(function (other) { other.checked = true; });
            } else if (this.value !== 'all' && !this.checked) {
                document.querySelector('.warehouse-filter-check[value="all"]').checked = false;
            } else if (this.value !== 'all' && this.checked) {
                var allOthersChecked = Array.prototype.every.call(document.querySelectorAll('.warehouse-filter-check:not([value="all"])'), function (other) { return other.checked; });
                if (allOthersChecked) document.querySelector('.warehouse-filter-check[value="all"]').checked = true;
            }
            applyStockFilters();
        });
    });
    </script>
</body>

</html>
