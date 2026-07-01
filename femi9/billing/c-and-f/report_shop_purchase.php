<?php

//-------------------------------------Today---------------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today Invoice Count
$select_count_invoice_today_shop="select count(*) as numinvoicetdy from user_invoice where date='$today_date' and to_user_type='shop' and sub_total>0 and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_count_invoice_today_shop=mysqli_query($db_conn,$select_count_invoice_today_shop);
$result_count_invoice_today_shop=mysqli_fetch_array($fetch_count_invoice_today_shop);

$today_invoice_count_shop=$result_count_invoice_today_shop['numinvoicetdy'];

//Today Product Qty
$select_prqty_today_shop="select sum(qty) from user_invoice_items where date='$today_date' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_today_shop=mysqli_query($db_conn,$select_prqty_today_shop);
$result_prqty_today_shop=mysqli_fetch_array($fetch_prqty_today_shop);

if($result_prqty_today_shop[0]!=NULL){
$today_total_qty_shop=$result_prqty_today_shop[0];}else{$today_total_qty_shop="0";}

//Today Total Amount
$select_totalamount_today_shop="select sum(total) from user_invoice where date='$today_date' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_today_shop=mysqli_query($db_conn,$select_totalamount_today_shop);
$result_totalamount_today_shop=mysqli_fetch_array($fetch_totalamount_today_shop);

if($result_totalamount_today_shop[0]!=NULL){
$today_total_amount_shop=number_format($result_totalamount_today_shop[0],2,'.','');
}else{$today_total_amount_shop="0.00";}


//-------------------------------------Yesterday-----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//yesterday Invoice Count
$select_count_invoice_yesterday_shop="select count(*) as numinvoicetdy from user_invoice where date='$Yesterday_date' and to_user_type='shop' and sub_total>0 and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_count_invoice_yesterday_shop=mysqli_query($db_conn,$select_count_invoice_yesterday_shop);
$result_count_invoice_yesterday_shop=mysqli_fetch_array($fetch_count_invoice_yesterday_shop);

$yesterday_invoice_count_shop=$result_count_invoice_yesterday_shop['numinvoicetdy'];

//yesterday Product Qty
$select_prqty_yesterday_shop="select sum(qty) from user_invoice_items where date='$Yesterday_date' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_yesterday_shop=mysqli_query($db_conn,$select_prqty_yesterday_shop);
$result_prqty_yesterday_shop=mysqli_fetch_array($fetch_prqty_yesterday_shop);

if($result_prqty_yesterday_shop[0]!=NULL){
$yesterday_total_qty_shop=$result_prqty_yesterday_shop[0];
}else{$yesterday_total_qty_shop="0";}

//yesterday Total Amount
$select_totalamount_yesterday_shop="select sum(total) from user_invoice where date='$Yesterday_date' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_yesterday_shop=mysqli_query($db_conn,$select_totalamount_yesterday_shop);
$result_totalamount_yesterday_shop=mysqli_fetch_array($fetch_totalamount_yesterday_shop);

if($result_totalamount_yesterday_shop[0]!=NULL){
$yesterday_total_amount_shop=number_format($result_totalamount_yesterday_shop[0],2,'.','');
}else{$yesterday_total_amount_shop="0.00";}


//-------------------------------------This Month----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//thismonth Invoice Count
$select_count_invoice_thismonth_shop="select count(*) as numinvoicetdy from user_invoice where date between '$start_date' and '$endDate' and to_user_type='shop' and sub_total>0 and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_count_invoice_thismonth_shop=mysqli_query($db_conn,$select_count_invoice_thismonth_shop);
$result_count_invoice_thismonth_shop=mysqli_fetch_array($fetch_count_invoice_thismonth_shop);

$thismonth_invoice_count_shop=$result_count_invoice_thismonth_shop['numinvoicetdy'];

//thismonth Product Qty
$select_prqty_thismonth_shop="select sum(qty) from user_invoice_items where date between '$start_date' and '$endDate' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_thismonth_shop=mysqli_query($db_conn,$select_prqty_thismonth_shop);
$result_prqty_thismonth_shop=mysqli_fetch_array($fetch_prqty_thismonth_shop);

if($result_prqty_thismonth_shop[0]!=NULL){
$thismonth_total_qty_shop=$result_prqty_thismonth_shop[0];
}else{$thismonth_total_qty_shop="0";}

//thismonth Total Amount
$select_totalamount_thismonth_shop="select sum(total) from user_invoice where date between '$start_date' and '$endDate' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_thismonth_shop=mysqli_query($db_conn,$select_totalamount_thismonth_shop);
$result_totalamount_thismonth_shop=mysqli_fetch_array($fetch_totalamount_thismonth_shop);

if($result_totalamount_thismonth_shop[0]!=NULL){
$thismonth_total_amount_shop=number_format($result_totalamount_thismonth_shop[0],2,'.','');
}else{$thismonth_total_amount_shop="0.00";}


//-------------------------------------Last Month Till Date------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//lastmonth Invoice Count
$select_count_invoice_lastmonth_shop="select count(*) as numinvoicetdy from user_invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_user_type='shop' and sub_total>0 and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_count_invoice_lastmonth_shop=mysqli_query($db_conn,$select_count_invoice_lastmonth_shop);
$result_count_invoice_lastmonth_shop=mysqli_fetch_array($fetch_count_invoice_lastmonth_shop);

$lastmonth_invoice_count_shop=$result_count_invoice_lastmonth_shop['numinvoicetdy'];

//lastmonth Product Qty
$select_prqty_lastmonth_shop="select sum(qty) from user_invoice_items where date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_lastmonth_shop=mysqli_query($db_conn,$select_prqty_lastmonth_shop);
$result_prqty_lastmonth_shop=mysqli_fetch_array($fetch_prqty_lastmonth_shop);

if($result_prqty_lastmonth_shop[0]!=NULL)
{
$lastmonth_total_qty_shop=$result_prqty_lastmonth_shop[0];
}else{$lastmonth_total_qty_shop="0";}

//lastmonth Total Amount
$select_totalamount_lastmonth_shop="select sum(total) from user_invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_lastmonth_shop=mysqli_query($db_conn,$select_totalamount_lastmonth_shop);
$result_totalamount_lastmonth_shop=mysqli_fetch_array($fetch_totalamount_lastmonth_shop);

if($result_totalamount_lastmonth_shop[0]!=NULL){
$lastmonth_total_amount_shop=number_format($result_totalamount_lastmonth_shop[0],2,'.','');
}else{$lastmonth_total_amount_shop="0.00";}

?>