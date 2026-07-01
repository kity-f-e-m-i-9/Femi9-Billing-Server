<?php
  error_reporting(E_ALL);

//Once reached target purchase values - than send commission amount to referred by user (Distributor, Super Distributor)

date_default_timezone_set("Asia/Kolkata");
$Current_wallet_date=date("Y-m-d");

//1 
$one_month_before=date ("Y-m-01", strtotime("-1 month", strtotime($Current_wallet_date)));
$numberOfDays_1month = date('t',strtotime($one_month_before));
$last_month_name = date('M',strtotime($one_month_before));
$last_month_YEAR = date('Y',strtotime($one_month_before));

$from_date_one=$one_month_before;
$to_date_one=date("Y-m-".$numberOfDays_1month."",strtotime($one_month_before));

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

//GET REFERRAL DETAILS
$select_Referaral_dtails="select * from distributor_referral where distributor_id='$Login_user_IDvl'";
$fetch_Referaral_dtails=mysqli_query($db_conn,$select_Referaral_dtails);
$count_refferral=mysqli_num_rows($fetch_Referaral_dtails);

$result_Referaral_dtails=mysqli_fetch_array($fetch_Referaral_dtails);

$referral_userTYPE=$result_Referaral_dtails['ref_by_user_type'];

if($referral_userTYPE!='company' && $count_refferral==1)
{
$referral_userID=mysqli_real_escape_string($db_conn, $result_Referaral_dtails['ref_by_user_id']);

if($referral_userTYPE=="super_distributor"){$tablename_USER="super_distributor";}
if($referral_userTYPE=="distributor"){$tablename_USER="distributor";}
if($referral_userTYPE=="super_stockiest"){$tablename_USER="super_stockiest";}
if($referral_userTYPE=="stockiest"){$tablename_USER="stockiest";}

$select_USER_Records="select temp_id from ".$tablename_USER." where useridtext='$referral_userID'";
$fetch_USER_Records=mysqli_query($db_conn,$select_USER_Records);
$result_USER_Records=mysqli_fetch_array($fetch_USER_Records);
$referral_userID_TEMP=$result_USER_Records['temp_id'];

$target_amount=$result_Referaral_dtails['target_amount'];
$select_StockistCategory="select * from distributor_category where amount='$target_amount'";
$fetch_StockistCategory=mysqli_query($db_conn,$select_StockistCategory);
$result_StockistCategory=mysqli_fetch_array($fetch_StockistCategory);
//
$Stockist_Target_sls_Amount=$result_StockistCategory['amount'];
$Stockist_ref_commission_percentage=$result_StockistCategory['ref_commission_percentage'];

if($Overall_sls_Amount_RPT1>=$Stockist_Target_sls_Amount)
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


} //!=company


?>