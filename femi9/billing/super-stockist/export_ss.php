<?php include("checksession.php");
include("config.php");

$file="stockist-list.xls";

$html='
<table style="width:100%;" border="1">
                                            <thead>
                                                <tr>
													<th>Name</th>
                                                    <th>State</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Email</th>
													<th>Mobile</th>
													<th>GSTIN</th>
													<th>Address</th>
                                                </tr>
                                            </thead>
											<tbody>';
											
				$select_product_list="select * from stockiest where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											
											//state details
											$state_id=$result_product_list['state_id'];
								$select_stateList="select * from state where id='$state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
							   
							   
											//district
											$district_id=$result_product_list['district_id'];
										$select_district="select * from district where id=$district_id";
										$fetch_district=mysqli_query($db_conn,$select_district);
										$result_district=mysqli_fetch_array($fetch_district);
										$district_name=$result_district['dist_name'];
										
										//Taluk
											$taluk_id=$result_product_list['taluk_id'];
										$select_Taluk="select * from taluk where id=$taluk_id";
										$fetch_Taluk=mysqli_query($db_conn,$select_Taluk);
										$result_Taluk=mysqli_fetch_array($fetch_Taluk);
										$taluk_name=$result_Taluk['taluk'];
											
											$html=$html.'
                                            
                                                <tr>
                                                  <td>'.$result_product_list['name'].'</td>
                                                    <td>'.$state_name.'</td>
													<td>'.$district_name.'</td>
													<td>'.$taluk_name.'</td>
													<td>'.$result_product_list['email'].'</td>
													<td>'.$result_product_list['mobile_number'].'</td>
													<td>'.$result_product_list['gstin'].'</td>
													<td>'.$result_product_list['address'].'</td>
                                                </tr>';
                                           
										}
										$html=$html.'
										
										 </tbody>
                                        </table>';

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$file");
echo $html;
?>