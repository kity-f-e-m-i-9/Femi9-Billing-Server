<?php 
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
$select_Godown_details="select * from company_godown where id='$get_godown_id'";
$fetch_Godown_details=mysqli_query($db_conn,$select_Godown_details);
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
							<th>Total Quantity</th>
							<th>Nil-Rated Value</th>
							<th>Taxable Value</th>
							</tr>

							<?php
							// Grouped by (hsn, rate) so a HSN code shared by both a nil-rated and a
							// taxable-rate product shows as two rows instead of merging their values —
							// each channel query below is filtered by the product's actual gst rate,
							// same split used in the filing summary table above (see gst_details.php).
							$select_hsnwise_total="SELECT DISTINCT hsn, gst FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY hsn ASC, gst ASC";
							$fetch_hsnwise_total=mysqli_query($db_conn,$select_hsnwise_total);
							$HSN_grand_nil = 0; $HSN_grand_taxable = 0;
							while($result_hsnwise_total=mysqli_fetch_array($fetch_hsnwise_total)){

								$hsn_code=$result_hsnwise_total['hsn'];
								$hsn_rate=(float)$result_hsnwise_total['gst'];
								$rate_filter = $hsn_rate == 0 ? "=0" : ">0";

								//Total sls qty + taxable value (total - gstamount_total strips embedded
								//GST from 'inclusive'-priced products; see gst_details.php for the same fix)
								$Total_HSN_sls="select sum(qty), sum(total-gstamount_total) from user_invoice_items where hsn='$hsn_code' and gst_percentage$rate_filter and date between '$from_date' and '$to_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id'";
								$fetch_HSN_sls=mysqli_query($db_conn,$Total_HSN_sls);
								$result_HSN_sls=mysqli_fetch_array($fetch_HSN_sls);
								$show_HSN_sls_qty = $result_HSN_sls[0]!=NULL ? $result_HSN_sls[0] : 0;
								$show_HSN_sls_val = $result_HSN_sls[1]!=NULL ? $result_HSN_sls[1] : 0;

								//Total customer sls qty + taxable value
								$Total_HSN_cust="select sum(qty), sum(total-gstamount_total) from invoice_items where hsn='$hsn_code' and gst_percentage$rate_filter and date between '$from_date' and '$to_date' and user_type='$Login_user_TYPEvl' and user_id='$get_godown_id'";
								$fetch_HSN_cust=mysqli_query($db_conn,$Total_HSN_cust);
								$result_HSN_cust=mysqli_fetch_array($fetch_HSN_cust);
								$show_HSN_cust_qty = $result_HSN_cust[0]!=NULL ? $result_HSN_cust[0] : 0;
								$show_HSN_cust_val = $result_HSN_cust[1]!=NULL ? $result_HSN_cust[1] : 0;

								//Total sls return qty + taxable value
								$Total_HSN_sls_rtn="select sum(qty), sum(total-gstamount_total) from user_return_stock_items where hsn='$hsn_code' and gst_percentage$rate_filter and date between '$from_date' and '$to_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id'";
								$fetch_HSN_sls_rtn=mysqli_query($db_conn,$Total_HSN_sls_rtn);
								$result_HSN_sls_rtn=mysqli_fetch_array($fetch_HSN_sls_rtn);
								$show_HSN_sls_rtn_qty = $result_HSN_sls_rtn[0]!=NULL ? $result_HSN_sls_rtn[0] : 0;
								$show_HSN_sls_rtn_val = $result_HSN_sls_rtn[1]!=NULL ? $result_HSN_sls_rtn[1] : 0;

								$Net_HSN_sls_qty=($show_HSN_sls_qty+$show_HSN_cust_qty)-$show_HSN_sls_rtn_qty;
								$Net_HSN_sls_val=($show_HSN_sls_val+$show_HSN_cust_val)-$show_HSN_sls_rtn_val;


								//Total OT sls qty + taxable value
								$Total_HSN_slsOT="select sum(qty), sum(total-gst_amount) from ot_sales where hsn='$hsn_code' and gst$rate_filter and date between '$from_date' and '$to_date' and godownid='$get_godown_id'";
								$fetch_HSN_slsOT=mysqli_query($db_conn,$Total_HSN_slsOT);
								$result_HSN_slsOT=mysqli_fetch_array($fetch_HSN_slsOT);
								$show_HSN_sls_qty_OT = $result_HSN_slsOT[0]!=NULL ? $result_HSN_slsOT[0] : 0;
								$show_HSN_sls_val_OT = $result_HSN_slsOT[1]!=NULL ? $result_HSN_slsOT[1] : 0;

								//Total OT sls return qty (ot_sales_return has no rate column, joins products via prid)
								$Total_HSN_slsOT_rtn="select sum(osr.qty), sum(osr.total) from ot_sales_return osr join products p on p.id=osr.prid where osr.return_date between '$from_date' and '$to_date' and osr.godownid='$get_godown_id' and osr.hsn='$hsn_code' and p.gst$rate_filter";
								$fetch_HSN_slsOT_rtn=mysqli_query($db_conn,$Total_HSN_slsOT_rtn);
								$result_HSN_slsOT_rtn=mysqli_fetch_array($fetch_HSN_slsOT_rtn);
								$show_HSN_sls_qty_OT_rtn = $result_HSN_slsOT_rtn[0]!=NULL ? $result_HSN_slsOT_rtn[0] : 0;
								$show_HSN_sls_val_OT_rtn = $result_HSN_slsOT_rtn[1]!=NULL ? $result_HSN_slsOT_rtn[1] : 0;

								$Net_HSN_sls_qty_OT=$show_HSN_sls_qty_OT-$show_HSN_sls_qty_OT_rtn;
								$Net_HSN_sls_val_OT=$show_HSN_sls_val_OT-$show_HSN_sls_val_OT_rtn;

								//Total Internal Transfer sls qty + taxable value (stored taxable_value
								//column is unreliable/unpopulated, so total-gst_amount is used instead)
								$Total_HSN_sls_inter="select sum(qty), sum(total-gst_amount) from internal_transfer where hsn='$hsn_code' and gst$rate_filter and date between '$from_date' and '$to_date' and send_from='$get_godown_id'";
								$fetch_HSN_sls_inter=mysqli_query($db_conn,$Total_HSN_sls_inter);
								$result_HSN_sls_inter=mysqli_fetch_array($fetch_HSN_sls_inter);
								$show_HSN_sls_qty_inter = $result_HSN_sls_inter[0]!=NULL ? $result_HSN_sls_inter[0] : 0;
								$show_HSN_sls_val_inter = $result_HSN_sls_inter[1]!=NULL ? $result_HSN_sls_inter[1] : 0;

								//Total TP sls qty + taxable value (company -> territory partner
								//transfers, godown-sourced only) — tpii.amount is the gross line total,
								//so the same inclusive/exclusive-aware helper used elsewhere is reused.
								require_once __DIR__ . '/include/TpGstHelper.php';
								$Total_HSN_sls_TP="select tpii.quantity, tpii.amount, p.gst as gst_percentage, p.gst_type as product_gst_type from tp_invoices tpi join tp_invoice_items tpii on tpii.tp_invoice_id=tpi.id join products p on p.id=tpii.product_id where p.hsn='$hsn_code' and p.gst$rate_filter and tpi.invoice_date between '$from_date' and '$to_date' and tpi.source_godown_id='$get_godown_id'";
								$fetch_HSN_sls_TP=mysqli_query($db_conn,$Total_HSN_sls_TP);
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
							<td style="text-align:left;"><?=$overall_HSN_sls_qty;?></td>
							<td style="text-align:left;"><?=$hsn_rate == 0 ? inr_format($overall_HSN_sls_val, 2) : '';?></td>
							<td style="text-align:left;"><?=$hsn_rate > 0 ? inr_format($overall_HSN_sls_val, 2) : '';?></td>
							</tr>

							<?php }?>
							<tr>
							<td colspan="3" style="text-align:right;"><b>Total</b></td>
							<td><b><?=inr_format($HSN_grand_nil, 2);?></b></td>
							<td><b><?=inr_format($HSN_grand_taxable, 2);?></b></td>
							</tr>
							</table>


							<!-------------HSN-wise B2B / B2C split (rated supplies only)---------->
							<br/>
							<h3>Table 4 &amp; 7 — Rated (Taxable) Supplies, B2B vs B2C, HSN-wise</h3>
							<table id="gsttablevl" style="height:auto;">
							<tr>
							<th>HSN</th>
							<th>GST Rate</th>
							<th>B2B Qty</th>
							<th>B2B Taxable Value</th>
							<th>B2C Qty</th>
							<th>B2C Taxable Value</th>
							</tr>
							<?php
							// B2B = buyer_gsttype='register' (has GSTIN on file), B2C = 'unregister'.
							// Restricted to gst_percentage > 0 rows only — nil-rated goods have no
							// B2B/B2C filing relevance and are covered by the Nil Rated table above.
							$select_hsnwise_rated="SELECT DISTINCT hsn, gst FROM products WHERE gst > 0 AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY hsn ASC, gst ASC";
							$fetch_hsnwise_rated=mysqli_query($db_conn,$select_hsnwise_rated);
							$B2B_grand_val = 0; $B2C_grand_val = 0;
							while($result_hsnwise_rated=mysqli_fetch_array($fetch_hsnwise_rated)){
								$hsn_code=$result_hsnwise_rated['hsn'];
								$hsn_rate=(float)$result_hsnwise_rated['gst'];

								$b2b_qty = 0; $b2b_val = 0; $b2c_qty = 0; $b2c_val = 0;
								foreach (['register' => true, 'unregister' => false] as $bg => $is_b2b) {
									$q = "select sum(qty), sum(total-gstamount_total) from user_invoice_items where hsn='$hsn_code' and gst_percentage>0 and buyer_gsttype='$bg' and date between '$from_date' and '$to_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id'";
									$r = mysqli_fetch_array(mysqli_query($db_conn,$q));
									$qty = (float)($r[0] ?? 0); $val = (float)($r[1] ?? 0);

									$q = "select sum(qty), sum(total-gstamount_total) from invoice_items where hsn='$hsn_code' and gst_percentage>0 and buyer_gsttype='$bg' and date between '$from_date' and '$to_date' and user_type='$Login_user_TYPEvl' and user_id='$get_godown_id'";
									$r = mysqli_fetch_array(mysqli_query($db_conn,$q));
									$qty += (float)($r[0] ?? 0); $val += (float)($r[1] ?? 0);

									$q = "select sum(qty), sum(total-gst_amount) from ot_sales where hsn='$hsn_code' and gst>0 and buyer_gsttype='$bg' and date between '$from_date' and '$to_date' and godownid='$get_godown_id'";
									$r = mysqli_fetch_array(mysqli_query($db_conn,$q));
									$qty += (float)($r[0] ?? 0); $val += (float)($r[1] ?? 0);

									$q = "select sum(qty), sum(total-gstamount_total) from user_return_stock_items where hsn='$hsn_code' and gst_percentage>0 and buyer_gsttype='$bg' and date between '$from_date' and '$to_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id'";
									$r = mysqli_fetch_array(mysqli_query($db_conn,$q));
									$qty -= (float)($r[0] ?? 0); $val -= (float)($r[1] ?? 0);

									$q = "select sum(osr.qty), sum(osr.total) from ot_sales_return osr join products p on p.id=osr.prid where osr.hsn='$hsn_code' and p.gst>0 and osr.buyer_gsttype='$bg' and osr.return_date between '$from_date' and '$to_date' and osr.godownid='$get_godown_id'";
									$r = mysqli_fetch_array(mysqli_query($db_conn,$q));
									$qty -= (float)($r[0] ?? 0); $val -= (float)($r[1] ?? 0);

									require_once __DIR__ . '/include/TpGstHelper.php';
									$q = "select tpii.quantity, tpii.amount, p.gst as gst_percentage, p.gst_type as product_gst_type, tp.gstin as tp_gstin from tp_invoices tpi join tp_invoice_items tpii on tpii.tp_invoice_id=tpi.id join products p on p.id=tpii.product_id join territory_partners tp on tp.id=tpi.territory_partner_id where p.hsn='$hsn_code' and p.gst>0 and tpi.invoice_date between '$from_date' and '$to_date' and tpi.source_godown_id='$get_godown_id'";
									$res = mysqli_query($db_conn,$q);
									while ($tr = mysqli_fetch_assoc($res)) {
										$tp_is_reg = tp_gstin_is_valid($tr['tp_gstin']);
										if (($bg === 'register') !== $tp_is_reg) continue;
										[$tp_taxable, ] = tp_line_taxable_and_gst((float)$tr['amount'], $tr['gst_percentage'], $tr['product_gst_type']);
										$qty += (float)$tr['quantity'];
										$val += $tp_taxable;
									}

									if ($is_b2b) { $b2b_qty = $qty; $b2b_val = $val; }
									else { $b2c_qty = $qty; $b2c_val = $val; }
								}

								if ($b2b_qty == 0 && $b2b_val == 0 && $b2c_qty == 0 && $b2c_val == 0) continue;
								$B2B_grand_val += $b2b_val; $B2C_grand_val += $b2c_val;
								?>
							<tr>
							<td style="text-align:left;"><?=$hsn_code;?></td>
							<td style="text-align:left;"><?=$hsn_rate;?>%</td>
							<td style="text-align:left;"><?=$b2b_qty;?></td>
							<td style="text-align:left;"><?=inr_format($b2b_val, 2);?></td>
							<td style="text-align:left;"><?=$b2c_qty;?></td>
							<td style="text-align:left;"><?=inr_format($b2c_val, 2);?></td>
							</tr>
							<?php }?>
							<tr>
							<td colspan="3" style="text-align:right;"><b>Total</b></td>
							<td><b><?=inr_format($B2B_grand_val, 2);?></b></td>
							<td></td>
							<td><b><?=inr_format($B2C_grand_val, 2);?></b></td>
							</tr>
							</table>


							<!-------------B2B Buyer-Wise Summary (total value, click to expand per-buyer detail)---------->
							<br/>
							<h3>Table 4 — B2B Invoices, Buyer-Wise Summary</h3>
							<?php
							// One row per individual registered (B2B) buyer — SS/ST/DT/Shop/Customer
							// each keyed by temp_id/id, TP keyed by territory_partner_id — across all
							// channels, net of that buyer's own credit notes. B2C (unregistered) buyers
							// are covered in aggregate by the B2B/B2C tables above, not listed individually
							// here since they're typically walk-in/anonymous.
							$b2b_buyers = []; // key => ['name','type','gstin','invoices'=>set,'taxable'=>,'cgst'=>,'sgst'=>,'igst'=>]

							function b2b_buyer_add(&$buyers, $key, $name, $type, $gstin, $inv, $taxable, $is_intra, $gst_amount) {
								if (!isset($buyers[$key])) {
									$buyers[$key] = ['name' => $name, 'type' => $type, 'gstin' => $gstin, 'invoices' => [], 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
								}
								$buyers[$key]['invoices'][$inv] = true;
								$buyers[$key]['taxable'] += $taxable;
								if ($is_intra) { $buyers[$key]['cgst'] += $gst_amount / 2; $buyers[$key]['sgst'] += $gst_amount / 2; }
								else { $buyers[$key]['igst'] += $gst_amount; }
							}

							// Network sales (SS/ST/DT/Shop)
							$q = "
								SELECT uii.to_user_type, uii.to_user_id, ui.inv_number, uii.gst_type,
									   SUM(uii.total-uii.gstamount_total) AS taxable, SUM(uii.gstamount_total) AS gst_amt,
									   COALESCE(ss.name,st.name,dt.name,sh.name) AS bname,
									   COALESCE(ss.gstin,st.gstin,dt.gstin,sh.gstin) AS bgstin
								FROM user_invoice_items uii
								LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
								LEFT JOIN super_stockiest ss ON uii.to_user_type='super_stockiest' AND ss.temp_id=uii.to_user_id
								LEFT JOIN stockiest       st ON uii.to_user_type='stockiest'       AND st.temp_id=uii.to_user_id
								LEFT JOIN distributor     dt ON uii.to_user_type='distributor'     AND dt.temp_id=uii.to_user_id
								LEFT JOIN shop            sh ON uii.to_user_type='shop'            AND sh.temp_id=uii.to_user_id
								WHERE uii.from_user_type='$Login_user_TYPEvl' AND uii.from_user_id='$get_godown_id'
								  AND uii.buyer_gsttype='register' AND uii.date BETWEEN '$from_date' AND '$to_date'
								GROUP BY uii.to_user_type, uii.to_user_id, ui.inv_number, uii.gst_type, ss.name, st.name, dt.name, sh.name, ss.gstin, st.gstin, dt.gstin, sh.gstin
							";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) {
								b2b_buyer_add($b2b_buyers, $r['to_user_type'].'_'.$r['to_user_id'], $r['bname'], ucfirst(str_replace('_',' ',$r['to_user_type'])), $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $r['gst_type']=='inner', (float)$r['gst_amt']);
							}

							// Customer sales
							$q = "
								SELECT ii.customer_id, i.inv_number, ii.gst_type,
									   SUM(ii.total-ii.gstamount_total) AS taxable, SUM(ii.gstamount_total) AS gst_amt,
									   c.name AS bname, c.gstin AS bgstin
								FROM invoice_items ii
								LEFT JOIN invoice i ON i.inv_id = ii.inv_id
								LEFT JOIN customers c ON c.id = ii.customer_id
								WHERE ii.user_type='$Login_user_TYPEvl' AND ii.user_id='$get_godown_id'
								  AND ii.buyer_gsttype='register' AND ii.date BETWEEN '$from_date' AND '$to_date'
								GROUP BY ii.customer_id, i.inv_number, ii.gst_type, c.name, c.gstin
							";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) {
								b2b_buyer_add($b2b_buyers, 'customer_'.$r['customer_id'], $r['bname'], 'Customer', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $r['gst_type']=='inner', (float)$r['gst_amt']);
							}

							// OT sales
							$q = "
								SELECT s.tempid, i.inv_number, s.gst_type, s.customer_name AS bname, s.gst_number AS bgstin,
									   SUM(s.total-s.gst_amount) AS taxable, SUM(s.gst_amount) AS gst_amt
								FROM ot_sales s
								LEFT JOIN ot_sales_invoice i ON i.tempid = s.tempid
								WHERE s.godownid='$get_godown_id' AND s.buyer_gsttype='register' AND s.date BETWEEN '$from_date' AND '$to_date'
								GROUP BY s.tempid, i.inv_number, s.gst_type, s.customer_name, s.gst_number
							";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) {
								b2b_buyer_add($b2b_buyers, 'ot_'.$r['bgstin'].'_'.$r['bname'], $r['bname'], 'OT Sale', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $r['gst_type']=='inner', (float)$r['gst_amt']);
							}

							// TP invoices — reuse the already-computed per-line list from gst_details.php.
							// ($tp_sls_lines and $tp_sls_lines_inter are both the SAME full unfiltered
							// line set for this godown — tp_gst_bucket_totals() does the intra/inter
							// split internally — so only one of the two must be used here, not both.)
							require_once __DIR__ . '/include/TpGstHelper.php';
							foreach ($tp_sls_lines ?? [] as $l) {
								if (!$l['is_registered']) continue;
								b2b_buyer_add($b2b_buyers, 'tp_'.$l['tp_invoice_id'], $l['tp_name'], 'Territory Partner', $l['tp_gstin'], $l['invoice_number'], $l['taxable_value'], $l['is_intra'], $l['gst_amount']);
							}

							// Internal transfers (company godown -> company godown, e.g. Neksomo ->
							// Health Care -> LLP). Each company_godown has its own distinct GSTIN, so
							// a transfer between them is a real B2B outward supply, not a same-entity
							// internal movement — the receiving godown is the "buyer" here. This is
							// the dominant (often only) transaction type for finance_only godowns
							// like Health Care/Neksomo, which otherwise have no external B2B buyers
							// and would show an empty Table 4 despite real GST-relevant activity.
							// (No internal-transfer credit-note/return concept exists in this system —
							// same as the "Total Internal Transfer Sales" row above, which has no
							// credit-note counterpart either — so no return-netting step is needed.)
							$q = "
								SELECT it.send_to, cg.gname AS bname, cg.gstin AS bgstin, cg.state AS to_state, it_from.state AS from_state,
									   COALESCE(iti.inv_number, it.tempid) AS inv_number, it.gst_type,
									   SUM(it.total - it.gst_amount) AS taxable, SUM(it.gst_amount) AS gst_amt
								FROM internal_transfer it
								LEFT JOIN internal_transfer_invoice iti ON iti.tempid = it.tempid
								JOIN company_godown cg ON cg.id = it.send_to
								JOIN company_godown it_from ON it_from.id = it.send_from
								WHERE it.send_from='$get_godown_id' AND it.date BETWEEN '$from_date' AND '$to_date'
								GROUP BY it.send_to, cg.gname, cg.gstin, cg.state, it_from.state, COALESCE(iti.inv_number, it.tempid), it.gst_type
							";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) {
								$is_intra = strtolower(trim($r['to_state'])) == strtolower(trim($r['from_state']));
								b2b_buyer_add($b2b_buyers, 'godown_'.$r['send_to'], $r['bname'], 'Internal Transfer', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $is_intra, (float)$r['gst_amt']);
							}

							// Net out each buyer's own registered-person credit notes (returns), matched by
							// buyer identity where the return table carries it directly.
							$q = "
								SELECT rsi.from_usertype, rsi.from_userid, rsi.gst_type,
									   SUM(rsi.total-rsi.gstamount_total) AS taxable, SUM(rsi.gstamount_total) AS gst_amt
								FROM user_return_stock_items rsi
								WHERE rsi.to_usertype='$Login_user_TYPEvl' AND rsi.to_userid='$get_godown_id'
								  AND rsi.buyer_gsttype='register' AND rsi.from_usertype != 'customer'
								  AND rsi.date BETWEEN '$from_date' AND '$to_date'
								GROUP BY rsi.from_usertype, rsi.from_userid, rsi.gst_type
							";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) {
								$key = $r['from_usertype'].'_'.$r['from_userid'];
								if (!isset($b2b_buyers[$key])) continue;
								$b2b_buyers[$key]['taxable'] -= (float)$r['taxable'];
								if ($r['gst_type']=='inner') { $b2b_buyers[$key]['cgst'] -= (float)$r['gst_amt']/2; $b2b_buyers[$key]['sgst'] -= (float)$r['gst_amt']/2; }
								else { $b2b_buyers[$key]['igst'] -= (float)$r['gst_amt']; }
							}

							usort($b2b_buyers, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
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
							<tr id="b2bSummaryRow" onclick="document.getElementById('b2bDetailWrap').style.display = document.getElementById('b2bDetailWrap').style.display === 'none' ? '' : 'none';" style="cursor:pointer;">
							<td style="text-align:left;"><a href="javascript:void(0);"><b>Total B2B Supplies (click to view buyer-wise detail)</b></a></td>
							<td style="text-align:left;"><?=$b2b_buyer_count;?></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_taxable, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_cgst, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_sgst, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2b_grand_igst, 2);?></b></td>
							</tr>
							</table>

							<div id="b2bDetailWrap" style="display:none;">
							<table id="gsttablevl" style="height:auto;">
							<tr>
							<th>Buyer</th>
							<th>Type</th>
							<th>GSTIN</th>
							<th>Invoices</th>
							<th>Taxable Value</th>
							<th>CGST</th>
							<th>SGST</th>
							<th>IGST</th>
							</tr>
							<?php if (empty($b2b_buyers)) { ?>
							<tr><td colspan="8" style="text-align:center;">No B2B buyers in this period.</td></tr>
							<?php } else {
							foreach ($b2b_buyers as $b) { ?>
							<tr>
							<td style="text-align:left;"><?=htmlspecialchars($b['name'] ?: '—');?></td>
							<td style="text-align:left;"><?=htmlspecialchars($b['type']);?></td>
							<td style="text-align:left;"><?=htmlspecialchars($b['gstin'] ?: '—');?></td>
							<td style="text-align:left;"><?=count($b['invoices']);?></td>
							<td style="text-align:left;"><?=inr_format($b['taxable'], 2);?></td>
							<td style="text-align:left;"><?=inr_format($b['cgst'], 2);?></td>
							<td style="text-align:left;"><?=inr_format($b['sgst'], 2);?></td>
							<td style="text-align:left;"><?=inr_format($b['igst'], 2);?></td>
							</tr>
							<?php } } ?>
							</table>
							</div>


							<!-------------Table 7 - B2C (Others): unregistered-buyer supplies with tax split---------->
							<br/>
							<h3>Table 7 — B2C (Others), Tax Summary</h3>
							<?php
							// Same CGST/SGST/IGST derivation as the B2B table above (gst_type='inner'
							// -> intra -> split evenly into CGST+SGST; otherwise inter -> IGST), but
							// aggregated only (no per-buyer listing — B2C buyers are typically
							// walk-in/anonymous, consistent with the existing B2B table's own note).
							// Net of B2C credit notes (rsi.buyer_gsttype='unregister' or blank/NULL,
							// same fallback used in gst_details_credit.php).
							$b2c_taxable = 0; $b2c_cgst = 0; $b2c_sgst = 0; $b2c_igst = 0;

							$b2c_add = function($is_intra, $taxable, $gst_amt) use (&$b2c_taxable, &$b2c_cgst, &$b2c_sgst, &$b2c_igst) {
								$b2c_taxable += $taxable;
								if ($is_intra) { $b2c_cgst += $gst_amt / 2; $b2c_sgst += $gst_amt / 2; }
								else { $b2c_igst += $gst_amt; }
							};

							$q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='unregister' and date between '$from_date' and '$to_date' group by gst_type";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) { $b2c_add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

							$q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='unregister' and date between '$from_date' and '$to_date' group by gst_type";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) { $b2c_add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

							$q = "select gst_type, sum(total-gst_amount) as taxable, sum(gst_amount) as gst_amt from ot_sales where godownid='$get_godown_id' and buyer_gsttype='unregister' and date between '$from_date' and '$to_date' group by gst_type";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) { $b2c_add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

							foreach ($tp_sls_lines ?? [] as $l) {
								if ($l['is_registered']) continue;
								$b2c_add($l['is_intra'], $l['taxable_value'], $l['gst_amount']);
							}

							// Net out B2C credit notes (returns) — blank/NULL buyer_gsttype defaults to
							// unregister here too, matching gst_details_credit.php's own convention.
							$q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and date between '$from_date' and '$to_date' group by gst_type";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) { $b2c_add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), -(float)$r['taxable'], -(float)$r['gst_amt']); }

							$q = "select gst_type, sum(total) as taxable from ot_sales_return where godownid='$get_godown_id' and buyer_gsttype='unregister' and return_date between '$from_date' and '$to_date' group by gst_type";
							$res = mysqli_query($db_conn, $q);
							while ($r = mysqli_fetch_assoc($res)) { $b2c_add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), -(float)$r['taxable'], 0); }

							foreach ($tp_credit_lines ?? [] as $l) {
								if ($l['is_registered']) continue;
								$b2c_add($l['is_intra'], -$l['taxable_value'], -$l['gst_amount']);
							}
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
							<td style="text-align:left;"><b>Total B2C (Others) Supplies</b></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_taxable, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_cgst, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_sgst, 2);?></b></td>
							<td style="text-align:left;"><b><?=inr_format($b2c_igst, 2);?></b></td>
							</tr>
							</table>


							<!-------------Documents Issued During the Tax Period---------->
							<br/>
							<h3>Table 13 — Documents Issued During the Tax Period</h3>
							<table id="gsttablevl" style="height:auto;">
							<tr>
							<th>Channel</th>
							<th>Document Series</th>
							<th>Series From</th>
							<th>Series To</th>
							<th>Total Issued</th>
							</tr>
							<?php
							// Split by the invoice number's own leading series code (its run of
							// letters — "C" vs "CD", "IN" vs "WEB" vs "LWAA", "S" vs "SS" vs "SH",
							// etc.), not by channel/buyer-type — a channel can carry more than one
							// series (e.g. Customer Sale mixes "C..." and "CD..." numbers; OT Sale
							// mixes "IN-...", "WEB/..." and bare "LWAA..." numbers with no
							// separator at all), and a merged Series From/To across two different
							// series would be meaningless.
							function doc_series_code($inv_number) {
								$inv_number = trim($inv_number);
								if (preg_match('/^([A-Za-z]+)/', $inv_number, $m)) return strtoupper($m[1]);
								return 'OTHER';
							}
							$doc_channels = [
								['label' => 'Network Sale (SS/ST/DT/Shop)', 'table' => 'user_invoice', 'num_col' => 'inv_number', 'date_col' => 'date', 'where' => "from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id'"],
								['label' => 'Customer Sale', 'table' => 'invoice', 'num_col' => 'inv_number', 'date_col' => 'date', 'where' => "user_type='$Login_user_TYPEvl' and user_id='$get_godown_id'"],
								['label' => 'OT Sale', 'table' => 'ot_sales_invoice i join ot_sales s on s.tempid=i.tempid', 'num_col' => 'i.inv_number', 'date_col' => 's.date', 'where' => "s.godownid='$get_godown_id'", 'distinct' => 'i.tempid'],
								['label' => 'Territory Partner', 'table' => 'tp_invoices', 'num_col' => 'invoice_number', 'date_col' => 'invoice_date', 'where' => "source_godown_id='$get_godown_id'"],
							];
							$doc_grand_total = 0;
							foreach ($doc_channels as $dc) {
								$dq = "select {$dc['num_col']} as num from {$dc['table']} where {$dc['where']} and {$dc['date_col']} between '$from_date' and '$to_date' order by {$dc['date_col']} asc, {$dc['num_col']} asc";
								$dres = mysqli_query($db_conn, $dq);
								$series_groups = []; // series code => [invoice numbers in date/number order]
								while ($drow = mysqli_fetch_assoc($dres)) {
									$series_groups[doc_series_code($drow['num'])][] = $drow['num'];
								}
								ksort($series_groups);
								foreach ($series_groups as $series_code => $nums) {
									$count = count($nums);
									if ($count == 0) continue;
									$doc_grand_total += $count;
									?>
							<tr>
							<td style="text-align:left;"><?=htmlspecialchars($dc['label']);?></td>
							<td style="text-align:left;"><?=htmlspecialchars($series_code);?></td>
							<td style="text-align:left;"><?=htmlspecialchars($nums[0]);?></td>
							<td style="text-align:left;"><?=htmlspecialchars($nums[$count-1]);?></td>
							<td style="text-align:left;"><?=$count;?></td>
							</tr>
							<?php }
							}?>
							<tr>
							<td colspan="4" style="text-align:right;"><b>Total</b></td>
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