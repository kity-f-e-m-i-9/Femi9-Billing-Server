<?php
mysqli_query($db_conn,"DELETE FROM temp_not_purchased where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'");

//insert temp not purchase report
//--------------------------------------------------------------------------
//super stockist
$tousertype_non_purcah="super_stockiest"; //user-type 

$select_nonpurchase_superstockist="select * from super_stockiest order by id asc";
$fetch_nonpurchase_superstockist=mysqli_query($db_conn,$select_nonpurchase_superstockist);
while($result_nonpurchase_superstockist=mysqli_fetch_array($fetch_nonpurchase_superstockist))
{
	$NNP_superstockist_ID=$result_nonpurchase_superstockist['temp_id'];
	$select_count_nonpurchase_superstockist="select count(*) as numnnss from user_invoice where to_user_id='$NNP_superstockist_ID' and to_user_type='$tousertype_non_purcah'";
	$fetch_count_nonpurchase_superstockist=mysqli_query($db_conn,$select_count_nonpurchase_superstockist);
	$result_count_nonpurchase_superstockist=mysqli_fetch_array($fetch_count_nonpurchase_superstockist);
	$Total_purcahse_count_superstockist=$result_count_nonpurchase_superstockist['numnnss'];
	
	$SLCT_SS_cnt="select count(*) as numnnss23 from temp_not_purchased where userid='$NNP_superstockist_ID' and usertype='$tousertype_non_purcah' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
	$fetch_SS_cnt=mysqli_query($db_conn,$SLCT_SS_cnt);
	$REsult_SS_cnt=mysqli_fetch_array($fetch_SS_cnt);
	if($REsult_SS_cnt['numnnss23']==0)
	{
		$insert_report_nonpurcahse_superstockist="insert into temp_not_purchased (usertype,userid,purchse_count,searchtype,onboard_userTYPE,onboard_userID) values ('$tousertype_non_purcah','$NNP_superstockist_ID','$Total_purcahse_count_superstockist','nil','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_report_nonpurcahse_superstockist);
		
	}
	
}

//--------------------------------------------------------------------------
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
	
	$SLCT_SS_cnt="select count(*) as numnnss23 from temp_not_purchased where userid='$NNP_stockiest_ID' and usertype='$tousertype_non_purcah23' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
	$fetch_SS_cnt=mysqli_query($db_conn,$SLCT_SS_cnt);
	$REsult_SS_cnt=mysqli_fetch_array($fetch_SS_cnt);
	if($REsult_SS_cnt['numnnss23']==0)
	{
		$insert_report_nonpurcahse_stockiest="insert into temp_not_purchased (usertype,userid,purchse_count,searchtype,onboard_userTYPE,onboard_userID) values ('$tousertype_non_purcah23','$NNP_stockiest_ID','$Total_purcahse_count_stockiest','nil','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_report_nonpurcahse_stockiest);
		
	}
	
}


//--------------------------------------------------------------------------
//distributor
$tousertype_non_purcah3_DTUSR="distributor"; //user-type 

$select_nonpurchase_DTUSR="select * from distributor where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
$fetch_nonpurchase_DTUSR=mysqli_query($db_conn,$select_nonpurchase_DTUSR);
while($result_nonpurchase_DTUSR=mysqli_fetch_array($fetch_nonpurchase_DTUSR))
{
	$NNP_DTUSR_ID=$result_nonpurchase_DTUSR['temp_id'];
	$select_count_nonpurchase_DTUSR="select count(*) as numnnss from user_invoice where to_user_id='$NNP_DTUSR_ID' and to_user_type='$tousertype_non_purcah3_DTUSR'";
	$fetch_count_nonpurchase_DTUSR=mysqli_query($db_conn,$select_count_nonpurchase_DTUSR);
	$result_count_nonpurchase_DTUSR=mysqli_fetch_array($fetch_count_nonpurchase_DTUSR);
	$Total_purcahse_count_DTUSR=$result_count_nonpurchase_DTUSR['numnnss'];
	
	$SLCT_DTUSR_cnt="select count(*) as numnnss23 from temp_not_purchased where userid='$NNP_DTUSR_ID' and usertype='$tousertype_non_purcah3_DTUSR' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
	$fetch_DTUSR_cnt=mysqli_query($db_conn,$SLCT_DTUSR_cnt);
	$REsult_DTUSR_cnt=mysqli_fetch_array($fetch_DTUSR_cnt);
	if($REsult_DTUSR_cnt['numnnss23']==0)
	{
		$insert_report_nonpurcahse_DTUSR="insert into temp_not_purchased (usertype,userid,purchse_count,searchtype,onboard_userTYPE,onboard_userID) values ('$tousertype_non_purcah3_DTUSR','$NNP_DTUSR_ID','$Total_purcahse_count_DTUSR','nil','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_report_nonpurcahse_DTUSR);
		
	}
	
}



