<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Users-demo.csv";

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];

// Fetch product data from database
$select_product_list="select * from demo_awareness where date between '$from_date' and '$to_date' order by date desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Usertype,Name,District,Taluk, Mobile Number, Date, Demo Title\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {
	
	
//USER DETAILS										
$usertype=$result_product_list['usertype'];
if($usertype=="super_stockiest"){ 

$photourl="super-stockist";  $disusertype="Super Stockist";

$select_Userdetails="select * from super_stockiest where temp_id='".$result_product_list['userid']."'";
$fetch_Userdetails=mysqli_query($db_conn,$select_Userdetails);
$result_Userdetails=mysqli_fetch_array($fetch_Userdetails);

$district_id=$result_Userdetails['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=$result_district['dist_name'];

$taluk_name="Nil";

}
else if($usertype=="stockiest"){ 

$photourl="stockist"; $disusertype="Stockist";

$select_Userdetails="select * from stockiest where temp_id='".$result_product_list['userid']."'";
$fetch_Userdetails=mysqli_query($db_conn,$select_Userdetails);
$result_Userdetails=mysqli_fetch_array($fetch_Userdetails);

$district_id=$result_Userdetails['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//TALUK DETAILS
$taluk_id=$result_Userdetails['taluk_id'];
if($taluk_id!=NULL)
{
$select_Taluk12="select * from taluk where id='$taluk_id'";
$fetch_Taluk12=mysqli_query($db_conn,$select_Taluk12);
$result_Taluk12=mysqli_fetch_array($fetch_Taluk12);
if($result_Taluk12['taluk']!=NULL){
$taluk_name=$result_Taluk12['taluk'];}else{$taluk_name="";}
}

}
else
{ 

$photourl="distributor"; $disusertype="Distributor";

$select_Userdetails="select * from distributor where temp_id='".$result_product_list['userid']."'";
$fetch_Userdetails=mysqli_query($db_conn,$select_Userdetails);
$result_Userdetails=mysqli_fetch_array($fetch_Userdetails);

$district_id=$result_Userdetails['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//TALUK DETAILS
$taluk_id=$result_Userdetails['taluk_id'];
if($taluk_id!=NULL)
{
$select_Taluk12="select * from taluk where id='$taluk_id'";
$fetch_Taluk12=mysqli_query($db_conn,$select_Taluk12);
$result_Taluk12=mysqli_fetch_array($fetch_Taluk12);
if($result_Taluk12['taluk']!=NULL){
$taluk_name=$result_Taluk12['taluk'];}else{$taluk_name="";}
}

}


    // Prepare CSV row
    $csv_content .= '"' . $disusertype . '",' .
                    '"' . $result_Userdetails["name"] . '",' .
					'"' . $district_name . '",' .
					'"' . $taluk_name . '",' .
					'"' . $result_Userdetails["mobile_number"] . '",' .
					'"' . $result_product_list["date"] . '",' .
                    '"' . $result_product_list["title"] . "\"\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



