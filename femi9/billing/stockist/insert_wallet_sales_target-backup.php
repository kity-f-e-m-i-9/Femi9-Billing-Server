<?php
/* ---- Stockiest target values ah reach pannitta avangalukku commission add agaum (intha value total purchase amount oda compara panni check pannanum.
//---- $Totaltargetvalues <= $Totalpurchasevalues) ---//
*/

date_default_timezone_set("Asia/Kolkata");
$Current_wallet_date=date("Y-m-d");

//1 
$one_month_before=date ("Y-m-01", strtotime("-1 month", strtotime($Current_wallet_date)));
$numberOfDays_1month = date('t',strtotime($one_month_before));
$last_month_name = date('M',strtotime($one_month_before));
$last_month_YEAR = date('Y',strtotime($one_month_before));
$from_date_one=$one_month_before;
$to_date_one=date("Y-m-".$numberOfDays_1month."",strtotime($one_month_before));

//2
$TWO_month_before=date ("Y-m-01", strtotime("-2 month", strtotime($Current_wallet_date)));
$numberOfDays_TWO_month = date('t',strtotime($TWO_month_before));
$from_date_TWO=$TWO_month_before;
$to_date_TWO=date("Y-m-".$numberOfDays_TWO_month."",strtotime($TWO_month_before));


//PRE MONTH---------------------------------------------------------------------------------
//PURCHASE REPORT
$select_inovice_sls_RPT1_USR="select sum(total) from user_invoice where to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl' and date between '$from_date_one' and '$to_date_one'";
$fetch_inovice_sls_RPT1_USR=mysqli_query($db_conn,$select_inovice_sls_RPT1_USR);
$result_inovice_sls_RPT1_USR=mysqli_fetch_array($fetch_inovice_sls_RPT1_USR);
$sls_RPT1_USR=$result_inovice_sls_RPT1_USR[0] ?? '0';

//PURCHASE RETURN REPORT
$select_inovice_slsrtn_RPT1="select sum(total) from user_return_stock where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' and date between '$from_date_one' and '$to_date_one'";
$fetch_inovice_slsrtn_RPT1=mysqli_query($db_conn,$select_inovice_slsrtn_RPT1);
$result_inovice_slsrtn_RPT1=mysqli_fetch_array($fetch_inovice_slsrtn_RPT1);
$slsrtn_RPT1=$result_inovice_slsrtn_RPT1[0] ?? '0';

$Overall_sls_Amount_RPT1=$sls_RPT1_USR-$slsrtn_RPT1;

//PRE MONTH BEFORE--------------------------------------------------------------------------------------
//PURCHASE REPORT
$select_inovice_sls_RPT2_USR="select sum(total) from user_invoice where to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl' and date between '$from_date_TWO' and '$to_date_TWO'";
$fetch_inovice_sls_RPT2_USR=mysqli_query($db_conn,$select_inovice_sls_RPT2_USR);
$result_inovice_sls_RPT2_USR=mysqli_fetch_array($fetch_inovice_sls_RPT2_USR);
$sls_RPT2_USR=$result_inovice_sls_RPT2_USR[0] ?? '0';

//PURCHASE RETURN REPORT
$select_inovice_slsrtn_RPT2="select sum(total) from user_return_stock where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' and date between '$from_date_TWO' and '$to_date_TWO'";
$fetch_inovice_slsrtn_RPT2=mysqli_query($db_conn,$select_inovice_slsrtn_RPT2);
$result_inovice_slsrtn_RPT2=mysqli_fetch_array($fetch_inovice_slsrtn_RPT2);
$slsrtn_RPT2=$result_inovice_slsrtn_RPT2[0] ?? '0';

$Overall_sls_Amount_RPT2=$sls_RPT2_USR-$slsrtn_RPT2;


//Get Refferal Dtails
$select_Referaral_dtails="select * from stockist_referral where stockist_id='$Login_user_IDvl'";
$fetch_Referaral_dtails=mysqli_query($db_conn,$select_Referaral_dtails);
$result_Referaral_dtails=mysqli_fetch_array($fetch_Referaral_dtails);

$referral_userTYPE=$result_Referaral_dtails['st_ref_type'];

if($referral_userTYPE!='company')
{
	
$referral_userID=$result_Referaral_dtails['st_ref_userid'];

if($referral_userTYPE=="super_stockiest")
{
	//super_stockiest
	$tablename_USER="super_stockiest";
	}
else if($referral_userTYPE=="stockiest")
{
	//stockiest
	$tablename_USER="stockiest";
	}
else 
{
	//distributor
	$tablename_USER="distributor";
}

$select_USER_Records="select temp_id from ".$tablename_USER." where useridtext='$referral_userID'";
$fetch_USER_Records=mysqli_query($db_conn,$select_USER_Records);
$result_USER_Records=mysqli_fetch_array($fetch_USER_Records);
$referral_userID_TEMP=$result_USER_Records['temp_id'];

$Stockist_CategoryID=$result_Referaral_dtails['st_cat_id'];
$select_StockistCategory="select * from stockist_category where id='$Stockist_CategoryID'";
$fetch_StockistCategory=mysqli_query($db_conn,$select_StockistCategory);
$result_StockistCategory=mysqli_fetch_array($fetch_StockistCategory);
//
$Stockist_Target_sls_Amount=$result_StockistCategory['target_amount'];
$Stockist_ref_commission_percentage=$result_StockistCategory['ref_commission_percentage'];

if($Overall_sls_Amount_RPT1>=$Stockist_Target_sls_Amount && $Overall_sls_Amount_RPT2>=$Stockist_Target_sls_Amount)
{
		
$select_Stockist_Wallet_Records="select * from wallet_monthly_sls_report where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and from_date='$from_date_one' and to_date='$to_date_one' and commission_type='Refferral'";
$fetch_Stockist_Wallet_Records=mysqli_query($db_conn,$select_Stockist_Wallet_Records);
$count_Stockist_Wallet_Records=mysqli_num_rows($fetch_Stockist_Wallet_Records);
$result_Stockist_Wallet_Records=mysqli_fetch_array($fetch_Stockist_Wallet_Records);

if($count_Stockist_Wallet_Records==0)
{
	
	$Total_Commission_Amount=$Overall_sls_Amount_RPT1*$Stockist_ref_commission_percentage/100;
	$Total_Commission_Amount=round($Total_Commission_Amount);
	
	$Insert_wallet_records="INSERT INTO wallet_monthly_sls_report (user_type,user_id,from_date,to_date,month,year,total_sls_amount,target_sls_amount,
	target_reached,refer_by_usertype,refer_by_userid,commission_percentage,
	commission_amount,commission_type,remarks) 
	VALUES ('$Login_user_TYPEvl','$Login_user_IDvl',
	'$from_date_one','$to_date_one','$last_month_name','$last_month_YEAR','$Overall_sls_Amount_RPT1',
	'$Stockist_Target_sls_Amount','yes','$referral_userTYPE','$referral_userID_TEMP',
	'$Stockist_ref_commission_percentage','$Total_Commission_Amount','Refferral','Nil')";
	mysqli_query($db_conn,$Insert_wallet_records);
}

}

}//!=company

?>