<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

$file="C-F-Report.xls";

$html='
<table border="1" cellpadding="5" cellspacing="0">
                                            <thead>
                                                <tr>
													<th>ID</th>
													<th>Name</th>
													<th>Mobile Number</th>
													<th>State</th>
													<th>Email ID</th>
													<th>GSTIN</th>
                                                </tr>
                                            </thead>
											<tbody>
';


$select_product_list="select * from c_and_f order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											
											
$html=$html.'

<td>'.$result_product_list["useridtext"].'</td>
<td>'.$result_product_list["name"].'</td>
<td>'.$result_product_list["mobile_number"].'</td>
<td>
';

$data=$result_product_list['state_id']; 
$ex=explode("#",$data);
 
  foreach ($ex as $key => $value)
   {  
    
$select_distict="select st_name from state where id='$value'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
		
		$html=$html.'
		'.$result_district['st_name'].'
		';
	
   }


$html=$html.'

</td>

<td>'.$result_product_list["email"].'</td>
<td>'.$result_product_list["gstin"].'</td>
					</tr>
					';

										}
										
										$html=$html.'</table>';



header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$file");
echo $html;

?>



