<?php require_once __DIR__ . '/../shared/db-helpers.php'; 
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php"); 
error_reporting(0);

$from_month=$_REQUEST['frdate']; 
$to_month=$_REQUEST['todate'];

$to_month_days=date("t",strtotime($to_month));
$from_date=date("Y-m-01",strtotime($from_month));
$to_date=date("Y-m-".$to_month_days."",strtotime($to_month));

$get_godown_id=$_REQUEST['godown_id'];
if (!empty($get_godown_id) && !is_godown_allowed($db_conn, (int)$get_godown_id)) {
    header("Location: overall-stock?unauthorized"); exit;
}
//
$select_Godown_details="select * from company_godown where id=?";
$fetch_Godown_details=db_q($db_conn,$select_Godown_details,[$get_godown_id]);
$result_Godown_details=mysqli_fetch_array($fetch_Godown_details);							   
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Responsive Admin Dashboard Template">
    <meta name="keywords" content="admin,dashboard">
    <meta name="author" content="stacks">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    
    <!-- Title -->
    <title>GSTR1 : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

    
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
	
	<style type="text/css">
	#dashanch{color:#000 !important;}
	#dashanch:hover{color:#1a06a6 !important;}
	#reportdash th{font-size:13px;font-weight:600;}
	#reportdash td{font-weight:700;font-size:14px;}
	
	#gsttablevl{height:200px;margin-bottom:10px;}
	#gsttablevl tr th{border:1px solid #000;padding:5px;}
	#gsttablevl tr td{border:1px solid #000;text-align:right;padding:5px;}
	#gsttablevl a{text-decoration:none;color:blue;}
	#gsttablevl a:hover{background:#ddd;}
	
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
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description" style="margin-left:-25px;">
								<table style="width:100%;">
								<tr>
								<td><h1>GST Reports > GSTR1</h1></td>
								</tr>
								</table>
                                </div>
                            </div>
							
							
							<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Month</label>
<input type="month" required="" name="frdate" value="<?=$from_month;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Month</label>
<input type="month" required="" name="todate" value="<?=$to_month;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>

