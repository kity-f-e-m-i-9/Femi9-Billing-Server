<?php

//-------------------------------------Today---------------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today Invoice Count
$select_count_invoice_today_purchaseorder="select count(distinct tempid) as numinvoicetdy from input_stock where input_date='$today_date'";
$fetch_count_invoice_today_purchaseorder=mysqli_query($db_conn,$select_count_invoice_today_purchaseorder);
$result_count_invoice_today_purchaseorder=mysqli_fetch_array($fetch_count_invoice_today_purchaseorder);

$today_invoice_count_purchaseorder=$result_count_invoice_today_purchaseorder['numinvoicetdy'];

//Today Product Qty
$select_prqty_today_purchaseorder="select sum(input_qty) from input_stock where input_date='$today_date'";
$fetch_prqty_today_purchaseorder=mysqli_query($db_conn,$select_prqty_today_purchaseorder);
$result_prqty_today_purchaseorder=mysqli_fetch_array($fetch_prqty_today_purchaseorder);

if($result_prqty_today_purchaseorder[0]!=NULL){
$today_total_qty_purchaseorder=$result_prqty_today_purchaseorder[0];}else{$today_total_qty_purchaseorder="0";}


/*
//-------------------------------------Yesterday-----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//yesterday Invoice Count
$select_count_invoice_yesterday_purchaseorder="select count(distinct tempid) as numinvoicetdy from input_stock where input_date='$Yesterday_date'";
$fetch_count_invoice_yesterday_purchaseorder=mysqli_query($db_conn,$select_count_invoice_yesterday_purchaseorder);
$result_count_invoice_yesterday_purchaseorder=mysqli_fetch_array($fetch_count_invoice_yesterday_purchaseorder);

$yesterday_invoice_count_purchaseorder=$result_count_invoice_yesterday_purchaseorder['numinvoicetdy'];

//yesterday Product Qty
$select_prqty_yesterday_purchaseorder="select sum(input_qty) from input_stock where input_date='$Yesterday_date'";
$fetch_prqty_yesterday_purchaseorder=mysqli_query($db_conn,$select_prqty_yesterday_purchaseorder);
$result_prqty_yesterday_purchaseorder=mysqli_fetch_array($fetch_prqty_yesterday_purchaseorder);

if($result_prqty_yesterday_purchaseorder[0]!=NULL){
$yesterday_total_qty_purchaseorder=$result_prqty_yesterday_purchaseorder[0];
}else{$yesterday_total_qty_purchaseorder="0";}




//-------------------------------------This Month----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//thismonth Invoice Count
$select_count_invoice_thismonth_purchaseorder="select count(distinct tempid) as numinvoicetdy from input_stock where input_date between '$start_date' and '$endDate'";
$fetch_count_invoice_thismonth_purchaseorder=mysqli_query($db_conn,$select_count_invoice_thismonth_purchaseorder);
$result_count_invoice_thismonth_purchaseorder=mysqli_fetch_array($fetch_count_invoice_thismonth_purchaseorder);

$thismonth_invoice_count_purchaseorder=$result_count_invoice_thismonth_purchaseorder['numinvoicetdy'];

//thismonth Product Qty
$select_prqty_thismonth_purchaseorder="select sum(input_qty) from input_stock where input_date between '$start_date' and '$endDate'";
$fetch_prqty_thismonth_purchaseorder=mysqli_query($db_conn,$select_prqty_thismonth_purchaseorder);
$result_prqty_thismonth_purchaseorder=mysqli_fetch_array($fetch_prqty_thismonth_purchaseorder);

if($result_prqty_thismonth_purchaseorder[0]!=NULL){
$thismonth_total_qty_purchaseorder=$result_prqty_thismonth_purchaseorder[0];
}else{$thismonth_total_qty_purchaseorder="0";}


//-------------------------------------Last Month Till Date------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//lastmonth Invoice Count
$select_count_invoice_lastmonth_purchaseorder="select count(distinct tempid) as numinvoicetdy from input_stock where input_date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_count_invoice_lastmonth_purchaseorder=mysqli_query($db_conn,$select_count_invoice_lastmonth_purchaseorder);
$result_count_invoice_lastmonth_purchaseorder=mysqli_fetch_array($fetch_count_invoice_lastmonth_purchaseorder);

$lastmonth_invoice_count_purchaseorder=$result_count_invoice_lastmonth_purchaseorder['numinvoicetdy'];

//lastmonth Product Qty
$select_prqty_lastmonth_purchaseorder="select sum(input_qty) from input_stock where input_date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_prqty_lastmonth_purchaseorder=mysqli_query($db_conn,$select_prqty_lastmonth_purchaseorder);
$result_prqty_lastmonth_purchaseorder=mysqli_fetch_array($fetch_prqty_lastmonth_purchaseorder);

if($result_prqty_lastmonth_purchaseorder[0]!=NULL)
{
$lastmonth_total_qty_purchaseorder=$result_prqty_lastmonth_purchaseorder[0];
}else{$lastmonth_total_qty_purchaseorder="0";}

*/
?>