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
    <title>Datewise Expenses Report</title>
	<style type="text/css">
	body{font-family:arial;text-align:center;}
	table{width:100%;border-collapse:collapse;font-family:arial;}
	table th{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	table td{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	</style>
</head>

<body>
    <h1>Datewise Expenses Report</h1>
	<h3><?=date("d/m/Y",strtotime($from_date));?> (to) <?=date("d/m/Y",strtotime($to_date));?></h3>
	<?php if($se_msid!=NULL){?>
	<h3>Staff : <?=$result_msDetails12['ms_name'];?>, <?=$result_msDetails12['ms_mobile'];?></h3>
	<?php }?>
	
                                         <table class="table">
                                            <thead>
                                               <tr>
                                                    <th>#</th>
													<th>Marketing Staff</th>
                                                    <th>Date</th>
													<th>Amount</th>
													<th>Remarks</th>
                                                </tr>
                                            </thead>
											
											<tbody>
<?php 
if($_REQUEST['frdate']==NULL && $se_msid==NULL)
{
$select_product_list="select * from ms_exp order by id desc";
}
if($_REQUEST['frdate']!=NULL && $se_msid==NULL)
{
$select_product_list="select * from ms_exp where date between '$from_date' and '$to_date' order by date asc";
}
if($_REQUEST['frdate']!=NULL && $se_msid!=NULL)
{
$select_product_list="select * from ms_exp where ms_id='$se_msid' and date between '$from_date' and '$to_date' and ms_id='$se_msid' order by date asc";
}


$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{																

					$rowid=base64_encode($result_product_list["id"]);
					
					if($result_product_list["photos"]!="Nil" && $result_product_list["photos"]!=NULL){
						$imgsrcname="../marketing/bill_copy_photos/".$result_product_list["photos"]."";}else{
							$imgsrcname="../../assets/images/no image.jpg";}
				
				$exp_amount=$result_product_list["amount"];
				$exp_amount123+=$exp_amount;
				
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
					<td>
					<b><?php echo date("d/m/Y",strtotime($result_product_list["date"]));?></td>
					<td><?=number_format($exp_amount,2,'.','');?></td>
					<td><?=ucwords($result_product_list["remarks"]);?></td>
					
													
	
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
										 
										 <tfoot>
										<tr>
										 <td colspan="3">Total</td>
										 <td><?=number_format($exp_amount123,2,'.','');?></td>
										 <td></td>
										 </tr>
										 </tfoot>
										 
                                        </table>
										
										
										<script>window.print();</script>