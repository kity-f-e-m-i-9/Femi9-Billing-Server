<?php
//-------------------------------------Today---------------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today Invoice Count
$select_count_invoice_today_channel="select count(distinct tempid) as numinvoicetdy from ot_sales where date='$today_date'";
$fetch_count_invoice_today_channel=mysqli_query($db_conn,$select_count_invoice_today_channel);
$result_count_invoice_today_channel=mysqli_fetch_array($fetch_count_invoice_today_channel);

$today_invoice_count_channel=$result_count_invoice_today_channel['numinvoicetdy'];

//Today Product Qty
$select_prqty_today_channel="select sum(qty) from ot_sales where date='$today_date'";
$fetch_prqty_today_channel=mysqli_query($db_conn,$select_prqty_today_channel);
$result_prqty_today_channel=mysqli_fetch_array($fetch_prqty_today_channel);

if($result_prqty_today_channel[0]!=NULL){
$today_total_qty_channel=$result_prqty_today_channel[0];}else{$today_total_qty_channel="0";}

//Today Total Amount
$select_totalamount_today_channel="select sum(total) from ot_sales where date='$today_date'";
$fetch_totalamount_today_channel=mysqli_query($db_conn,$select_totalamount_today_channel);
$result_totalamount_today_channel=mysqli_fetch_array($fetch_totalamount_today_channel);

if($result_totalamount_today_channel[0]!=NULL){
$today_total_amount_channel=number_format($result_totalamount_today_channel[0],2,'.','');
}else{$today_total_amount_channel="0.00";}


/*
//-------------------------------------Yesterday-----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//yesterday Invoice Count
$select_count_invoice_yesterday_channel="select count(distinct tempid) as numinvoicetdy from ot_sales where date='$Yesterday_date'";
$fetch_count_invoice_yesterday_channel=mysqli_query($db_conn,$select_count_invoice_yesterday_channel);
$result_count_invoice_yesterday_channel=mysqli_fetch_array($fetch_count_invoice_yesterday_channel);

$yesterday_invoice_count_channel=$result_count_invoice_yesterday_channel['numinvoicetdy'];

//yesterday Product Qty
$select_prqty_yesterday_channel="select sum(qty) from ot_sales where date='$Yesterday_date'";
$fetch_prqty_yesterday_channel=mysqli_query($db_conn,$select_prqty_yesterday_channel);
$result_prqty_yesterday_channel=mysqli_fetch_array($fetch_prqty_yesterday_channel);

if($result_prqty_yesterday_channel[0]!=NULL){
$yesterday_total_qty_channel=$result_prqty_yesterday_channel[0];
}else{$yesterday_total_qty_channel="0";}

//yesterday Total Amount
$select_totalamount_yesterday_channel="select sum(total) from ot_sales where date='$Yesterday_date'";
$fetch_totalamount_yesterday_channel=mysqli_query($db_conn,$select_totalamount_yesterday_channel);
$result_totalamount_yesterday_channel=mysqli_fetch_array($fetch_totalamount_yesterday_channel);

if($result_totalamount_yesterday_channel[0]!=NULL){
$yesterday_total_amount_channel=number_format($result_totalamount_yesterday_channel[0],2,'.','');
}else{$yesterday_total_amount_channel="0.00";}


//-------------------------------------This Month----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//thismonth Invoice Count
$select_count_invoice_thismonth_channel="select count(distinct tempid) as numinvoicetdy from ot_sales where date between '$start_date' and '$endDate'";
$fetch_count_invoice_thismonth_channel=mysqli_query($db_conn,$select_count_invoice_thismonth_channel);
$result_count_invoice_thismonth_channel=mysqli_fetch_array($fetch_count_invoice_thismonth_channel);

$thismonth_invoice_count_channel=$result_count_invoice_thismonth_channel['numinvoicetdy'];

//thismonth Product Qty
$select_prqty_thismonth_channel="select sum(qty) from ot_sales where date between '$start_date' and '$endDate'";
$fetch_prqty_thismonth_channel=mysqli_query($db_conn,$select_prqty_thismonth_channel);
$result_prqty_thismonth_channel=mysqli_fetch_array($fetch_prqty_thismonth_channel);

if($result_prqty_thismonth_channel[0]!=NULL){
$thismonth_total_qty_channel=$result_prqty_thismonth_channel[0];
}else{$thismonth_total_qty_channel="0";}

//thismonth Total Amount
$select_totalamount_thismonth_channel="select sum(total) from ot_sales where date between '$start_date' and '$endDate'";
$fetch_totalamount_thismonth_channel=mysqli_query($db_conn,$select_totalamount_thismonth_channel);
$result_totalamount_thismonth_channel=mysqli_fetch_array($fetch_totalamount_thismonth_channel);

if($result_totalamount_thismonth_channel[0]!=NULL){
$thismonth_total_amount_channel=number_format($result_totalamount_thismonth_channel[0],2,'.','');
}else{$thismonth_total_amount_channel="0.00";}


//-------------------------------------Last Month Till Date------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//lastmonth Invoice Count
$select_count_invoice_lastmonth_channel="select count(distinct tempid) as numinvoicetdy from ot_sales where date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_count_invoice_lastmonth_channel=mysqli_query($db_conn,$select_count_invoice_lastmonth_channel);
$result_count_invoice_lastmonth_channel=mysqli_fetch_array($fetch_count_invoice_lastmonth_channel);

$lastmonth_invoice_count_channel=$result_count_invoice_lastmonth_channel['numinvoicetdy'];

//lastmonth Product Qty
$select_prqty_lastmonth_channel="select sum(qty) from ot_sales where date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_prqty_lastmonth_channel=mysqli_query($db_conn,$select_prqty_lastmonth_channel);
$result_prqty_lastmonth_channel=mysqli_fetch_array($fetch_prqty_lastmonth_channel);

if($result_prqty_lastmonth_channel[0]!=NULL)
{
$lastmonth_total_qty_channel=$result_prqty_lastmonth_channel[0];
}else{$lastmonth_total_qty_channel="0";}

//lastmonth Total Amount
$select_totalamount_lastmonth_channel="select sum(total) from ot_sales where date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_totalamount_lastmonth_channel=mysqli_query($db_conn,$select_totalamount_lastmonth_channel);
$result_totalamount_lastmonth_channel=mysqli_fetch_array($fetch_totalamount_lastmonth_channel);

if($result_totalamount_lastmonth_channel[0]!=NULL){
$lastmonth_total_amount_channel=number_format($result_totalamount_lastmonth_channel[0],2,'.','');
}else{$lastmonth_total_amount_channel="0.00";}

*/
?>