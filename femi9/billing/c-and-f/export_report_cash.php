<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Cash Report";

if($_REQUEST['lable']==1 )
{
	$DISPLAY_LABLE="Super Stockist";  	$usertypevl="super_stockiest";
	//$tblenma="super_stockiest"; 
}

else if($_REQUEST['lable']==2)
{
	$DISPLAY_LABLE="Stockist"; 			$usertypevl="stockiest";
//$tblenma="stockiest"; 
}

else
{
	$DISPLAY_LABLE="Distributor";  		$usertypevl="distributor";
	//$tblenma="distributor"; 
}


$start_date=$_REQUEST['frdate'];
$endDate=$_REQUEST['todate'];

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';

//-----------------------------------------------------------usr1-------------------------------------
if($_REQUEST['lable']==1){
	
// Header row for CSV
$csv_content .= "Inv Num, Date, Type,ID,Name, District,Amount\n";

// Fetching data and formatting into CSV rows



$select_market_SSCASH_VLSS_THISMONTH="select distinct inv_id from receipt where from_user_type='$usertypevl' and date between '$start_date' and '$endDate'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$receipt_inv_id=$result_market_SSCASH_VLSS_THISMONTH['inv_id'];
	
	//get invoice Number
	$select_CNT_number="select count(*) as numINVNUM from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_CNT_number=mysqli_query($db_conn,$select_CNT_number);
	$result_CNT_number=mysqli_fetch_array($fetch_CNT_number);
	
	if($result_CNT_number['numINVNUM']==1)
	{
	$select_inv_number="select * from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	$to_user_type=$result_inv_number['to_user_type'];
	$to_user_id=$result_inv_number['to_user_id'];
	
	}else{
	$select_inv_number="select * from invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	$to_user_type="customer";
	$to_user_id=$result_inv_number['customer_id'];
	}
	
	
	
if($to_user_type=="stockiest")
{$tablename="stockiest";}
else if($to_user_type=="distributor")
{$tablename="distributor";}
else if($to_user_type=="customer")
{$tablename="customers";}
else
{$tablename="shop";}

if($to_user_id==0)
{
	
	$selectRcd_VLSS1="select * from ".$tablename." where id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext="Nil";
	$usernametext="Nil";
	$district_name_VLSS1="Nil";
	
}else{
	
$selectRcd_VLSS1="select * from ".$tablename." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
}
										
$select_marketstock_VLDIST_VLSS1="select sum(received) from receipt where inv_id='$receipt_inv_id'";
$fetch_marketstock_VLDIST_VLSS1=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS1);
$result_marketstock_VLDIST_VLSS1=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS1);

if($result_marketstock_VLDIST_VLSS1[0]!=NULL){
$Total_available_stock_VLSS1=number_format($result_marketstock_VLDIST_VLSS1[0],2,'.','');
}else{$Total_available_stock_VLSS1="0";}

$Total_available_stock123_VLSS1+=$Total_available_stock_VLSS1;


    // Prepare CSV row
    $csv_content .= '"' . $inv_number . '",' .
                    '"' . $inv_date. '",' .
					'"' . $to_user_type. '",' .
					'"' . $useridtext. '",' .
					'"' . $usernametext. '",' .
					'"' . $district_name_VLSS1. '",' .
                    '"' . $Total_available_stock_VLSS1 . "\"\n";
					
					
					
}
										

}




//-----------------------------------------------------------usr2-------------------------------------
if($_REQUEST['lable']==2){
	
	// Header row for CSV
$csv_content .= "Inv Num, Date, Type,ID,Name, District,Amount\n";
	
	$select_market_SSCASH_VLSS_THISMONTH="select distinct inv_id from receipt where from_user_type='$usertypevl' and date between '$start_date' and '$endDate'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$receipt_inv_id=$result_market_SSCASH_VLSS_THISMONTH['inv_id'];
	
	//get invoice Number
	$select_CNT_number="select count(*) as numINVNUM from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_CNT_number=mysqli_query($db_conn,$select_CNT_number);
	$result_CNT_number=mysqli_fetch_array($fetch_CNT_number);
	
	if($result_CNT_number['numINVNUM']==1)
	{
	$select_inv_number="select * from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	$to_user_type=$result_inv_number['to_user_type'];
	$to_user_id=$result_inv_number['to_user_id'];
	
	}else{
	$select_inv_number="select * from invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	$to_user_type="customer";
	$to_user_id=$result_inv_number['customer_id'];
	}
	
	
if($to_user_type=="distributor")
{$tablename="distributor";}
else if($to_user_type=="customer")
{$tablename="customers";}
else
{$tablename="shop";}

if($to_user_id==0)
{
	
	$selectRcd_VLSS1="select * from ".$tablename." where id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext="Nil";
	$usernametext="Nil";
	$district_name_VLSS1="Nil";
	
}else{
	
$selectRcd_VLSS1="select * from ".$tablename." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
}
										
$select_marketstock_VLDIST_VLSS1="select sum(received) from receipt where inv_id='$receipt_inv_id'";
$fetch_marketstock_VLDIST_VLSS1=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS1);
$result_marketstock_VLDIST_VLSS1=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS1);

if($result_marketstock_VLDIST_VLSS1[0]!=NULL){
$Total_available_stock_VLSS1=number_format($result_marketstock_VLDIST_VLSS1[0],2,'.','');
}else{$Total_available_stock_VLSS1="0";}

$Total_available_stock123_VLSS1+=$Total_available_stock_VLSS1;


