<?php include("checksession.php");
include("config.php");

$file="super-distributor-list.xls";

$html='
<table style="width:100%;" border="1">
                                            <thead>
                                                <tr>
													<th>ID</th>
													<th>Name</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Pincode</th>
													<th>Mobile</th>
													<th>Account Status</th>
                                                </tr>
                                            </thead>
											<tbody>';

				$select_product_list="select * from super_distributor where onboard_userID='$onboard_userID' order by id desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{
											$html=$html.'

                                                <tr>
                                                  <td>'.$result_product_list["useridtext"].'</td>
                                                    <td>'.ucwords($result_product_list["name"]).'</td>
													<td>'.ucwords($result_product_list["district_id"]).'</td>
													<td>'.ucwords($result_product_list["taluk_id"]).'</td>
													<td>'.$result_product_list["pincode_id"].'</td>
													<td>'.$result_product_list["country_code"].' '.$result_product_list["mobile_number"].'</td>
													<td>'.ucwords($result_product_list["account_status"]).'</td>
                                                </tr>';

										}
										$html=$html.'

										 </tbody>
                                        </table>';

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$file");
echo $html;
?>