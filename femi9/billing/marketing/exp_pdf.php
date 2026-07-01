<?php include("checksession.php");
include("config.php");
error_reporting(0);

$from_date=$_REQUEST['frd'];
						$to_date=$_REQUEST['tod'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <!-- Title -->
    <title>Datewise Expenses report</title>
	<style type="text/css">
	body{font-family:arial;text-align:center;}
	table{width:100%;border-collapse:collapse;font-family:arial;}
	table th{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	table td{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	</style>
</head>

<body>
    <h1>Datewise Expenses report</h1>
	<h3><?=date("d/m/Y",strtotime($from_date));?> (to) <?=date("d/m/Y",strtotime($to_date));?></h3>
	
                                         <table class="table">
                                            <thead>
                                               <tr>
                                                    <th>#</th>
                                                    <th>Date</th>
													<th>Amount</th>
													<th>Remarks</th>
                                                </tr>
                                            </thead>
											
											<tbody>
<?php 
	$select_product_list="select * from ms_exp where ms_id='$markeingSTFID' and date between '$from_date' and '$to_date' order by date asc";

$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{																

					$exp_amount=$result_product_list["amount"];
					$exp_amount12+=$exp_amount;
					
?>                                            
                    <tr>
                    <td><?php echo ++$i; ?></td>
                    
					<td>
					<b><?php echo date("d/m/Y",strtotime($result_product_list["date"]));?></td>
					<td><?=number_format($exp_amount,2,'.','');?></td>
					<td><?=ucwords($result_product_list["remarks"]);?></td>
	
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
										 
										 <tr>
										 <td colspan="2">Total</td>
										 <td><?=number_format($exp_amount12,2,'.','');?></td>
										 <td></td>
										 </tr>
										 
                                        </table>
										
										
										<script>window.print();</script>