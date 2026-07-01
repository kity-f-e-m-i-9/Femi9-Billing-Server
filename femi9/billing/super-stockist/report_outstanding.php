<?php
//STOCKIST OUTSTANDING
$select_outstanding_ST_RCVD="select sum(received) from receipt where to_user_type='stockiest' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_outstanding_ST_RCVD=mysqli_query($db_conn,$select_outstanding_ST_RCVD);
$result_outstanding_ST_RCVD=mysqli_fetch_array($fetch_outstanding_ST_RCVD);

if($result_outstanding_ST_RCVD[0]!=NULL)
{ $ST_received_amount=$result_outstanding_ST_RCVD[0];}else{ $ST_received_amount="0";}

$select_outstanding_ST_RCVBL="select sum(total) from user_invoice where to_user_type='stockiest' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_outstanding_ST_RCVBL=mysqli_query($db_conn,$select_outstanding_ST_RCVBL);
$result_outstanding_ST_RCVBL=mysqli_fetch_array($fetch_outstanding_ST_RCVBL);

if($result_outstanding_ST_RCVBL[0]!=NULL)
{ $ST_receivable_amount=$result_outstanding_ST_RCVBL[0];}else{ $ST_receivable_amount="0";}

$Total_ST_outstanding=$ST_receivable_amount-$ST_received_amount;


//DISTRIBUTOR OUTSTANDING
$select_outstanding_DT_RCVD="select sum(received) from receipt where to_user_type='distributor' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_outstanding_DT_RCVD=mysqli_query($db_conn,$select_outstanding_DT_RCVD);
$result_outstanding_DT_RCVD=mysqli_fetch_array($fetch_outstanding_DT_RCVD);

if($result_outstanding_DT_RCVD[0]!=NULL)
{ $DT_received_amount=$result_outstanding_DT_RCVD[0];}else{ $DT_received_amount="0";}

$select_outstanding_DT_RCVBL="select sum(total) from user_invoice where to_user_type='distributor' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_outstanding_DT_RCVBL=mysqli_query($db_conn,$select_outstanding_DT_RCVBL);
$result_outstanding_DT_RCVBL=mysqli_fetch_array($fetch_outstanding_DT_RCVBL);

if($result_outstanding_DT_RCVBL[0]!=NULL)
{ $DT_receivable_amount=$result_outstanding_DT_RCVBL[0];}else{ $DT_receivable_amount="0";}

$Total_DT_outstanding=$DT_receivable_amount-$DT_received_amount;


//SHOP OUTSTANDING
$select_outstanding_SHP_RCVD="select sum(received) from receipt where to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_outstanding_SHP_RCVD=mysqli_query($db_conn,$select_outstanding_SHP_RCVD);
$result_outstanding_SHP_RCVD=mysqli_fetch_array($fetch_outstanding_SHP_RCVD);

if($result_outstanding_SHP_RCVD[0]!=NULL)
{ $SHP_received_amount=$result_outstanding_SHP_RCVD[0];}else{ $SHP_received_amount="0";}

$select_outstanding_SHP_RCVBL="select sum(total) from user_invoice where to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_outstanding_SHP_RCVBL=mysqli_query($db_conn,$select_outstanding_SHP_RCVBL);
$result_outstanding_SHP_RCVBL=mysqli_fetch_array($fetch_outstanding_SHP_RCVBL);

if($result_outstanding_SHP_RCVBL[0]!=NULL)
{ $SHP_receivable_amount=$result_outstanding_SHP_RCVBL[0];}else{ $SHP_receivable_amount="0";}

$Total_SHP_outstanding=$SHP_receivable_amount-$SHP_received_amount;


?>