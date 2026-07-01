<?php
//SUPER STOCKIST OUTSTANDING
$select_outstanding_SS_RCVD="select sum(received) from receipt where to_user_type='super_stockiest'";
$fetch_outstanding_SS_RCVD=mysqli_query($db_conn,$select_outstanding_SS_RCVD);
$result_outstanding_SS_RCVD=mysqli_fetch_array($fetch_outstanding_SS_RCVD);

if($result_outstanding_SS_RCVD[0]!=NULL)
{ $SS_received_amount=$result_outstanding_SS_RCVD[0];}else{ $SS_received_amount="0";}

$select_outstanding_SS_RCVBL="select sum(total) from user_invoice where to_user_type='super_stockiest'";
$fetch_outstanding_SS_RCVBL=mysqli_query($db_conn,$select_outstanding_SS_RCVBL);
$result_outstanding_SS_RCVBL=mysqli_fetch_array($fetch_outstanding_SS_RCVBL);

if($result_outstanding_SS_RCVBL[0]!=NULL)
{ $SS_receivable_amount=$result_outstanding_SS_RCVBL[0];}else{ $SS_receivable_amount="0";}

$Total_SS_outstanding=$SS_receivable_amount-$SS_received_amount;


//STOCKIST OUTSTANDING
$select_outstanding_ST_RCVD="select sum(received) from receipt where to_user_type='stockiest'";
$fetch_outstanding_ST_RCVD=mysqli_query($db_conn,$select_outstanding_ST_RCVD);
$result_outstanding_ST_RCVD=mysqli_fetch_array($fetch_outstanding_ST_RCVD);

if($result_outstanding_ST_RCVD[0]!=NULL)
{ $ST_received_amount=$result_outstanding_ST_RCVD[0];}else{ $ST_received_amount="0";}

$select_outstanding_ST_RCVBL="select sum(total) from user_invoice where to_user_type='stockiest'";
$fetch_outstanding_ST_RCVBL=mysqli_query($db_conn,$select_outstanding_ST_RCVBL);
$result_outstanding_ST_RCVBL=mysqli_fetch_array($fetch_outstanding_ST_RCVBL);

if($result_outstanding_ST_RCVBL[0]!=NULL)
{ $ST_receivable_amount=$result_outstanding_ST_RCVBL[0];}else{ $ST_receivable_amount="0";}

$Total_ST_outstanding=$ST_receivable_amount-$ST_received_amount;


//DISTRIBUTOR OUTSTANDING
$select_outstanding_DT_RCVD="select sum(received) from receipt where to_user_type='distributor'";
$fetch_outstanding_DT_RCVD=mysqli_query($db_conn,$select_outstanding_DT_RCVD);
$result_outstanding_DT_RCVD=mysqli_fetch_array($fetch_outstanding_DT_RCVD);

if($result_outstanding_DT_RCVD[0]!=NULL)
{ $DT_received_amount=$result_outstanding_DT_RCVD[0];}else{ $DT_received_amount="0";}

$select_outstanding_DT_RCVBL="select sum(total) from user_invoice where to_user_type='distributor'";
$fetch_outstanding_DT_RCVBL=mysqli_query($db_conn,$select_outstanding_DT_RCVBL);
$result_outstanding_DT_RCVBL=mysqli_fetch_array($fetch_outstanding_DT_RCVBL);

if($result_outstanding_DT_RCVBL[0]!=NULL)
{ $DT_receivable_amount=$result_outstanding_DT_RCVBL[0];}else{ $DT_receivable_amount="0";}

$Total_DT_outstanding=$DT_receivable_amount-$DT_received_amount;


//SHOP OUTSTANDING
$select_outstanding_SHP_RCVD="select sum(received) from receipt where to_user_type='shop'";
$fetch_outstanding_SHP_RCVD=mysqli_query($db_conn,$select_outstanding_SHP_RCVD);
$result_outstanding_SHP_RCVD=mysqli_fetch_array($fetch_outstanding_SHP_RCVD);

if($result_outstanding_SHP_RCVD[0]!=NULL)
{ $SHP_received_amount=$result_outstanding_SHP_RCVD[0];}else{ $SHP_received_amount="0";}

$select_outstanding_SHP_RCVBL="select sum(total) from user_invoice where to_user_type='shop'";
$fetch_outstanding_SHP_RCVBL=mysqli_query($db_conn,$select_outstanding_SHP_RCVBL);
$result_outstanding_SHP_RCVBL=mysqli_fetch_array($fetch_outstanding_SHP_RCVBL);

if($result_outstanding_SHP_RCVBL[0]!=NULL)
{ $SHP_receivable_amount=$result_outstanding_SHP_RCVBL[0];}else{ $SHP_receivable_amount="0";}

$Total_SHP_outstanding=$SHP_receivable_amount-$SHP_received_amount;


//SUPER DISTRIBUTOR OUTSTANDING
$select_outstanding_SDT_RCVD="select sum(received) from receipt where to_user_type='super_distributor'";
$fetch_outstanding_SDT_RCVD=mysqli_query($db_conn,$select_outstanding_SDT_RCVD);
$result_outstanding_SDT_RCVD=mysqli_fetch_array($fetch_outstanding_SDT_RCVD);

if($result_outstanding_SDT_RCVD[0]!=NULL)
{ $SDT_received_amount=$result_outstanding_SDT_RCVD[0];}else{ $SDT_received_amount="0";}

$select_outstanding_SDT_RCVBL="select sum(total) from user_invoice where to_user_type='super_distributor'";
$fetch_outstanding_SDT_RCVBL=mysqli_query($db_conn,$select_outstanding_SDT_RCVBL);
$result_outstanding_SDT_RCVBL=mysqli_fetch_array($fetch_outstanding_SDT_RCVBL);

if($result_outstanding_SDT_RCVBL[0]!=NULL)
{ $SDT_receivable_amount=$result_outstanding_SDT_RCVBL[0];}else{ $SDT_receivable_amount="0";}

$Total_SDT_outstanding=$SDT_receivable_amount-$SDT_received_amount;
?>