<?php include("checksession.php");
include("config.php");
error_reporting(0);

$tablename="customers";
$file="customer-invoice-list.xls";

$html='
<table style="width:100%;" border="1">
                                            <thead>
                                                <tr>
													<th>Invoice Number</th>
													<th>Customer Name</th>
													<th>Invoice Date</th>
													<th>Invoice Amount</th>
                                                </tr>
                                            </thead>
											<tbody>';

$select_product_list="select * from invoice where user_id='$Login_user_IDvl' order by id desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{
	$CuSTID=$result_product_list['customer_id'];
	if($CuSTID!=0)
	{
		$select_Customers="select * from ".$tablename." where id='$CuSTID'";
		$fetch_Customers=mysqli_query($db_conn,$select_Customers);
		$result_Customers=mysqli_fetch_array($fetch_Customers);
		$customerDetails=$result_Customers['name']." M: ".$result_Customers['mobile'];
	}else{
		$customerDetails="Walking Customer";
	}

	$html=$html.'
                                                <tr>
                                                  <td>'.$result_product_list["inv_number"].'</td>
													<td>'.$customerDetails.'</td>
													<td>'.date("d/M/Y",strtotime($result_product_list["date"])).'</td>
													<td>'.inr_format($result_product_list["total"], 2).'</td>
                                                </tr>';
}
$html=$html.'
										 </tbody>
                                        </table>';

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$file");
echo $html;
?>