// Prepare CSV row
    $csv_content .= '"' . $inv_number . '",' .
                    '"' . $inv_date. '",' .
					'"' . $to_user_type. '",' .
					'"' . $useridtext. '",' .
					'"' . $usernametext. '",' .
					'"' . $district_name_VLSS1. '",' .
                    '"' . $Total_available_stock_VLSS1 . "\"\n";

										
										}
	
	
}



//-----------------------------------------------------------usr3-------------------------------------
if($_REQUEST['lable']==3){
	
	// Header row for CSV
$csv_content .= "Inv Num, Date, Type,ID,Name, District,Amount\n";



$select_market_SSCASH_VLSS_THISMONTH="select distinct inv_id from receipt where from_user_type='$usertypevl' and date between '$start_date' and '$endDate'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$receipt_inv_id=$result_market_SSCASH_VLSS_THISMONTH['inv_id'];
	
	//get invoice Number
	$select_CNT_number="select count(*) as numINVNUM from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_CNT_number=mysqli_query($db_conn,$select_CNT_number);
	$result_CNT_number=mysqli_fetch_array($fetch_CNT_number);
	
	if($result_CNT_number['numINVNUM']==1)
	{
	$select_inv_number="select * from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	$to_user_type=$result_inv_number['to_user_type'];
	$to_user_id=$result_inv_number['to_user_id'];
	
	}else{
	$select_inv_number="select * from invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	$to_user_type="customer";
	$to_user_id=$result_inv_number['customer_id'];
	}
	
	
if($to_user_type=="distributor")
{$tablename="distributor";}
else if($to_user_type=="customer")
{$tablename="customers";}
else
{$tablename="shop";}

if($to_user_id==0)
{
	
	$selectRcd_VLSS1="select * from ".$tablename." where id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext="Nil";
	$usernametext="Nil";
	$district_name_VLSS1="Nil";
	
}else{
	
$selectRcd_VLSS1="select * from ".$tablename." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
}
										
$select_marketstock_VLDIST_VLSS1="select sum(received) from receipt where inv_id='$receipt_inv_id'";
$fetch_marketstock_VLDIST_VLSS1=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS1);
$result_marketstock_VLDIST_VLSS1=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS1);

if($result_marketstock_VLDIST_VLSS1[0]!=NULL){
$Total_available_stock_VLSS1=number_format($result_marketstock_VLDIST_VLSS1[0],2,'.','');
}else{$Total_available_stock_VLSS1="0";}

$Total_available_stock123_VLSS1+=$Total_available_stock_VLSS1;


// Prepare CSV row
    $csv_content .= '"' . $inv_number . '",' .
                    '"' . $inv_date. '",' .
					'"' . $to_user_type. '",' .
					'"' . $useridtext. '",' .
					'"' . $usernametext. '",' .
					'"' . $district_name_VLSS1. '",' .
                    '"' . $Total_available_stock_VLSS1 . "\"\n";

										
										}
	
}




//-----------------------------------------------------------usr4-------------------------------------
if($_REQUEST['modelval']=="usr4"){
	
	// Header row for CSV
$csv_content .= "User ID, Name, District, Taluk, Outstanding\n";



$selectRcd_VLSS4="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS4=mysqli_query($db_conn,$selectRcd_VLSS4);
										while($resultRcd_VLSS4=mysqli_fetch_array($fetchRcd_VLSS4))
										{
											
											$state_id_VLSS4=$resultRcd_VLSS4['state_id'];
											$district_id_VLSS4=$resultRcd_VLSS4['district_id'];

										$selectrecords_VLSS4="select * from district where state_id='$state_id_VLSS4' and id='$district_id_VLSS4'";
										$fetchrecords_VLSS4=mysqli_query($db_conn,$selectrecords_VLSS4);
										$resultrecords_VLSS4=mysqli_fetch_array($fetchrecords_VLSS4);
										$district_name_VLSS4=$resultrecords_VLSS4['dist_name'];
										
										$taluk_id_VLSS4=$resultRcd_VLSS4['taluk_id'];
										
$selectrecords_VLSS4_taluk="select * from taluk where state_id='$state_id_VLSS4' and dist_id='$district_id_VLSS4' and id='$taluk_id_VLSS4'";
$fetchrecords_VLSS4_taluk=mysqli_query($db_conn,$selectrecords_VLSS4_taluk);
$resultrecords_VLSS4_taluk=mysqli_fetch_array($fetchrecords_VLSS4_taluk);
$taluk_name_VLSS4=$resultrecords_VLSS4_taluk['taluk'];
										
$select_marketstock_VLDIST_VLSS4="select sum(receivable) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS4['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS4=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS4);
$result_marketstock_VLDIST_VLSS4=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS4);

if($result_marketstock_VLDIST_VLSS4[0]!=NULL){
$Total_available_stock_VLSS4=number_format($result_marketstock_VLDIST_VLSS4[0],2,'.','');
}else{$Total_available_stock_VLSS4="0";}
$Total_available_stock123_VLSS4+=$Total_available_stock_VLSS4;

if($Total_available_stock_VLSS4>0)
{
	
	
							$csv_content .='"' . $resultRcd_VLSS4["useridtext"]. '",' .
							'"' . $resultRcd_VLSS4["name"]. '",' .
							'"' . $district_name_VLSS4. '",' .
							'"' . $taluk_name_VLSS4. '",' .
							'"' . $Total_available_stock_VLSS4 . "\"\n";	
										
										}}
	
}



										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>