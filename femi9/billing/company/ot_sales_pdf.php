<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$Report_LABLE="Channelwise Sales";

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Today";}
else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Yesterday";}
else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="This Month";}
else
{$DISPLAY_LABLE="Last Month";}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];
$catname=base64_decode($_REQUEST['cat']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <!-- Title -->
    <title>OT Sales Report</title>
	<style type="text/css">
	body{font-family:arial;text-align:center;}
	table{width:100%;border-collapse:collapse;font-family:arial;}
	table th{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	table td{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	</style>
</head>

<body>
    <h1>OT Sales Report</h1>
	<h3><?=date("d/m/Y",strtotime($from_date));?> (to) <?=date("d/m/Y",strtotime($to_date));?></h3>
	
                                         <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Company Profile</th>
													<th>Category</th>
													<th>Date</th>
													<th>Order Number</th>
													<th>Customer Name</th>
													<th>Customer Mobile</th>
													<th>Address</th>
													<th>Total Amount</th>
													
				<?php $select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){?>
				<th><?=$result_prdetails_header['productName'];?></th>
				<?php }?>
				
												</tr>
                                            </thead>
											
											<tbody>
										<?php
										$select_Count_records="select count(*) as numRecords from ot_sales where date between '$from_date' and '$to_date'";
										$fetch_Count_records=mysqli_query($db_conn,$select_Count_records);
										$result_Count_records=mysqli_fetch_array($fetch_Count_records);
										if($result_Count_records['numRecords']>0)
										{
										
										
										if($catname==NULL)
										{
										$select_product_list="select distinct tempid from ot_sales where date between '$from_date' and '$to_date' and godownid IN (" . godown_ids_subquery($db_conn) . ")";
										}else{
										$select_product_list="select distinct tempid from ot_sales where date between '$from_date' and '$to_date' and cat='$catname' and godownid IN (" . godown_ids_subquery($db_conn) . ")";
										}
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											$ot_tempid=$result_product_list['tempid'];

										$selectrecords="select * from ot_sales where tempid='$ot_tempid'";
										$fetchrecords=mysqli_query($db_conn,$selectrecords);
										$resultrecords=mysqli_fetch_array($fetchrecords);
											
											//company profile details
											$godownid=$resultrecords['godownid'];
$select_Customers="select * from company_godown where id='$godownid' AND " . godown_finance_filter_sql($db_conn);
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?=$result_Customers["gname"];?></td>
													<td><?=$resultrecords['cat'];?></td>
													<td><?=date("d/M/Y",strtotime($resultrecords["date"]));?></td>
													
													<td><?php echo $resultrecords["order_number"];?></td>
					
					<td><?php echo $resultrecords["customer_name"];?></td>
					<td><?php echo $resultrecords["customer_mobile"];?></td>
					<td><?php echo $resultrecords["customer_address"];?></td>
													
				<?php
				$selectsumTotalAmount="select sum(total) from ot_sales where tempid='$ot_tempid'";
				$fetchsumTotalAmount=mysqli_query($db_conn,$selectsumTotalAmount);
				$resultsumTotalAmount=mysqli_fetch_array($fetchsumTotalAmount);
				if($resultsumTotalAmount[0]!=NULL){
				$TotalAmount=$resultsumTotalAmount[0];}else{$TotalAmount="0";}
				$TotalAmount123+=$TotalAmount;
				
				/*$selectsumTotalQTY="select sum(qty) from ot_sales where tempid='$ot_tempid'";
				$fetchsumTotalQTY=mysqli_query($db_conn,$selectsumTotalQTY);
				$resultsumTotalQTY=mysqli_fetch_array($fetchsumTotalQTY);
				if($resultsumTotalQTY[0]!=NULL){
				$TotalPrQty=$resultsumTotalQTY[0];}else{$TotalPrQty="0";}
				$TotalPrQty123+=$TotalPrQty;*/
				?>
				<td align="right"><?php echo inr_format($TotalAmount, 2);?></td>
				
				
				<!------------------------PRODUCT WISE SALES QTY------------------------------->
				<?php $select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){
					
					$prid_header=$result_prdetails_header['id'];
					
					//SALES QTY
					$select_SUM_QTY="select qty from ot_sales where tempid='$ot_tempid' and prid='$prid_header'";
					$fetch_SUM_QTY=mysqli_query($db_conn,$select_SUM_QTY);
					$result_SUM_QTY=mysqli_fetch_array($fetch_SUM_QTY);
					if($result_SUM_QTY['qty']!=NULL){ $slsqty=$result_SUM_QTY['qty'];} else{ $slsqty="0";}
					
					//SALES Return QTY
					/*$select_Return_QTY="select qty from ot_sales_return where tempid='$ot_tempid' and prid='$prid_header'";
					$fetch_Return_QTY=mysqli_query($db_conn,$select_Return_QTY);
					$result_Return_QTY=mysqli_fetch_array($fetch_Return_QTY);
					if($result_Return_QTY['qty']!=NULL){ $slsRtnqty=$result_Return_QTY['qty'];} else{ $slsRtnqty="0";}*/
					
					//$net_sls_qty=$slsqty-$slsRtnqty;
					
					$net_sls_qty=$slsqty;
						
				?>
				<td><b><?=$net_sls_qty;?></b></td>
				<?php }?>
				<!-------------------------------------------------------------------->
				
				
				
                                        </tr>
                                        
                                        
                                           
										<?php }?>
										<?php }?>
										
										</tbody>
										 
										 <?php /*?>
										 <tfoot>
										 <tr>
										 <td colspan="4">Grand Total</td>
				<td align="right"><b><?php echo inr_format($TotalAmount123, 2);?></b></td>
				<td align="right"><b><?=$TotalPrQty123;?></b></td>
										 </tr>
										 </tfoot>
										 <?php */?>
										 
                                        </table>
										
										
										<script>window.print();</script>