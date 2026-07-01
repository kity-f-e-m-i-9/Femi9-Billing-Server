<?php


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


?>