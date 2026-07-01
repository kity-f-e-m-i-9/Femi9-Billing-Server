<?php include("checksession.php"); 
error_reporting(0);

if(isset($_REQUEST['UpdateAction']))
{

$old_mobile_number=$_REQUEST['old_mobile_number'];
$new_mobile_number=$_REQUEST['new_mobile_number'];
$update_usertype=$_REQUEST['update_usertype'];

if($update_usertype=="super_stockiest")
{
	$updae_table_name="super_stockiest"; //table name	
    $redir_url="manage_ss?";	
}
else if($update_usertype=="stockiest")
{
	$updae_table_name="stockiest"; //table name
	$redir_url="overallusers_stockist?invuser=stockiest&&";	
}
else if($update_usertype=="candf")
{
	$updae_table_name="c_and_f"; //table name
	$redir_url="manage_cf?";	
}
else if($update_usertype=="super_distributor")
{
	$updae_table_name="super_distributor"; //table name
	$redir_url="super_Distributor_overallusers?invuser=super_distributor&&";	
}
else
{
	$updae_table_name="distributor"; //table name
	$redir_url="overallusers?invuser=distributor&&";	
}

$select_candidate_list="select * from ".$updae_table_name." where username='$new_mobile_number'";
$fetch_candidate_list=mysqli_query($db_conn,$select_candidate_list);
$result_candidate_list=mysqli_num_rows($fetch_candidate_list);
	
	
	if($new_mobile_number!=$old_mobile_number)
	{
		
	if($result_candidate_list==0)
	{
		$update_mobile_action="UPDATE ".$updae_table_name." SET username='$new_mobile_number',mobile_number='$new_mobile_number' WHERE username='$old_mobile_number'";
		mysqli_query($db_conn,$update_mobile_action);
		echo "<script>window.location='".$redir_url."MobileUpdatedSuccess';</script>";
	}
	else{
		echo "<script>window.location='".$redir_url."MobileAlreadyExists';</script>";
	}
	
	}else{ echo "<script>window.location='".$redir_url."Samenumbernotaccepted';</script>"; }
	
	
	
}else{ echo "<script>window.location='dashboard';</script>";}
?>