<div id="searchleftcont">
<label for="exampleInputEmail1" class="form-label">Company</label>
                               <select required name="godown_id" class="form-control">
							   <?php if($get_godown_id==NULL){?>
							   <option value="" hidden="">Select</option>
							   <?php }?>
							   <?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						  <option value="<?=$result_Godown['id'];?>" <?=($get_godown_id==$result_Godown['id'])?'selected':'';?>><?=$result_Godown['gname'];?></option>
							   <?php }?>
							   </select>
							   </div>
							   
							   
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>



							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
                        </div>
						
						<!--------------------------------------------------------------------->
						<!--------------------------------------------------------------------->
						<div class="row">
                           
						   <?php 
						   if($from_month!=NULL){
							   
							    include('gst_details.php'); 		//intra state (Tamilnadu)
							    include('gst_details_inter.php');	//inter state (Other States)	
							   
							 ?>
							 
							 
							 <table style="width:100%;">
							 <tr valign="top">
							 
						   <!----------Left------------>
						   <td>
							 
						   <h1>Intra-state</h1>
						   <table id="gsttablevl">
						   <tr>
						   <th width="50%"></th>
						   <th width="25%">registered person</th>
						   <th width="25%">unregistered person</th>
						   </tr>
						   
						   <?php
						   $Total_sls_register_intra=$total_intra_register+$total_intra_register2+$total_reg_TP;
						   $Total_sls_unregister_intra=$total_intra_unregister+$total_intra_unregister2+$total_unreg_TP;
						   ?>

						   <tr>
						   <th>Total Sales (SS,ST, DT, SHP, CUS, TP)</th>
						   <td><a href="gst_sls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($Total_sls_register_intra, 2);?></a></td>
						   <td><a href="gst_sls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($Total_sls_unregister_intra, 2);?></a></td>
						   </tr>
						   <tr>
						   <th>Total OT Sales</th>
						   <td><a href="gst_otsls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($total_reg_OTSLS_intra, 2);?></a></td>
						   <td><a href="gst_otsls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($total_reg_OTSLSUN_intra, 2);?></a></td>
						   </tr>
						    <tr>
						   <th>Total Internal Transfer Sales</th>
						    <td><a href="gst_intrsls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($total_reg_INTR, 2);?></a></td>
							 <td>0.00</td>
						   </tr>
						   <?php
						   $Total_intra_register_sales=$total_intra_register+$total_intra_register2+$total_reg_OTSLS_intra+$total_reg_INTR+$total_reg_TP;

						   $Total_intra_unregister_sales=$total_intra_unregister+$total_intra_unregister2+$total_reg_OTSLSUN_intra+$total_unreg_TP;
						   ?>
						   <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=inr_format($Total_intra_register_sales, 2);?></b></td>
							<td><b><?=inr_format($Total_intra_unregister_sales, 2);?></b></td>
						   </tr>
						   </tfoot>
						   </table>
						   
						   <?php include('gst_details_credit.php'); ?>
						   
						    <h3 style="color:red;">Credit Note</h3>
						   <table id="gsttablevl">
						   <tr>
						   <th width="50%"></th>
						   <th width="25%">registered person</th>
						   <th width="25%">unregistered person</th>
						   </tr>
						   <?php
						   $Total_sls_register_intra_credit=$total_intra_register_credit+$total_reg_TP_credit;
						   $Total_sls_unregister_intra_credit=$total_intra_unregister_credit+$total_unreg_TP_credit;
						   ?>
						   <tr>
						   <th>Sales Return<br/>(SS,ST, DT, SHP, CUS, TP)</th>
						   <td><a href="gst_credit_sls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=inr_format($Total_sls_register_intra_credit, 2);?></a>
						   </td>
						   <td>
						   <a href="gst_credit_sls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=inr_format($Total_sls_unregister_intra_credit, 2);?></a>
						   </td>
						   </tr>
						   <tr>
						   <th>OT Sales Return</th>
						  <td>
						  <a href="gst_credit_otsls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						  <?=inr_format($total_intra_register_creditOT, 2);?></a>
						  </td>
						  <td><a href="gst_credit_otsls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						  <?=inr_format($total_intra_unregister_creditOT, 2);?></a>
						  </td>
						   </tr>

						   <?php
							// intra-state (register) credit note
							$total_intra_register_credit_note=$total_intra_register_credit+$total_intra_register_creditOT+$total_reg_TP_credit;
							// intra-state (unregister) credit note
							$total_intra_unregister_credit_note=$total_intra_unregister_credit+$total_intra_unregister_creditOT+$total_unreg_TP_credit;
						   ?>
						   
						    <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=inr_format($total_intra_register_credit_note, 2);?></b></td>
							<td><b><?=inr_format($total_intra_unregister_credit_note, 2);?></b></td>
						   </tr>
						   </tfoot>
						   </table>
						   
						   </td>
							 
						   <td>&nbsp;&nbsp;</td>
							 
						   <!------------------------------------------------------------------------------>
						   <!-----------------------------Inter State (Other State)------------------------>
						   <!--------Right------------>
						   <td>
							 
						   <h1>Inter-state</h1>
						   <table id="gsttablevl">
						   <tr>
						   <th width="50%"></th>
						   <th width="25%">registered person</th>
						   <th width="25%">unregistered person</th>
						   </tr>
						   
						   <?php
						   $Total_sls_register_inter=$total_inter_register+$total_inter_register2+$total_reg_TP_inter;
						   $Total_sls_unregister_inter=$total_inter_unregister+$total_inter_unregister2+$total_unreg_TP_inter;
						   ?>

						   <tr>
						   <th>Total Sales (SS,ST, DT, SHP, CUS, TP)</th>
						   <td><a href="gst_sls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($Total_sls_register_inter, 2);?></a></td>
						   <td><a href="gst_sls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($Total_sls_unregister_inter, 2);?></a></td>
						   </tr>

						   <tr>
						   <th>Total OT Sales</th>
						   <td><a href="gst_otsls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($total_reg_OTSLS_inter, 2);?></a></td>
						   <td><a href="gst_otsls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($total_reg_OTSLSUN_inter, 2);?></a></td>
						   </tr>

						   <?php
						   $Total_inter_register_sales=$Total_sls_register_inter+$total_reg_OTSLS_inter;
						   $Total_inter_unregister_sales=$Total_sls_unregister_inter+$total_reg_OTSLSUN_inter;
						   ?>
						   <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=inr_format($Total_inter_register_sales, 2);?></b></td>
							<td><b><?=inr_format($Total_inter_unregister_sales, 2);?></b></td>
						   </tr>
						   </tfoot>
						   </table>
						   
						   <?php include('gst_details_credit_inter.php'); ?>
						   
						   <h3 style="color:red;">Credit Note</h3>
						   <table id="gsttablevl">
						   <tr>
						   <th width="50%"></th>
						   <th width="25%">registered person</th>
						   <th width="25%">unregistered person</th>
						   </tr>
						   <?php
						   $Total_sls_register_inter_credit=$total_inter_register_credit+$total_reg_TP_credit_inter;
						   $Total_sls_unregister_inter_credit=$total_inter_unregister_credit+$total_unreg_TP_credit_inter;
						   ?>
						   <tr>
						   <th>Sales Return<br/>(SS,ST, DT, SHP, CUS, TP)</th>
						   <td>
						   <a href="gst_credit_sls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=inr_format($Total_sls_register_inter_credit, 2);?></a>
						   </td>
						   <td><a href="gst_credit_sls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=inr_format($Total_sls_unregister_inter_credit, 2);?></a>
						   </td>
						   </tr>

						   <tr>
						   <th>OT Sales Return</th>
						   <td>
						   <a href="gst_credit_otsls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($total_inter_register_creditOT, 2);?></a>
						   </td>
						   <td>
						   <a href="gst_credit_otsls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=inr_format($total_inter_unregister_creditOT, 2);?></a>
						   </td>
						   </tr>

						   <?php
							// inter-state (register) credit note
							$total_inter_register_credit_note=$total_inter_register_credit+$total_inter_register_creditOT+$total_reg_TP_credit_inter;
							// inter-state (unregister) credit note
							$total_inter_unregister_credit_note=$total_inter_unregister_credit+$total_inter_unregister_creditOT+$total_unreg_TP_credit_inter;
						   ?>
						   
						    <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=inr_format($total_inter_register_credit_note, 2);?></b></td>
							<td><b><?=inr_format($total_inter_unregister_credit_note, 2);?></b></td>
						   </tr>
						   </tfoot>
						   </table>
						   
						   </td>
							 
							 </tr>
							 </table>
							 
						   <div style="clear:both;"></div>
						   <br/>

						   <h1 style="margin-top:20px;">GSTR-1 Filing Summary</h1>
						   <p style="color:#666;margin-top:-8px;">Standard GST portal table layout &mdash; Table 4 (B2B), Table 7 (B2C), Table 8 (Nil/Exempt/Non-GST), Table 12 (HSN Summary), Table 13 (Documents Issued).</p>
						   
						    <?php
							//Intra
			$intra_reg_supplies_grand_total=$Total_intra_register_sales-$total_intra_register_credit_note;
			$intra_unreg_supplies_grand_total=$Total_intra_unregister_sales-$total_intra_unregister_credit_note;

			//Inter
			$inter_reg_supplies_grand_total=$Total_inter_register_sales-$total_inter_register_credit_note;
			$inter_unreg_supplies_grand_total=$Total_inter_unregister_sales-$total_inter_unregister_credit_note;

			// Nil-rated (gst_percentage=0) vs taxable (gst_percentage>0) split of each grand
			// total above — $Total_*_sales mixes both rates together, so the filing table
			// below must not report the whole grand total as "Nil Rated". Both sides must
			// be net of their own credit notes (not the combined-rate credit note total
			// already baked into *_supplies_grand_total), otherwise taxable = grand_total
			// - gross_nil double-subtracts nil-rated returns and can go negative.
			$intra_reg_nil    = $nil_intra_register - $nil_intra_register_credit;
			$intra_reg_taxable    = $intra_reg_supplies_grand_total - $intra_reg_nil;
			$intra_unreg_nil  = $nil_intra_unregister - $nil_intra_unregister_credit;
			$intra_unreg_taxable  = $intra_unreg_supplies_grand_total - $intra_unreg_nil;
			$inter_reg_nil    = $nil_inter_register - $nil_inter_register_credit;
			$inter_reg_taxable    = $inter_reg_supplies_grand_total - $inter_reg_nil;
			$inter_unreg_nil  = $nil_inter_unregister - $nil_inter_unregister_credit;
			$inter_unreg_taxable  = $inter_unreg_supplies_grand_total - $inter_unreg_nil;
						   ?>
						   
						   <div align="right">
						   <a href="export_GSTR1?t1=<?=$intra_reg_supplies_grand_total;?>&&t2=<?=$intra_unreg_supplies_grand_total;?>&&t3=<?=$inter_reg_supplies_grand_total;?>&&t4=<?=$inter_unreg_supplies_grand_total;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a>
						   </div>
						   
						   <div style="clear:both;"></div>
						   <br/>

						   <h3>Table 8 — Nil Rated, Exempted &amp; Non-GST Outward Supplies</h3>
						   <table id="gsttablevl">
						   <tr>
						   <th>Description</th>
						   <th>Nil Rated Supplies</th>
						   <th>Taxable Supplies (GST Rated)</th>
						   <th>Non GST Supplies</th>
						   </tr>

						   <tr>
						   <th>Intra-state supplies to registered person</th>
						   <td><?=inr_format($intra_reg_nil, 2);?></td>
						   <td><?=inr_format($intra_reg_taxable, 2);?></td>
						   <td>0.00</td>
						   </tr>
						   <tr>
						   <th>Intra-state supplies to unregistered person</th>
						   <td><?=inr_format($intra_unreg_nil, 2);?></td>
						   <td><?=inr_format($intra_unreg_taxable, 2);?></td>
						   <td>0.00</td>
						   </tr>

						   <tr>
						   <th>Inter-state supplies to registered person</th>
						   <td><?=inr_format($inter_reg_nil, 2);?></td>
						   <td><?=inr_format($inter_reg_taxable, 2);?></td>
						   <td>0.00</td>
						   </tr>
						   <tr>
						   <th>Inter-state supplies to unregistered person</th>
						   <td><?=inr_format($inter_unreg_nil, 2);?></td>
						   <td><?=inr_format($inter_unreg_taxable, 2);?></td>
						   <td>0.00</td>
						   </tr>

						  <?php
						  $Nil_rated_total=$intra_reg_nil+$intra_unreg_nil+$inter_reg_nil+$inter_unreg_nil;
						  $Taxable_total=$intra_reg_taxable+$intra_unreg_taxable+$inter_reg_taxable+$inter_unreg_taxable;
						  ?>

						  <tr>
						  <td></td>
						 <td><?=inr_format($Nil_rated_total, 2);?></td>
						  <td><?=inr_format($Taxable_total, 2);?></td>
						  <td></td>
						  </tr>

						   </table>
						   
						   
						   <!-------------HSN wise Total Qty---------->
						   <br/>
						   <h3>Table 12 — HSN-wise Summary of Outward Supplies</h3>
						    <table id="gsttablevl" style="height:auto;">
							<tr>
							<th>HSN</th>
							<th>GST Rate</th>
							<th>UQC</th>
							<th>Total Quantity</th>
							<th>Nil-Rated Value</th>
							<th>Taxable Value</th>
							</tr>

							<?php
							// Grouped by (hsn, rate) so a HSN code shared by both a nil-rated and a
							// taxable-rate product shows as two rows instead of merging their values —
							// each channel query below is filtered by the product's actual gst rate,
							// same split used in the filing summary table above (see gst_details.php).
							$select_hsnwise_total="SELECT hsn, gst FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) GROUP BY hsn, gst ORDER BY hsn ASC, gst ASC";
							$fetch_hsnwise_total=mysqli_query($db_conn,$select_hsnwise_total);
							$HSN_grand_nil = 0; $HSN_grand_taxable = 0;
							while($result_hsnwise_total=mysqli_fetch_array($fetch_hsnwise_total)){

								$hsn_code=$result_hsnwise_total['hsn'];
								$hsn_rate=(float)$result_hsnwise_total['gst'];
								$rate_filter = $hsn_rate == 0 ? "=0" : ">0";

								//Total sls qty + taxable value (total - gstamount_total strips embedded
								//GST from 'inclusive'-priced products; see gst_details.php for the same fix)
								$Total_HSN_sls="select sum(qty), sum(total-gstamount_total) from user_invoice_items where hsn=? and gst_percentage$rate_filter and date between ? and ? and from_user_type=? and from_user_id=?";
								$fetch_HSN_sls=db_q($db_conn,$Total_HSN_sls,[$hsn_code,$from_date,$to_date,$Login_user_TYPEvl,$get_godown_id]);
								$result_HSN_sls=mysqli_fetch_array($fetch_HSN_sls);
								$show_HSN_sls_qty = $result_HSN_sls[0]!=NULL ? $result_HSN_sls[0] : 0;
								$show_HSN_sls_val = $result_HSN_sls[1]!=NULL ? $result_HSN_sls[1] : 0;

								//Total customer sls qty + taxable value
								$Total_HSN_cust="select sum(qty), sum(total-gstamount_total) from invoice_items where hsn=? and gst_percentage$rate_filter and date between ? and ? and user_type=? and user_id=?";
								$fetch_HSN_cust=db_q($db_conn,$Total_HSN_cust,[$hsn_code,$from_date,$to_date,$Login_user_TYPEvl,$get_godown_id]);
								$result_HSN_cust=mysqli_fetch_array($fetch_HSN_cust);
								$show_HSN_cust_qty = $result_HSN_cust[0]!=NULL ? $result_HSN_cust[0] : 0;
								$show_HSN_cust_val = $result_HSN_cust[1]!=NULL ? $result_HSN_cust[1] : 0;

								//Total sls return qty + taxable value
								$Total_HSN_sls_rtn="select sum(qty), sum(total-gstamount_total) from user_return_stock_items where hsn=? and gst_percentage$rate_filter and date between ? and ? and to_usertype=? and to_userid=?";
								$fetch_HSN_sls_rtn=db_q($db_conn,$Total_HSN_sls_rtn,[$hsn_code,$from_date,$to_date,$Login_user_TYPEvl,$get_godown_id]);
								$result_HSN_sls_rtn=mysqli_fetch_array($fetch_HSN_sls_rtn);
								$show_HSN_sls_rtn_qty = $result_HSN_sls_rtn[0]!=NULL ? $result_HSN_sls_rtn[0] : 0;
								$show_HSN_sls_rtn_val = $result_HSN_sls_rtn[1]!=NULL ? $result_HSN_sls_rtn[1] : 0;

								$Net_HSN_sls_qty=($show_HSN_sls_qty+$show_HSN_cust_qty)-$show_HSN_sls_rtn_qty;
								$Net_HSN_sls_val=($show_HSN_sls_val+$show_HSN_cust_val)-$show_HSN_sls_rtn_val;


								//Total OT sls qty + taxable value
								$Total_HSN_slsOT="select sum(qty), sum(total-gst_amount) from ot_sales where hsn=? and gst$rate_filter and date between ? and ? and godownid=?";
								$fetch_HSN_slsOT=db_q($db_conn,$Total_HSN_slsOT,[$hsn_code,$from_date,$to_date,$get_godown_id]);
								$result_HSN_slsOT=mysqli_fetch_array($fetch_HSN_slsOT);
								$show_HSN_sls_qty_OT = $result_HSN_slsOT[0]!=NULL ? $result_HSN_slsOT[0] : 0;
								$show_HSN_sls_val_OT = $result_HSN_slsOT[1]!=NULL ? $result_HSN_slsOT[1] : 0;

								//Total OT sls return qty (ot_sales_return has no rate column, joins products via prid)
								$Total_HSN_slsOT_rtn="select sum(osr.qty), sum(osr.total) from ot_sales_return osr join products p on p.id=osr.prid where osr.return_date between ? and ? and osr.godownid=? and osr.hsn=? and p.gst$rate_filter";
								$fetch_HSN_slsOT_rtn=db_q($db_conn,$Total_HSN_slsOT_rtn,[$from_date,$to_date,$get_godown_id,$hsn_code]);
								$result_HSN_slsOT_rtn=mysqli_fetch_array($fetch_HSN_slsOT_rtn);
								$show_HSN_sls_qty_OT_rtn = $result_HSN_slsOT_rtn[0]!=NULL ? $result_HSN_slsOT_rtn[0] : 0;
								$show_HSN_sls_val_OT_rtn = $result_HSN_slsOT_rtn[1]!=NULL ? $result_HSN_slsOT_rtn[1] : 0;

								$Net_HSN_sls_qty_OT=$show_HSN_sls_qty_OT-$show_HSN_sls_qty_OT_rtn;
								$Net_HSN_sls_val_OT=$show_HSN_sls_val_OT-$show_HSN_sls_val_OT_rtn;

								//Total Internal Transfer sls qty + taxable value (stored taxable_value
								//column is unreliable/unpopulated, so total-gst_amount is used instead)
								$Total_HSN_sls_inter="select sum(qty), sum(total-gst_amount) from internal_transfer where hsn=? and gst$rate_filter and date between ? and ? and send_from=?";
								$fetch_HSN_sls_inter=db_q($db_conn,$Total_HSN_sls_inter,[$hsn_code,$from_date,$to_date,$get_godown_id]);
								$result_HSN_sls_inter=mysqli_fetch_array($fetch_HSN_sls_inter);
								$show_HSN_sls_qty_inter = $result_HSN_sls_inter[0]!=NULL ? $result_HSN_sls_inter[0] : 0;
								$show_HSN_sls_val_inter = $result_HSN_sls_inter[1]!=NULL ? $result_HSN_sls_inter[1] : 0;

								//Total TP sls qty + taxable value (company -> territory partner
								//transfers, godown-sourced only) — tpii.amount is the gross line total,
								//so the same inclusive/exclusive-aware helper used elsewhere is reused.
								require_once __DIR__ . '/include/TpGstHelper.php';
								$Total_HSN_sls_TP="select tpii.quantity, tpii.amount, p.gst as gst_percentage, p.gst_type as product_gst_type from tp_invoices tpi join tp_invoice_items tpii on tpii.tp_invoice_id=tpi.id join products p on p.id=tpii.product_id where p.hsn=? and p.gst$rate_filter and tpi.invoice_date between ? and ? and tpi.source_godown_id=?";
								$fetch_HSN_sls_TP=db_q($db_conn,$Total_HSN_sls_TP,[$hsn_code,$from_date,$to_date,$get_godown_id]);
								$show_HSN_sls_qty_TP=0; $show_HSN_sls_val_TP=0;
								while($row_HSN_sls_TP=mysqli_fetch_array($fetch_HSN_sls_TP)){
									$show_HSN_sls_qty_TP += (int)$row_HSN_sls_TP['quantity'];
									[$tp_taxable, ] = tp_line_taxable_and_gst((float)$row_HSN_sls_TP['amount'], $row_HSN_sls_TP['gst_percentage'], $row_HSN_sls_TP['product_gst_type']);
									$show_HSN_sls_val_TP += $tp_taxable;
								}

								$overall_HSN_sls_qty=$Net_HSN_sls_qty+$Net_HSN_sls_qty_OT+$show_HSN_sls_qty_inter+$show_HSN_sls_qty_TP;
								$overall_HSN_sls_val=$Net_HSN_sls_val+$Net_HSN_sls_val_OT+$show_HSN_sls_val_inter+$show_HSN_sls_val_TP;

								if ($overall_HSN_sls_qty == 0 && $overall_HSN_sls_val == 0) continue;

								if ($hsn_rate == 0) { $HSN_grand_nil += $overall_HSN_sls_val; }
								else { $HSN_grand_taxable += $overall_HSN_sls_val; }
								?>

							<tr>
							<td style="text-align:left;"><?=$hsn_code;?></td>
							<td style="text-align:left;"><?=$hsn_rate > 0 ? $hsn_rate.'%' : 'Nil';?></td>
							<td style="text-align:left;">PAC-PACKS</td>
							<td style="text-align:left;"><?=$overall_HSN_sls_qty;?></td>
							<td style="text-align:left;"><?=$hsn_rate == 0 ? inr_format($overall_HSN_sls_val, 2) : '';?></td>
							<td style="text-align:left;"><?=$hsn_rate > 0 ? inr_format($overall_HSN_sls_val, 2) : '';?></td>
							</tr>

							<?php }?>
							<tr>
							<td colspan="4" style="text-align:right;"><b>Total</b></td>
							<td><b><?=inr_format($HSN_grand_nil, 2);?></b></td>
							<td><b><?=inr_format($HSN_grand_taxable, 2);?></b></td>
							</tr>
							</table>


							<!-------------HSN-wise B2B / B2C split (rated + nil-rated, with tax split)---------->
							<br/>
							<div align="right">
							<a href="export_GSTR1_hsn?frdate=<?=urlencode($from_date);?>&todate=<?=urlencode($to_date);?>&godown_id=<?=urlencode($get_godown_id);?>" title="Export HSN-wise B2B / B2C (CSV)"><b>Export HSN B2B / B2C (CSV)</b></a>
							 &nbsp;|&nbsp; <a href="export_GSTR1_json?frdate=<?=urlencode($from_date);?>&todate=<?=urlencode($to_date);?>&godown_id=<?=urlencode($get_godown_id);?>" title="Export GSTR-1 JSON"><b>Export GSTR-1 JSON</b></a>
							</div>
							<h3>Table 4 &amp; 7 — Supplies B2B vs B2C, HSN-wise (Nil-rated included)</h3>
							<?php
							require_once __DIR__ . '/include/TpGstHelper.php';
							require_once __DIR__ . '/include/HsnB2bB2cHelper.php';
							$hsn_split_rows = compute_hsn_b2b_b2c($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, $tp_sls_lines ?? []);
							$hsn_split_tot = ['b2b' => ['qty'=>0,'taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0], 'b2c' => ['qty'=>0,'taxable'=>0,'cgst'=>0,'sgst'=>0,'igst'=>0]];
							?>
							<table id="gsttablevl" style="height:auto;">
							<tr>
							<th rowspan="2">HSN</th>
							<th rowspan="2">GST Rate</th>
							<th rowspan="2">UQC</th>
							<th colspan="5">B2B (Registered)</th>
							<th colspan="5">B2C (Unregistered)</th>
							</tr>
							<tr>
							<th>Qty</th><th>Taxable Value</th><th>CGST</th><th>SGST</th><th>IGST</th>
							<th>Qty</th><th>Taxable Value</th><th>CGST</th><th>SGST</th><th>IGST</th>
							</tr>
							<?php foreach ($hsn_split_rows as $hr) {
								foreach (['b2b','b2c'] as $bk) { foreach ($hsn_split_tot[$bk] as $fk => $fv) { $hsn_split_tot[$bk][$fk] += $hr[$bk][$fk]; } }
								?>
							<tr>
							<td style="text-align:left;"><?=htmlspecialchars($hr['hsn']);?></td>
							<td style="text-align:left;"><?=$hr['rate'] > 0 ? $hr['rate'].'%' : 'Nil';?></td>
							<td style="text-align:left;"><?=$hr['uqc'];?></td>
							<td><?=$hr['b2b']['qty'];?></td><td><?=inr_format($hr['b2b']['taxable'], 2);?></td><td><?=inr_format($hr['b2b']['cgst'], 2);?></td><td><?=inr_format($hr['b2b']['sgst'], 2);?></td><td><?=inr_format($hr['b2b']['igst'], 2);?></td>
							<td><?=$hr['b2c']['qty'];?></td><td><?=inr_format($hr['b2c']['taxable'], 2);?></td><td><?=inr_format($hr['b2c']['cgst'], 2);?></td><td><?=inr_format($hr['b2c']['sgst'], 2);?></td><td><?=inr_format($hr['b2c']['igst'], 2);?></td>
							</tr>
							<?php }?>
							<tr>
							<td colspan="3" style="text-align:right;"><b>Total</b></td>
							<td><b><?=$hsn_split_tot['b2b']['qty'];?></b></td><td><b><?=inr_format($hsn_split_tot['b2b']['taxable'], 2);?></b></td><td><b><?=inr_format($hsn_split_tot['b2b']['cgst'], 2);?></b></td><td><b><?=inr_format($hsn_split_tot['b2b']['sgst'], 2);?></b></td><td><b><?=inr_format($hsn_split_tot['b2b']['igst'], 2);?></b></td>
							<td><b><?=$hsn_split_tot['b2c']['qty'];?></b></td><td><b><?=inr_format($hsn_split_tot['b2c']['taxable'], 2);?></b></td><td><b><?=inr_format($hsn_split_tot['b2c']['cgst'], 2);?></b></td><td><b><?=inr_format($hsn_split_tot['b2c']['sgst'], 2);?></b></td><td><b><?=inr_format($hsn_split_tot['b2c']['igst'], 2);?></b></td>
							</tr>
							</table>


							<!-------------B2B Buyer-Wise Summary (total value, click to expand per-buyer detail)---------->
							<br/>
							<h3>Table 4 — B2B Invoices, Buyer-Wise Summary</h3>
							<?php
							require_once __DIR__ . '/include/TpGstHelper.php';
							require_once __DIR__ . '/include/B2bBuyerHelper.php';
							$b2b_buyers = compute_b2b_buyers($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, $tp_sls_lines ?? []);
							$b2b_grand_taxable = 0; $b2b_grand_cgst = 0; $b2b_grand_sgst = 0; $b2b_grand_igst = 0; $b2b_buyer_count = count($b2b_buyers);
							foreach ($b2b_buyers as $b) {
								$b2b_grand_taxable += $b['taxable']; $b2b_grand_cgst += $b['cgst']; $b2b_grand_sgst += $b['sgst']; $b2b_grand_igst += $b['igst'];
							}
							?>
							<table id="gsttablevl" style="height:auto;">
							<tr>
							<th>Description</th>
							<th>B2B Buyers</th>
							<th>Taxable Value</th>
							<th>CGST</th>
							<th>SGST</th>
							<th>IGST</th>
							</tr>
							<tr>
							<td style="text-align:left;"><a href="gst_b2b_buyer_report?frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><b>Total B2B Supplies (view buyer-wise detail)</b></a></td>
							<td style="text-align:left;"><?=$b2b_buyer_count;?></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_taxable, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_cgst, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_sgst, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_igst, 2);?></b></td>
							</tr>
							</table>


							<!-------------Table 7 - B2C (Others): unregistered-buyer supplies with tax split---------->
							<br/>
							<h3>Table 7 — B2C (Others), Tax Summary</h3>
							<?php
							require_once __DIR__ . '/include/B2cInvoiceHelper.php';
							$b2c_totals = compute_b2c_totals($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, $tp_sls_lines ?? [], $tp_credit_lines ?? []);
							?>
							<table id="gsttablevl" style="height:auto;">
							<tr>
							<th>Description</th>
							<th>Taxable Value</th>
							<th>CGST</th>
							<th>SGST</th>
							<th>IGST</th>
							</tr>
							<tr>
							<td style="text-align:left;"><a href="gst_b2c_invoice_report?frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><b>Total B2C (Others) Supplies (view invoice-wise detail)</b></a></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_totals['taxable'], 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_totals['cgst'], 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_totals['sgst'], 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_totals['igst'], 2);?></b></td>
							</tr>
							</table>


							<!-------------Documents Issued During the Tax Period---------->
							<br/>
							<h3>Table 13 — Documents Issued During the Tax Period (Registered / Unregistered)</h3>
							<table id="gsttablevl" style="height:auto;">
							<tr>
							<th>Channel</th>
							<th>Buyer Type</th>
							<th>Document Series</th>
							<th>Series From</th>
							<th>Series To</th>
							<th>Total Issued</th>
							</tr>
							<?php
							// Split by buyer GST registration (Registered = buyer has a GSTIN on file /
							// buyer_gsttype='register'; everything else = Unregistered), then by the
							// invoice number's own leading series code ("C" vs "CD", "IN" vs "WEB" ...),
							// so a merged Series From/To never spans two different series. An invoice
							// counts once, as Registered if any of its lines is a registered-buyer line.
							require_once __DIR__ . '/include/TpGstHelper.php';
							function doc_series_code($inv_number) {
								$inv_number = trim($inv_number);
								if (preg_match('/^([A-Za-z]+)/', $inv_number, $m)) return strtoupper($m[1]);
								return 'OTHER';
							}
							$doc_channels = [
								['label' => 'Network Sale (SS/ST/DT/Shop)', 'sql' => "select ui.inv_number as num, ui.date as d, (select max(x.buyer_gsttype='register') from user_invoice_items x where x.inv_id=ui.inv_id) as reg from user_invoice ui where ui.from_user_type='$Login_user_TYPEvl' and ui.from_user_id='".db_esc($get_godown_id)."' and ui.date between '$from_date' and '$to_date' order by ui.date asc, ui.inv_number asc"],
								['label' => 'Customer Sale', 'sql' => "select i.inv_number as num, i.date as d, (select max(x.buyer_gsttype='register') from invoice_items x where x.inv_id=i.inv_id) as reg from invoice i where i.user_type='$Login_user_TYPEvl' and i.user_id='".db_esc($get_godown_id)."' and i.date between '$from_date' and '$to_date' order by i.date asc, i.inv_number asc"],
								['label' => 'OT Sale', 'sql' => "select i.inv_number as num, min(s.date) as d, max(s.buyer_gsttype='register') as reg from ot_sales_invoice i join ot_sales s on s.tempid=i.tempid where s.godownid='".db_esc($get_godown_id)."' and s.date between '$from_date' and '$to_date' group by i.inv_number order by d asc, i.inv_number asc"],
								['label' => 'Territory Partner', 'sql' => "select tpi.invoice_number as num, tpi.invoice_date as d, tp.gstin as tp_gstin from tp_invoices tpi left join territory_partners tp on tp.id=tpi.territory_partner_id where tpi.source_godown_id='".db_esc($get_godown_id)."' and tpi.invoice_date between '$from_date' and '$to_date' order by tpi.invoice_date asc, tpi.invoice_number asc", 'tp' => true],
							];
							$doc_grand_total = 0;
							foreach ($doc_channels as $dc) {
								$dres = mysqli_query($db_conn, $dc['sql']);
								$groups = ['Registered' => [], 'Unregistered' => []]; // type => series => [numbers]
								while ($drow = mysqli_fetch_assoc($dres)) {
									$is_reg = !empty($dc['tp']) ? tp_gstin_is_valid($drow['tp_gstin']) : ((int)$drow['reg'] === 1);
									$groups[$is_reg ? 'Registered' : 'Unregistered'][doc_series_code($drow['num'])][] = $drow['num'];
								}
								foreach ($groups as $btype => $series_groups) {
									ksort($series_groups);
									foreach ($series_groups as $series_code => $nums) {
										$count = count($nums);
										if ($count == 0) continue;
										$doc_grand_total += $count;
										?>
							<tr>
							<td style="text-align:left;"><?=htmlspecialchars($dc['label']);?></td>
							<td style="text-align:left;"><?=$btype;?></td>
							<td style="text-align:left;"><?=htmlspecialchars($series_code);?></td>
							<td style="text-align:left;"><?=htmlspecialchars($nums[0]);?></td>
							<td style="text-align:left;"><?=htmlspecialchars($nums[$count-1]);?></td>
							<td style="text-align:left;"><?=$count;?></td>
							</tr>
							<?php }
								}
							}?>
							<tr>
							<td colspan="5" style="text-align:right;"><b>Total</b></td>
							<td><b><?=$doc_grand_total;?></b></td>
							</tr>
							</table>

							<!-------------------------------------------------->
							<!-------------------------------------------------->
							<!-------------------------------------------------->
						   <?php }?>
							
                        </div>
						
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>