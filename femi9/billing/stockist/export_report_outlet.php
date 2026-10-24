<?php require_once __DIR__ . '/../shared/db-helpers.php';
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Outlet Visit";

if($_REQUEST['lable']==1 )
{
$DISPLAY_LABLE="Super Stockist (Stockist Visit)";  	$usertypevl="super_stockiest";
//$tblenma="super_stockiest"; 
}

else if($_REQUEST['lable']==2)
{
$DISPLAY_LABLE="Stockist (Distributor Visit)"; 			$usertypevl="stockiest";
//$tblenma="stockiest"; 
}

else
{
$DISPLAY_LABLE="Distributor (Shop Visit)";  		$usertypevl="distributor";
//$tblenma="distributor"; 
}

$start_date=$_REQUEST['frdate'];
$endDate=$_REQUEST['todate'];	
$seuser=$_REQUEST['seuser'];

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';

//-----------------------------------------------------------usr3-------------------------------------
if($_REQUEST['lable']==3){	
	
	
// Header row for CSV
$csv_content .= "Distributor ID,Distributor Name,No of Visit,District, Taluk, Pincode\n";

// Fetching data and formatting into CSV rows

if($seuser==NULL){	
$select_records="select * from distributor where onboard_userTYPE='".db_esc($Login_user_TYPEvl)."' and onboard_userID='".db_esc($Login_user_IDvl)."' order by id asc";
}else{
$select_records="select * from distributor where name LIKE '%".db_esc($seuser)."%' and onboard_userTYPE=? and onboard_userID=? order by id asc";	
}	
$exe_records=db_q($db_conn,$select_records,[$Login_user_TYPEvl,$Login_user_IDvl]);
while($fetch_records=mysqli_fetch_array($exe_records))
{
	
	$distributorID=$fetch_records['temp_id'];
	//
	if($_REQUEST['indistinct']==NULL)
	{
	$select_visitcnt="select count(distinct date) numvstoutlet from user_invoice where to_user_type='shop' and from_user_type='distributor' and from_user_id='".db_esc($distributorID)."'";
	}else{
	$select_visitcnt="select count(*) numvstoutlet from user_invoice where to_user_type='shop' and from_user_type='distributor' and from_user_id=?";	
	}
	$exe_visitcnt=db_q($db_conn,$select_visitcnt,[$distributorID]);
	$fetch_visitcnt=mysqli_fetch_array($exe_visitcnt);
	$Total_Visit_Count=$fetch_visitcnt['numvstoutlet'];
	
$useridtext=$fetch_records["useridtext"];
$usernametext=$fetch_records["name"];

$stateid=$fetch_records['state_id'];
$distid=$fetch_records['district_id'];
						
//District Details						
$selectrecords_VLSS1="select * from district where state_id=? and id=?";
$fetchrecords_VLSS1=db_q($db_conn,$selectrecords_VLSS1,[$stateid,$distid]);
$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
				
//Taluk Details				
$talukid=$fetch_records['taluk_id'];
$selectrecordstaluk="select * from taluk where state_id=? and dist_id=? and id=?";
$fetchrecords_VLSS2_taluk=db_q($db_conn,$selectrecordstaluk,[$stateid,$distid,$talukid]);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										
//Pincode Details				
$pincodeid=$fetch_records['pincode_id'];
$selectrecordspincode="select * from pincode where state_id=? and dist_id=? and taluk_id=? and id=?";
$exerecordspincode=db_q($db_conn,$selectrecordspincode,[$stateid,$distid,$talukid,$pincodeid]);
$fetchrecordspincode=mysqli_fetch_array($exerecordspincode);
$pincodeshow=$fetchrecordspincode['pincode'];

if($Total_Visit_Count>0)
{


    // Prepare CSV row
    $csv_content .= '"' . $useridtext . '",' .
                    '"' . $usernametext. '",' .
					'"' . $Total_Visit_Count. '",' .
					'"' . $district_name_VLSS1. '",' .
					'"' . $taluk_name_VLSS2. '",' .
                    '"' . $pincodeshow . "\"\n";
					
					
}
					
					
					
}
										
}





										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>