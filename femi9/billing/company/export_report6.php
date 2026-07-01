<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Market Stock Report";

if($_REQUEST['modelval']=="usr1" )
{$DISPLAY_LABLE="Super Stockist"; $tblenma="super_stockiest"; $usertype_stock="super_stockiest";}

else if($_REQUEST['modelval']=="usr2")
{$DISPLAY_LABLE="Stockist"; $tblenma="stockiest"; $usertype_stock="stockiest";}

else if($_REQUEST['modelval']=="usr3")
{$DISPLAY_LABLE="Distributor"; $tblenma="distributor"; $usertype_stock="distributor";}

else if($_REQUEST['modelval']=="usr4")
{$DISPLAY_LABLE="Super Distributor"; $tblenma="super_distributor"; $usertype_stock="super_distributor";}

else
{$DISPLAY_LABLE="Outlet"; $tblenma="outlet"; $usertype_stock="outlet";}

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';


if($_REQUEST['modelval']=="usr1"){
	
// Header row for CSV
$csv_content .= "User ID, Name, District, Product Description, Available Stock\n";

// Fetching data and formatting into CSV rows
$selectRcd_VLSS1="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
//GET PRODCUTWISE STOCK
$usrid_VLSS1=$resultRcd_VLSS1['temp_id'];
$select_marketstock_VLDIST_VLSS1="select * from stock where user_type='$usertype_stock' and user_id='$usrid_VLSS1'";
$fetch_marketstock_VLDIST_VLSS1=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS1);
while($result_marketstock_VLDIST_VLSS1=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS1))
{
	
	$productID_VLSS1=$result_marketstock_VLDIST_VLSS1['product_id'];
	//
	$select_productDetils_VLSS1="select * from products where id='$productID_VLSS1'";
						$Fetch_productDetils_VLSS1=mysqli_query($db_conn,$select_productDetils_VLSS1);
						$Result_productDetils_VLSS1=mysqli_fetch_array($Fetch_productDetils_VLSS1);
						
						$closing_stock_VLSS1=$result_marketstock_VLDIST_VLSS1['closing_qty'];
						
						if($closing_stock_VLSS1>0){
	

    // Prepare CSV row
    $csv_content .= '"' . $resultRcd_VLSS1["useridtext"] . '",' .
                    '"' . $resultRcd_VLSS1["name"]. '",' .
					'"' . $district_name_VLSS1. '",' .
					'"' . $Result_productDetils_VLSS1["productName"]. '",' .
                    '"' . $closing_stock_VLSS1 . "\"\n";
}
}
}


}//if usr1 end***

else 

{
	
	// Header row for CSV
$csv_content .= "User ID, Name, District, Taluk, Product Description, Available Stock\n";
	
	$selectRcd_VLSS2="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS2=mysqli_query($db_conn,$selectRcd_VLSS2);
										while($resultRcd_VLSS2=mysqli_fetch_array($fetchRcd_VLSS2))
										{
											
											$state_id_VLSS2=$resultRcd_VLSS2['state_id'];
											$district_id_VLSS2=$resultRcd_VLSS2['district_id'];
											$taluk_id_VLSS2=$resultRcd_VLSS2['taluk_id'];
//DISTRICT
if(is_numeric($district_id_VLSS2))
{
										$selectrecords_VLSS2="select * from district where state_id='$state_id_VLSS2' and id='$district_id_VLSS2'";
										$fetchrecords_VLSS2=mysqli_query($db_conn,$selectrecords_VLSS2);
										$resultrecords_VLSS2=mysqli_fetch_array($fetchrecords_VLSS2);
										$district_name_VLSS2=$resultrecords_VLSS2['dist_name'];
}else{
	$district_name_VLSS2=$district_id_VLSS2;
}
	
//TALUK	
if(is_numeric($taluk_id_VLSS2))
{	
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS2' and dist_id='$district_id_VLSS2' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
}else{
$taluk_name_VLSS2=$taluk_id_VLSS2;	
}
										
//GET PRODCUTWISE STOCK
$usrid_VLSS2=$resultRcd_VLSS2['temp_id'];
$select_marketstock_VLDIST_VLSS2="select * from stock where user_type='$usertype_stock' and user_id='$usrid_VLSS2'";
$fetch_marketstock_VLDIST_VLSS2=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS2);
while($result_marketstock_VLDIST_VLSS2=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS2))
{
	
	$productID_VLSS2=$result_marketstock_VLDIST_VLSS2['product_id'];
	$select_productDetils_VLSS2="select * from products where id='$productID_VLSS2'";
						$Fetch_productDetils_VLSS2=mysqli_query($db_conn,$select_productDetils_VLSS2);
						$Result_productDetils_VLSS2=mysqli_fetch_array($Fetch_productDetils_VLSS2);
						
						$closing_stock_VLSS2=$result_marketstock_VLDIST_VLSS2['closing_qty'];
						
						if($closing_stock_VLSS2>0){



$csv_content .='"' . $resultRcd_VLSS2["useridtext"]. '",' .
							'"' . $resultRcd_VLSS2["name"]. '",' .
							'"' . $district_name_VLSS2. '",' .
							'"' . $taluk_name_VLSS2. '",' .
							'"' . $Result_productDetils_VLSS2["productName"]. '",' .
							'"' . $closing_stock_VLSS2 . "\"\n";

										}
										
}
										}
	
	
}//if usr2 end***







										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>