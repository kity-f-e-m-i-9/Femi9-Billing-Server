<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); error_reporting(0); require_once("include/PermissionCheck.php"); requirePermission('ms');?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Marketing Staff > No Orders : <?php echo $business_name;?></title>

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
        .tp-tag { font-size:11px; padding:3px 9px; border-radius:6px; font-weight:600; white-space:nowrap; display:inline-block; }
        .tp-tag-good    { background:#e5f7e5; color:#0ca30c; }
        .tp-tag-bad     { background:#fbe6e6; color:#d03b3b; }
        .tp-tag-neutral { background:#f0f1f2; color:#52525b; }
        .tp-stat-hoverable { position:relative; cursor:default; }
        .tp-stat-tooltip {
            display:none; position:absolute; top:100%; left:0; margin-top:6px;
            z-index:50; background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            box-shadow:0 8px 24px rgba(16,24,40,.14); padding:10px 12px;
            min-width:180px; max-width:260px; max-height:240px; overflow-y:auto; text-align:left;
            font-size:12.5px; color:#374151;
        }
        .tp-stat-hoverable:hover .tp-stat-tooltip,
        .tp-stat-hoverable:focus-within .tp-stat-tooltip,
        .tp-stat-hoverable:focus .tp-stat-tooltip { display:block; }
        .tp-stat-tooltip-row { padding:3px 0; border-bottom:1px solid #f8fafc; }
        .tp-stat-tooltip-row:last-child { border-bottom:none; }
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
						$mt_filter = $_REQUEST['mt_filter'] ?? '';
						if (!in_array($mt_filter, ['', 'available', 'not_available'], true)) { $mt_filter = ''; }

						// Same marketing_tool parsing as territory-partner/manage-orders.php's
						// mt_filter (add_order.php's Available/Not Available toggle + tool
						// checkboxes, 2026-11-30) — kept as a separate copy here since this
						// page has no shared include to pull it from, matching this
						// portal's existing per-folder helper convention (see
						// RemoveSpecialChar.php). 2026-11-30, user-requested.
						function parseMarketingTool(?string $raw): array {
						    $raw = trim((string)$raw);
						    if ($raw === '') { return ['status' => null, 'tools' => [], 'raw' => '']; }
						    foreach (['Available', 'Not Available'] as $prefix) {
						        if (stripos($raw, $prefix . ':') === 0) {
						            $toolsPart = substr($raw, strlen($prefix) + 1);
						            $tools = array_values(array_filter(array_map('trim', explode(',', $toolsPart)), fn($t) => $t !== ''));
						            return ['status' => $prefix, 'tools' => $tools, 'raw' => $raw];
						        }
						    }
						    return ['status' => null, 'tools' => [], 'raw' => $raw];
						}
						//
						$select_msDetails12="select * from marketing_staff where id=?";
$fetch_msDetails12=db_q($db_conn,$select_msDetails12,[$se_msid]);
$result_msDetails12=mysqli_fetch_array($fetch_msDetails12);
						?>
						
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Marketing Staff > No Orders</td>
									<td>
									<a href="ms_orders_pdf2?frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&se_msid=<?=htmlspecialchars((string)($se_msid), ENT_QUOTES, 'UTF-8', false);?>" title="Export" target="_blank"><img src="32-pdf.png"></a>
									</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						
						<form method="post" enctype="multipart/form-data" action="<?=htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES);?>">

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
<label class="form-label">Marketing Staff</label>
<select name="se_msid" class="form-control">
<?php if($se_msid==NULL){?>
<option value="" hidden="">Select</option>
<?php }else{?>
<option value="<?=htmlspecialchars((string)($se_msid), ENT_QUOTES, 'UTF-8', false);?>" hidden=""><?=htmlspecialchars((string)(strtoupper($result_msDetails12['ms_name'])), ENT_QUOTES, 'ISO-8859-1', false);?>, <?=htmlspecialchars((string)($result_msDetails12['ms_mobile']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
<?php }?>
<?php $select_msDetailsOPT="select * from marketing_staff order by ms_name asc";
$fetch_msDetailsOPT=mysqli_query($db_conn,$select_msDetailsOPT);
while($result_msDetailsOPT=mysqli_fetch_array($fetch_msDetailsOPT))
{
?>
<option value="<?=htmlspecialchars((string)($result_msDetailsOPT['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)(strtoupper($result_msDetailsOPT['ms_name'])), ENT_QUOTES, 'ISO-8859-1', false);?>, <?=htmlspecialchars((string)($result_msDetailsOPT['ms_mobile']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
<?php }?>
</select>
</div>
<div id="searchleftcont">
<label class="form-label">Marketing Tool</label>
<select name="mt_filter" class="form-control">
<option value="" <?=$mt_filter===''?'selected':''?>>All</option>
<option value="available" <?=$mt_filter==='available'?'selected':''?>>Available</option>
<option value="not_available" <?=$mt_filter==='not_available'?'selected':''?>>Not Available</option>
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
													<th>Reason</th>
													<th>Marketing Tool</th>
                                                </tr>
                                            </thead>
											
											<tbody>
<?php 
if($from_date==NULL && $se_msid==NULL)
{
$select_product_list="select * from ms_orders where new_order='no' order by id desc";
}
if($from_date!=NULL && $se_msid==NULL)
{
$select_product_list="select * from ms_orders where new_order='no' and order_date between '".db_esc($from_date)."' and '".db_esc($to_date)."' order by id desc";
}
if($from_date!=NULL && $se_msid!=NULL)
{
$select_product_list="select * from ms_orders where new_order='no' and order_date between ? and ? and ms_id=? order by id desc";
}
// db_q() only takes params when the SQL actually has that many '?'
// placeholders (branch 3 above) — the other two branches already inline
// their values via db_esc(), so passing params there made db_q() return
// false (0 placeholders !== 3 values), which crashed this page with a
// TypeError on PHP 8.1+ the moment someone searched by date range without
// picking a Marketing Staff. Pre-existing bug, found while adding the
// Marketing Tool filter below (which also needed the result set as a plain
// array instead of a row-at-a-time while-loop). 2026-11-30.
$_noorderParams = ($from_date!=NULL && $se_msid!=NULL) ? [$from_date,$to_date,$se_msid] : [];
$fetch_product_list=db_q($db_conn,$select_product_list,$_noorderParams);
$_noorder_rows = $fetch_product_list ? mysqli_fetch_all($fetch_product_list, MYSQLI_ASSOC) : [];
if ($mt_filter !== '') {
    $_wantStatus = $mt_filter === 'available' ? 'Available' : 'Not Available';
    $_noorder_rows = array_filter($_noorder_rows, fn($r) => parseMarketingTool($r['marketing_tool'] ?? '')['status'] === $_wantStatus);
}
foreach ($_noorder_rows as $result_product_list12)
{


//shop category
$shop_id=$result_product_list12['shop_id'];
$select_shopcatt="select * from ms_shop where id=?";
$fetch_shopcatt=db_q($db_conn,$select_shopcatt,[$shop_id]);
$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);

//marketing staff details
$ms_id=$result_product_list12['ms_id'];
$select_msDetails="select * from marketing_staff where id=?";
$fetch_msDetails=db_q($db_conn,$select_msDetails,[$ms_id]);
$result_msDetails=mysqli_fetch_array($fetch_msDetails);
?>
                                            
                                               <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=htmlspecialchars((string)($result_msDetails['ms_name']), ENT_QUOTES, 'ISO-8859-1', false);?><br/>
					<?=htmlspecialchars((string)($result_msDetails['ms_mobile']), ENT_QUOTES, 'ISO-8859-1', false);?>
					</td>
					<td><?=htmlspecialchars((string)($result_shopcatt['name']), ENT_QUOTES, 'ISO-8859-1', false);?></td>
					<td><?=htmlspecialchars((string)($result_shopcatt['mobile_number']), ENT_QUOTES, 'ISO-8859-1', false);?></td>
					<td><?=htmlspecialchars((string)(ucwords($result_shopcatt["address"])), ENT_QUOTES, 'ISO-8859-1', false);?></td>
					
					<td><?=date("d/m/Y",strtotime($result_product_list12["order_date"]));?></td>
					<td><?=htmlspecialchars((string)($result_product_list12["noorder_reason"]), ENT_QUOTES, 'ISO-8859-1', false);?></td>
					<td>
					<?php $mtp = parseMarketingTool($result_product_list12["marketing_tool"] ?? ''); ?>
					<?php if ($mtp['status'] === 'Available'): ?>
					<span class="tp-tag tp-tag-good tp-stat-hoverable" tabindex="0">Available
						<div class="tp-stat-tooltip">
							<?php if (empty($mtp['tools'])): ?>
							<div class="tp-stat-tooltip-row text-muted">None listed.</div>
							<?php else: foreach ($mtp['tools'] as $tool): ?>
							<div class="tp-stat-tooltip-row"><?=htmlspecialchars($tool)?></div>
							<?php endforeach; endif; ?>
						</div>
					</span>
					<?php elseif ($mtp['status'] === 'Not Available'): ?>
					<span class="tp-tag tp-tag-bad tp-stat-hoverable" tabindex="0">Not Available
						<div class="tp-stat-tooltip">
							<?php if (empty($mtp['tools'])): ?>
							<div class="tp-stat-tooltip-row text-muted">None listed.</div>
							<?php else: foreach ($mtp['tools'] as $tool): ?>
							<div class="tp-stat-tooltip-row"><?=htmlspecialchars($tool)?></div>
							<?php endforeach; endif; ?>
						</div>
					</span>
					<?php elseif ($mtp['raw'] !== ''): ?>
					<span class="tp-tag tp-tag-neutral tp-stat-hoverable" tabindex="0">Noted
						<div class="tp-stat-tooltip"><div class="tp-stat-tooltip-row"><?=htmlspecialchars($mtp['raw'])?></div></div>
					</span>
					<?php else: ?>
					<span class="text-muted">-</span>
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
</body>

</html>