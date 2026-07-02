<?php include("checksession.php");
include("config.php");

$file="customer-list.xls";

$html='
<table style="width:100%;" border="1">
                                            <thead>
                                                <tr>
													<th>Name</th>
													<th>Mobile</th>
													<th>Email</th>
													<th>GSTIN</th>
													<th>Address</th>
                                                </tr>
                                            </thead>
											<tbody>';
											
				$select_product_list="select * from customers where user_id='$Login_user_IDvl' order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											$html=$html.'
                                            
                                                <tr>
                                                 <td>'.$result_product_list["name"].'</td>
													<td>'.$result_product_list["mobile"].'</td>
													<td>'.$result_product_list["email"].'</td>
													<td>'.$result_product_list["gstin"].'</td>
													<td>'.$result_product_list["address"].'</td>
                                                </tr>';
                                           
										}
										$html=$html.'
										
										 </tbody>
                                        </table>';

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$file");
echo $html;
?>