//--------------------------------------------------------------------------
//Super Distributor
$tousertype_non_purcah3_SDTUSR="super_distributor"; //user-type 

$select_nonpurchase_SDTUSR="select * from super_distributor where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
$fetch_nonpurchase_SDTUSR=mysqli_query($db_conn,$select_nonpurchase_SDTUSR);
while($result_nonpurchase_SDTUSR=mysqli_fetch_array($fetch_nonpurchase_SDTUSR))
{
	$NNP_SDTUSR_ID=$result_nonpurchase_SDTUSR['temp_id'];
	$select_count_nonpurchase_SDTUSR="select count(*) as numnnss from user_invoice where to_user_id='$NNP_SDTUSR_ID' and to_user_type='$tousertype_non_purcah3_SDTUSR'";
	$fetch_count_nonpurchase_SDTUSR=mysqli_query($db_conn,$select_count_nonpurchase_SDTUSR);
	$result_count_nonpurchase_SDTUSR=mysqli_fetch_array($fetch_count_nonpurchase_SDTUSR);
	$Total_purcahse_count_SDTUSR=$result_count_nonpurchase_SDTUSR['numnnss'];
	
	$SLCT_SDTUSR_cnt="select count(*) as numnnss23 from temp_not_purchased where userid='$NNP_SDTUSR_ID' and usertype='$tousertype_non_purcah3_SDTUSR' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
	$fetch_SDTUSR_cnt=mysqli_query($db_conn,$SLCT_SDTUSR_cnt);
	$REsult_SDTUSR_cnt=mysqli_fetch_array($fetch_SDTUSR_cnt);
	if($REsult_SDTUSR_cnt['numnnss23']==0)
	{
		$insert_report_nonpurcahse_SDTUSR="insert into temp_not_purchased (usertype,userid,purchse_count,searchtype,onboard_userTYPE,onboard_userID) values ('$tousertype_non_purcah3_SDTUSR','$NNP_SDTUSR_ID','$Total_purcahse_count_SDTUSR','nil','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_report_nonpurcahse_SDTUSR);
		
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
$select_count_non_DTUSER="select count(*) as numDTUSER from temp_not_purchased where usertype='$tousertype_non_purcah3_DTUSR' and purchse_count='0' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fethc_ount_non_DTUSER=mysqli_query($db_conn,$select_count_non_DTUSER);
$result_count_non_DTUSER=mysqli_fetch_array($fethc_ount_non_DTUSER);
$show_nonpur_DTUSER=$result_count_non_DTUSER['numDTUSER'];

//Super distributor
$select_count_non_SDTUSER="select count(*) as numDTUSER from temp_not_purchased where usertype='$tousertype_non_purcah3_SDTUSR' and purchse_count='0' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fethc_ount_non_SDTUSER=mysqli_query($db_conn,$select_count_non_SDTUSER);
$result_count_non_SDTUSER=mysqli_fetch_array($fethc_ount_non_SDTUSER);
$show_nonpur_SDTUSER=$result_count_non_SDTUSER['numDTUSER'];

?>