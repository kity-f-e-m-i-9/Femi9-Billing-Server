<?php
mysqli_query($db_conn,"DELETE FROM temp_not_purchased where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'");

//insert temp not purchase report


//stockist
$tousertype_non_purcah23="stockiest"; //user-type 

$select_nonpurchase_stockiest="select * from stockiest where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
$fetch_nonpurchase_stockiest=mysqli_query($db_conn,$select_nonpurchase_stockiest);
while($result_nonpurchase_stockiest=mysqli_fetch_array($fetch_nonpurchase_stockiest))
{
	$NNP_stockiest_ID=$result_nonpurchase_stockiest['temp_id'];
	$select_count_nonpurchase_stockiest="select count(*) as numnnss from user_invoice where to_user_id='$NNP_stockiest_ID' and to_user_type='$tousertype_non_purcah23'";
	$fetch_count_nonpurchase_stockiest=mysqli_query($db_conn,$select_count_nonpurchase_stockiest);
	$result_count_nonpurchase_stockiest=mysqli_fetch_array($fetch_count_nonpurchase_stockiest);
	$Total_purcahse_count_stockiest=$result_count_nonpurchase_stockiest['numnnss'];
	
	$SLCT_SS_cnt="select count(*) as numnnss23 from temp_not_purchased where userid='$NNP_stockiest_ID' and usertype='$tousertype_non_purcah23'";
	$fetch_SS_cnt=mysqli_query($db_conn,$SLCT_SS_cnt);
	$REsult_SS_cnt=mysqli_fetch_array($fetch_SS_cnt);
	if($REsult_SS_cnt['numnnss23']==0)
	{
		$insert_report_nonpurcahse_stockiest="insert into temp_not_purchased (usertype,userid,purchse_count,searchtype,onboard_userTYPE,onboard_userID) 
		values ('$tousertype_non_purcah23','$NNP_stockiest_ID',
		'$Total_purcahse_count_stockiest','nil','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_report_nonpurcahse_stockiest);
		
	}
	
}



//distributor
$tousertype_non_purcah3="distributor"; //user-type 

$select_nonpurchase_stockiest="select * from distributor where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
$fetch_nonpurchase_stockiest=mysqli_query($db_conn,$select_nonpurchase_stockiest);
while($result_nonpurchase_stockiest=mysqli_fetch_array($fetch_nonpurchase_stockiest))
{
	$NNP_stockiest_ID=$result_nonpurchase_stockiest['temp_id'];
	$select_count_nonpurchase_stockiest="select count(*) as numnnss from user_invoice where to_user_id='$NNP_stockiest_ID' and to_user_type='$tousertype_non_purcah3'";
	$fetch_count_nonpurchase_stockiest=mysqli_query($db_conn,$select_count_nonpurchase_stockiest);
	$result_count_nonpurchase_stockiest=mysqli_fetch_array($fetch_count_nonpurchase_stockiest);
	$Total_purcahse_count_stockiest=$result_count_nonpurchase_stockiest['numnnss'];
	
	$SLCT_SS_cnt="select count(*) as numnnss23 from temp_not_purchased where userid='$NNP_stockiest_ID' and usertype='$tousertype_non_purcah3'";
	$fetch_SS_cnt=mysqli_query($db_conn,$SLCT_SS_cnt);
	$REsult_SS_cnt=mysqli_fetch_array($fetch_SS_cnt);
	if($REsult_SS_cnt['numnnss23']==0)
	{
		$insert_report_nonpurcahse_stockiest="insert into temp_not_purchased (usertype,userid,purchse_count,searchtype,onboard_userTYPE,onboard_userID) 
		values ('$tousertype_non_purcah3','$NNP_stockiest_ID',
		'$Total_purcahse_count_stockiest','nil','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_report_nonpurcahse_stockiest);
		
	}
	
}


//--------------end **------------------------------------------



//super stockist
$select_count_non_SSUSER="select count(*) as numSSUSER from temp_not_purchased where usertype='$tousertype_non_purcah' and purchse_count='0' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fethc_ount_non_SSUSER=mysqli_query($db_conn,$select_count_non_SSUSER);
$result_count_non_SSUSER=mysqli_fetch_array($fethc_ount_non_SSUSER);
$show_nonpur_SSUSER=$result_count_non_SSUSER['numSSUSER'];

//stockist
$select_count_non_STUSER="select count(*) as numSTUSER from temp_not_purchased where usertype='$tousertype_non_purcah23' and purchse_count='0' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fethc_ount_non_STUSER=mysqli_query($db_conn,$select_count_non_STUSER);
$result_count_non_STUSER=mysqli_fetch_array($fethc_ount_non_STUSER);
$show_nonpur_STUSER=$result_count_non_STUSER['numSTUSER'];


//distributor
$select_count_non_DTUSER="select count(*) as numDTUSER from temp_not_purchased where usertype='$tousertype_non_purcah3' and purchse_count='0' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fethc_ount_non_DTUSER=mysqli_query($db_conn,$select_count_non_DTUSER);
$result_count_non_DTUSER=mysqli_fetch_array($fethc_ount_non_DTUSER);
$show_nonpur_DTUSER=$result_count_non_DTUSER['numDTUSER'];

?>