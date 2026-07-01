<?php include("checksession.php");
include("config.php");
error_reporting(0);

$from_date=$_REQUEST['frd'];
$to_date=$_REQUEST['tod'];
$se_msid=$_REQUEST['se_msid'];
//
$select_msDetails12="select * from marketing_staff where id='$se_msid'";
$fetch_msDetails12=mysqli_query($db_conn,$select_msDetails12);
$result_msDetails12=mysqli_fetch_array($fetch_msDetails12);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <!-- Title -->
    <title>Datewise Product Orders Report</title>
	<style type="text/css">
	body{font-family:arial;text-align:center;}
	table{width:100%;border-collapse:collapse;font-family:arial;}
	table th{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	table td{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	</style>
</head>

<body>
    <h1>Datewise Product Orders Report</h1>
	<h3><?=date("d/m/Y",strtotime($from_date));?> (to) <?=date("d/m/Y",strtotime($to_date));?></h3>
	<?php if($se_msid!=NULL){?>
	<h3>Staff : <?=$result_msDetails12['ms_name'];?>, <?=$result_msDetails12['ms_mobile'];?></h3>
	<?php }?>
	
                                         <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Marketing Staff</th>
													<th>Shop Name</th>
													<th>Shop Contact Number</th>
													<th>Address</th>
													<th>Date</th>
													
			   <?php $select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){?>
				<th><?=$result_prdetails_header['productName'];?></th>
				<?php }?>
				
                                                </tr>
                                            </thead>
											
											<tbody>
<?php 
if($from_date!=NULL && $se_msid==NULL)
{
$select_product_list="select distinct order_id from ms_orders where new_order='yes' and order_date between '$from_date' and '$to_date'";
}
if($from_date!=NULL && $se_msid!=NULL)
{
$select_product_list="select distinct order_id from ms_orders where new_order='yes' and order_date between '$from_date' and '$to_date' and ms_id='$se_msid'";
}
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

//marketing staff details
$ms_id=$result_product_list['ms_id'];
$select_msDetails="select * from marketing_staff where id='$ms_id'";
$fetch_msDetails=mysqli_query($db_conn,$select_msDetails);
$result_msDetails=mysqli_fetch_array($fetch_msDetails);
?>
                                            
                                               <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$result_msDetails['ms_name'];?><br/>
					<?=$result_msDetails['ms_mobile'];?>
					</td>
					<td><?=$result_shopcatt['name'];?></td>
					<td><?=$result_shopcatt['mobile_number'];?></td>
					<td><?=ucwords($result_shopcatt["address"]);?></td>
					<td><?=date("d/m/Y",strtotime($result_product_list["order_date"]));?></td>
					
					
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
					
					
			
													
	
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
										 
                                        </table>
										
										
										<script>window.print();</script>