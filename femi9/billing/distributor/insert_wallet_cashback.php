<?php

// Note:-
//pre 2 months continues reahced target purchase value if add cashback to stockist

$cashback_month=date("M",strtotime($Current_wallet_date));
$cashback_year=date("Y",strtotime($Current_wallet_date));

// if current month Dec 
//This is a Nov Month
$from_date_one_CB=date ("Y-m-01", strtotime("-1 month", strtotime($Current_wallet_date)));
$numberOfDays_1month_CB = date('t',strtotime($from_date_one_CB));
$to_date_one_CB=date("Y-m-".$numberOfDays_1month_CB."",strtotime($from_date_one_CB));

//This is a Oct Month
$from_date_two_CB=date ("Y-m-01", strtotime("-1 month", strtotime($from_date_one_CB)));
$numberOfDays_2month_CB = date('t',strtotime($from_date_two_CB));
$to_date_two_CB=date("Y-m-".$numberOfDays_2month_CB."",strtotime($from_date_two_CB));

//---------------------------------------------------------------------------------
//(1) Total Purchase values
$select_inovice_sls_RPT1_USR_CB="select sum(total) from user_invoice where to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl' and date between '$from_date_one_CB' and '$to_date_one_CB'";
$fetch_inovice_sls_RPT1_USR_CB=mysqli_query($db_conn,$select_inovice_sls_RPT1_USR_CB);
$result_inovice_sls_RPT1_USR_CB=mysqli_fetch_array($fetch_inovice_sls_RPT1_USR_CB);
$sls_RPT1_USR_CB=$result_inovice_sls_RPT1_USR_CB[0] ?? '0';

//(1) Total Purcahse Return Values
$select_inovice_slsrtn_RPT1_CB="select sum(total) from user_return_stock where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' and date between '$from_date_one_CB' and '$to_date_one_CB'";
$fetch_inovice_slsrtn_RPT1_CB=mysqli_query($db_conn,$select_inovice_slsrtn_RPT1_CB);
$result_inovice_slsrtn_RPT1_CB=mysqli_fetch_array($fetch_inovice_slsrtn_RPT1_CB);
$slsrtn_RPT1_CB=$result_inovice_slsrtn_RPT1_CB[0] ?? '0';

$Overall_sls_Amount_RPT1_CB=$sls_RPT1_USR_CB-$slsrtn_RPT1_CB;

//----------------------------------------------------------------------------------
//(2) Total Purchase values
$select_inovice_sls_RPT1_USR_CB_TWO="select sum(total) from user_invoice where to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl' and date between '$from_date_two_CB' and '$to_date_two_CB'";
$fetch_inovice_sls_RPT1_USR_CB_TWO=mysqli_query($db_conn,$select_inovice_sls_RPT1_USR_CB_TWO);
$result_inovice_sls_RPT1_USR_CB_TWO=mysqli_fetch_array($fetch_inovice_sls_RPT1_USR_CB_TWO);
$sls_RPT1_USR_CB_TWO=$result_inovice_sls_RPT1_USR_CB_TWO[0] ?? '0';

//(2) Total Purcahse Return Values
$select_inovice_slsrtn_RPT1_CB_TWO="select sum(total) from user_return_stock where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' and date between '$from_date_two_CB' and '$to_date_two_CB'";
$fetch_inovice_slsrtn_RPT1_CB_TWO=mysqli_query($db_conn,$select_inovice_slsrtn_RPT1_CB_TWO);
$result_inovice_slsrtn_RPT1_CB_TWO=mysqli_fetch_array($fetch_inovice_slsrtn_RPT1_CB_TWO);
$slsrtn_RPT1_CB_TWO=$result_inovice_slsrtn_RPT1_CB_TWO[0] ?? '0';

$Overall_sls_Amount_RPT1_CB_TWO=$sls_RPT1_USR_CB_TWO-$slsrtn_RPT1_CB_TWO;

//-----------------------------------------------------------------------------------------------

$select_Referaral_dtails="select * from distributor_referral where distributor_id='$Login_user_IDvl'";
$fetch_Referaral_dtails=mysqli_query($db_conn,$select_Referaral_dtails);
$count_referral_details=mysqli_num_rows($fetch_Referaral_dtails);

if($count_referral_details==1)
{
	
$result_Referaral_dtails=mysqli_fetch_array($fetch_Referaral_dtails);

$target_amount=$result_Referaral_dtails['target_amount'];
$select_StockistCategory="select * from distributor_category where amount='$target_amount'";
$fetch_StockistCategory=mysqli_query($db_conn,$select_StockistCategory);
$result_StockistCategory=mysqli_fetch_array($fetch_StockistCategory);

$Stockist_Target_sls_Amount=$result_StockistCategory['amount'];
$Stockist_ref_commission_percentage=$result_StockistCategory['cash_back_percentage'];


if($Overall_sls_Amount_RPT1_CB>=$Stockist_Target_sls_Amount && $Overall_sls_Amount_RPT1_CB_TWO>=$Stockist_Target_sls_Amount)
{
	
$select_Stockist_Wallet_Records_CB="select * from wallet_monthly_sls_report where refer_by_usertype='$Login_user_TYPEvl' and refer_by_userid='$Login_user_IDvl' and from_date='$from_date_one_CB' and to_date='$to_date_two_CB' and commission_type='Cashback'";
$fetch_Stockist_Wallet_Records_CB=mysqli_query($db_conn,$select_Stockist_Wallet_Records_CB);

if(mysqli_num_rows($fetch_Stockist_Wallet_Records_CB)==0)
{
	
$Remarks_CB="Total Purchase Values <br/>
".date('d-m-Y',strtotime($from_date_one_CB))." (to) ".date('d-m-Y',strtotime($to_date_one_CB))." : ".$Overall_sls_Amount_RPT1_CB."<br/>
".date('d-m-Y',strtotime($from_date_two_CB))." (to) ".date('d-m-Y',strtotime($to_date_two_CB))." : ".$Overall_sls_Amount_RPT1_CB_TWO."
";
	
	$Total_Commission_Amount=$Overall_sls_Amount_RPT1_CB_TWO*$Stockist_ref_commission_percentage/100;
	$Total_Commission_Amount=round($Total_Commission_Amount);
	
	$Insert_wallet_records="INSERT INTO wallet_monthly_sls_report (user_type,user_id,from_date,to_date,month,year,total_sls_amount,target_sls_amount,
	target_reached,refer_by_usertype,refer_by_userid,commission_percentage,
	commission_amount,commission_type,remarks) 
	VALUES ('Nill','Nill',
	'$from_date_one_CB','$to_date_two_CB','$cashback_month','$cashback_year','0',
	'$Stockist_Target_sls_Amount','yes','$Login_user_TYPEvl','$Login_user_IDvl',
	'$Stockist_ref_commission_percentage','$Total_Commission_Amount','Cashback','$Remarks_CB')";
	mysqli_query($db_conn,$Insert_wallet_records);
}

}

}

?>