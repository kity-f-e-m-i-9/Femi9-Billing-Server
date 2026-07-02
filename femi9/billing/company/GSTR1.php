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
							   <?php }else{?>
							   <option value="<?=$get_godown_id;?>" hidden=""><?=$result_Godown_details['gname'];?></option>
							   <?php }?>
							   <?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						  <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
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
						   $Total_sls_register_intra=$total_intra_register+$total_intra_register2;
						   $Total_sls_unregister_intra=$total_intra_unregister+$total_intra_unregister2;
						   ?>
						   
						   <tr>
						   <th>Total Sales (SS,ST, DT, SHP, CUS)</th>
						   <td><a href="gst_sls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($Total_sls_register_intra,2);?></a></td>
						   <td><a href="gst_sls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($Total_sls_unregister_intra,2);?></a></td>
						   </tr>
						   <tr>
						   <th>Total OT Sales</th>
						   <td><a href="gst_otsls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($total_reg_OTSLS_intra,2);?></a></td>
						   <td><a href="gst_otsls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($total_reg_OTSLSUN_intra,2);?></a></td>
						   </tr>
						    <tr>
						   <th>Total Internal Transfer Sales</th>
						    <td><a href="gst_intrsls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($total_reg_INTR,2);?></a></td>
							 <td>0.00</td>
						   </tr>
						   <?php 
						   $Total_intra_register_sales=$total_intra_register+$total_intra_register2+$total_reg_OTSLS_intra+$total_reg_INTR;
						   
						   $Total_intra_unregister_sales=$total_intra_unregister+$total_intra_unregister2+$total_reg_OTSLSUN_intra;
						   ?>
						   <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=number_format($Total_intra_register_sales,2);?></b></td>
							<td><b><?=number_format($Total_intra_unregister_sales,2);?></b></td>
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
						   <tr>
						   <th>Sales Return<br/>(SS,ST, DT, SHP, CUS)</th>
						   <td><a href="gst_credit_sls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=number_format($total_intra_register_credit,2);?></a>
						   </td>
						   <td>
						   <a href="gst_credit_sls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=number_format($total_intra_unregister_credit,2);?></a>
						   </td>
						   </tr>
						   <tr>
						   <th>OT Sales Return</th>
						  <td>
						  <a href="gst_credit_otsls_detailed_report?data1=inner&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						  <?=number_format($total_intra_register_creditOT,2);?></a>
						  </td>
						  <td><a href="gst_credit_otsls_detailed_report?data1=inner&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						  <?=number_format($total_intra_unregister_creditOT,2);?></a>
						  </td>
						   </tr>
						   
						   <?php
							// intra-state (register) credit note
							$total_intra_register_credit_note=$total_intra_register_credit+$total_intra_register_creditOT;
							// intra-state (unregister) credit note
							$total_intra_unregister_credit_note=$total_intra_unregister_credit+$total_intra_unregister_creditOT;
						   ?>
						   
						    <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=number_format($total_intra_register_credit_note,2);?></b></td>
							<td><b><?=number_format($total_intra_unregister_credit_note,2);?></b></td>
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
						   $Total_sls_register_inter=$total_inter_register+$total_inter_register2;
						   $Total_sls_unregister_inter=$total_inter_unregister+$total_inter_unregister2;
						   ?>
						   
						   <tr>
						   <th>Total Sales (SS,ST, DT, SHP, CUS)</th>
						   <td><a href="gst_sls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($Total_sls_register_inter,2);?></a></td>
						   <td><a href="gst_sls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($Total_sls_unregister_inter,2);?></a></td>
						   </tr>
						   
						   <tr>
						   <th>Total OT Sales</th>
						   <td><a href="gst_otsls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($total_reg_OTSLS_inter,2);?></a></td>
						   <td><a href="gst_otsls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($total_reg_OTSLSUN_inter,2);?></a></td>
						   </tr>
						   
						   <?php 
						   $Total_inter_register_sales=$Total_sls_register_inter+$total_reg_OTSLS_inter;
						   $Total_inter_unregister_sales=$Total_sls_unregister_inter+$total_reg_OTSLSUN_inter;
						   ?>
						   <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=number_format($Total_inter_register_sales,2);?></b></td>
							<td><b><?=number_format($Total_inter_unregister_sales,2);?></b></td>
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
						   <tr>
						   <th>Sales Return<br/>(SS,ST, DT, SHP, CUS)</th>
						   <td>
						   <a href="gst_credit_sls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=number_format($total_inter_register_credit,2);?></a>
						   </td>
						   <td><a href="gst_credit_sls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank">
						   <?=number_format($total_inter_unregister_credit,2);?></a>
						   </td>
						   </tr>
						   
						   <tr>
						   <th>OT Sales Return</th>
						   <td>
						   <a href="gst_credit_otsls_detailed_report?data1=outer&&data2=register&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($total_inter_register_creditOT,2);?></a>
						   </td>
						   <td>
						   <a href="gst_credit_otsls_detailed_report?data1=outer&&data2=unregister&&frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&gid=<?=$get_godown_id;?>" target="_blank"><?=number_format($total_inter_unregister_creditOT,2);?></a>
						   </td>
						   </tr>
						   
						   <?php
							// inter-state (register) credit note
							$total_inter_register_credit_note=$total_inter_register_credit+$total_inter_register_creditOT;
							// inter-state (unregister) credit note
							$total_inter_unregister_credit_note=$total_inter_unregister_credit+$total_inter_unregister_creditOT;
						   ?>
						   
						    <tfoot>
						   <tr>
						   <td style="text-align:right;"><b>Total</b></td>
						    <td><b><?=number_format($total_inter_register_credit_note,2);?></b></td>
							<td><b><?=number_format($total_inter_unregister_credit_note,2);?></b></td>
						   </tr>
						   </tfoot>
						   </table>
						   
						   </td>
							 
							 </tr>
							 </table>
							 
						   <div style="clear:both;"></div>
						   <br/>
						   
						    <?php 
							//Intra
			$intra_reg_supplies_grand_total=$Total_intra_register_sales-$total_intra_register_credit_note;
			$intra_unreg_supplies_grand_total=$Total_intra_unregister_sales-$total_intra_unregister_credit_note;
			
			//Inter
			$inter_reg_supplies_grand_total=$Total_inter_register_sales-$total_inter_register_credit_note;
			$inter_unreg_supplies_grand_total=$Total_inter_unregister_sales-$total_inter_unregister_credit_note;
						   ?>
						   
						   <div align="right">
						   <a href="export_GSTR1?t1=<?=$intra_reg_supplies_grand_total;?>&&t2=<?=$intra_unreg_supplies_grand_total;?>&&t3=<?=$inter_reg_supplies_grand_total;?>&&t4=<?=$inter_unreg_supplies_grand_total;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a>
						   </div>
						   
						   <div style="clear:both;"></div>
						   <br/>
						   
						   <table id="gsttablevl">
						   <tr>
						   <th>Description</th>
						   <th>Nil Rated Supplies</th>
						   <th>Exempted (Other than Nil Rated/non GST Supply)</th>
						   <th>Non GST Supplies</th>
						   </tr>
						  
						   <tr>
						   <th>Intra-state supplies to registered person</th>
						   <td><?=number_format($intra_reg_supplies_grand_total,2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						   </tr>
						   <tr>
						   <th>Intra-state supplies to unregistered person</th>
						   <td><?=number_format($intra_unreg_supplies_grand_total,2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						   </tr>
						 
						   <tr>
						   <th>Inter-state supplies to registered person</th>
						   <td><?=number_format($inter_reg_supplies_grand_total,2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						   </tr>
						   <tr>
						   <th>Inter-state supplies to unregistered person</th>
						   <td><?=number_format($inter_unreg_supplies_grand_total,2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						   </tr>
						  
						  <?php 
						  $Nil_rated_total=$intra_reg_supplies_grand_total+$intra_unreg_supplies_grand_total+$inter_reg_supplies_grand_total+$inter_unreg_supplies_grand_total;
						  ?>
						  
						  <tr>
						  <td></td>
						 <td><?=number_format($Nil_rated_total,2);?></td>
						  <td></td>
						  <td></td>
						  </tr>
						  
						   </table>
						   
						   
						   <!-------------HSN wise Total Qty---------->
						   <br/>
						    <table id="gsttablevl" style="height:auto;">
							<tr>
							<th>HSN</th>
							<th>Total Quantity</th>
							<th>Total Taxable Value</th>
							</tr>
							
							<?php $select_hsnwise_total="SELECT DISTINCT hsn FROM products ORDER BY hsn ASC";
							$fetch_hsnwise_total=mysqli_query($db_conn,$select_hsnwise_total);
							while($result_hsnwise_total=mysqli_fetch_array($fetch_hsnwise_total)){
								
								$hsn_code=$result_hsnwise_total['hsn'];
								
								//Total sls qty
								$Total_HSN_sls="select sum(qty) from user_invoice_items where hsn='$hsn_code' and date between '$from_date' and '$to_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id'";
								$fetch_HSN_sls=mysqli_query($db_conn,$Total_HSN_sls);
								$result_HSN_sls=mysqli_fetch_array($fetch_HSN_sls);
								if($result_HSN_sls[0]!=NULL){ $show_HSN_sls_qty=$result_HSN_sls[0];}
								else{$show_HSN_sls_qty="0";}
								
								//Total sls return qty
								$Total_HSN_sls_rtn="select sum(qty) from user_return_stock_items where hsn='$hsn_code' and date between '$from_date' and '$to_date' and to_usertype='$Login_user_TYPEvl' and to_usertype='$get_godown_id'";
								$fetch_HSN_sls_rtn=mysqli_query($db_conn,$Total_HSN_sls_rtn);
								$result_HSN_sls_rtn=mysqli_fetch_array($fetch_HSN_sls_rtn);
								if($result_HSN_sls_rtn[0]!=NULL){ $show_HSN_sls_rtn_qty=$result_HSN_sls_rtn[0];}
								else{$show_HSN_sls_rtn_qty="0";}
								
								$Net_HSN_sls_qty=$show_HSN_sls_qty-$show_HSN_sls_rtn_qty;
								
								
								//Total OT sls qty
								$Total_HSN_slsOT="select sum(qty) from ot_sales where hsn='$hsn_code' and date between '$from_date' and '$to_date' and godownid='$get_godown_id'";
								$fetch_HSN_slsOT=mysqli_query($db_conn,$Total_HSN_slsOT);
								$result_HSN_slsOT=mysqli_fetch_array($fetch_HSN_slsOT);
								if($result_HSN_slsOT[0]!=NULL){ $show_HSN_sls_qty_OT=$result_HSN_slsOT[0];}
								else{$show_HSN_sls_qty_OT="0";}
								
								//Total OT sls qty
								$Total_HSN_slsOT_rtn="select sum(qty) from ot_sales_return where return_date between '$from_date' and '$to_date' and godownid='$get_godown_id' and hsn='$hsn_code'";
								$fetch_HSN_slsOT_rtn=mysqli_query($db_conn,$Total_HSN_slsOT_rtn);
								$result_HSN_slsOT_rtn=mysqli_fetch_array($fetch_HSN_slsOT_rtn);
								if($result_HSN_slsOT_rtn[0]!=NULL){ $show_HSN_sls_qty_OT_rtn=$result_HSN_slsOT_rtn[0];}
								else{$show_HSN_sls_qty_OT_rtn="0";}
								
								$Net_HSN_sls_qty_OT=$show_HSN_sls_qty_OT-$show_HSN_sls_qty_OT_rtn;
								
								//Total Internal Transfer sls qty
								$Total_HSN_sls_inter="select sum(qty) from internal_transfer where hsn='$hsn_code' and date between '$from_date' and '$to_date' and send_from='$get_godown_id'";
								$fetch_HSN_sls_inter=mysqli_query($db_conn,$Total_HSN_sls_inter);
								$result_HSN_sls_inter=mysqli_fetch_array($fetch_HSN_sls_inter);
								if($result_HSN_sls_inter[0]!=NULL){ $show_HSN_sls_qty_inter=$result_HSN_sls_inter[0];}
								else{$show_HSN_sls_qty_inter="0";}
								
								$overall_HSN_sls_qty=$Net_HSN_sls_qty+$Net_HSN_sls_qty_OT+$show_HSN_sls_qty_inter;
								
								?>
							
							<tr>
							<td style="text-align:left;"><?=$hsn_code;?></td>
							<td style="text-align:left;"><?=$overall_HSN_sls_qty;?></td>
							<td style="text-align:left;"><?=number_format($Nil_rated_total,2);?></td>
							</tr>
							
							<?php }?>
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