<?php include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser="shop";
$tablename="shop";
$file="shop-invoice-list.xls";

$html='
<table style="width:100%;" border="1">
                                            <thead>
                                                <tr>
													<th>Invoice Number</th>
													<th>Shop Name</th>
													<th>Invoice Date</th>
													<th>Invoice Amount</th>
                                                </tr>
                                            </thead>
											<tbody>';

$select_product_list="select * from user_invoice where from_user_id='$Login_user_IDvl' and to_user_type='$getinvuser' order by id desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{
	$CuSTID=$result_product_list['to_user_id'];
	$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
	$fetch_Customers=mysqli_query($db_conn,$select_Customers);
	$result_Customers=mysqli_fetch_array($fetch_Customers);
	$Cust_Name=$result_Customers['name'];
	$Cust_Mbile=$result_Customers['mobile_number'];

	$html=$html.'
                                                <tr>
                                                  <td>'.$result_product_list["inv_number"].'</td>
													<td>'.$Cust_Name.' M: '.$Cust_Mbile.'</td>
													<td>'.date("d/M/Y",strtotime($result_product_list["date"])).'</td>
													<td>'.number_format($result_product_list["total"],2,'.','').'</td>
                                                </tr>';
}
$html=$html.'
										 </tbody>
                                        </table>';

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$file");
echo $html;
?>