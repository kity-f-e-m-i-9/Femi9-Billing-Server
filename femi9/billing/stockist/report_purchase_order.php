<?php

//-------------------------------------Today---------------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today Invoice Count
$select_count_invoice_today_purchaseorder="select count(distinct inv_id) as numinvoicetdy from user_invoice where date='$today_date' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_count_invoice_today_purchaseorder=mysqli_query($db_conn,$select_count_invoice_today_purchaseorder);
$result_count_invoice_today_purchaseorder=mysqli_fetch_array($fetch_count_invoice_today_purchaseorder);

$today_invoice_count_purchaseorder=$result_count_invoice_today_purchaseorder['numinvoicetdy'];

//Today Product Qty
$select_prqty_today_purchaseorder="select sum(qty) from user_invoice_items where date='$today_date' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_prqty_today_purchaseorder=mysqli_query($db_conn,$select_prqty_today_purchaseorder);
$result_prqty_today_purchaseorder=mysqli_fetch_array($fetch_prqty_today_purchaseorder);

if($result_prqty_today_purchaseorder[0]!=NULL){
$today_total_qty_purchaseorder=$result_prqty_today_purchaseorder[0];}else{$today_total_qty_purchaseorder="0";}

//Today Total Amount
$select_totalamount_today_purchaseorder="select sum(total) from user_invoice where date='$today_date' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_totalamount_today_purchaseorder=mysqli_query($db_conn,$select_totalamount_today_purchaseorder);
$result_totalamount_today_purchaseorder=mysqli_fetch_array($fetch_totalamount_today_purchaseorder);

if($result_totalamount_today_purchaseorder[0]!=NULL){
$today_total_amount_purchaseorder=number_format($result_totalamount_today_purchaseorder[0],2,'.','');
}else{$today_total_amount_purchaseorder="0.00";}


//-------------------------------------Yesterday-----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//yesterday Invoice Count
$select_count_invoice_yesterday_purchaseorder="select count(distinct inv_id) as numinvoicetdy from user_invoice where date='$Yesterday_date' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_count_invoice_yesterday_purchaseorder=mysqli_query($db_conn,$select_count_invoice_yesterday_purchaseorder);
$result_count_invoice_yesterday_purchaseorder=mysqli_fetch_array($fetch_count_invoice_yesterday_purchaseorder);

$yesterday_invoice_count_purchaseorder=$result_count_invoice_yesterday_purchaseorder['numinvoicetdy'];

//yesterday Product Qty
$select_prqty_yesterday_purchaseorder="select sum(qty) from user_invoice_items where date='$Yesterday_date' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_prqty_yesterday_purchaseorder=mysqli_query($db_conn,$select_prqty_yesterday_purchaseorder);
$result_prqty_yesterday_purchaseorder=mysqli_fetch_array($fetch_prqty_yesterday_purchaseorder);

if($result_prqty_yesterday_purchaseorder[0]!=NULL){
$yesterday_total_qty_purchaseorder=$result_prqty_yesterday_purchaseorder[0];
}else{$yesterday_total_qty_purchaseorder="0";}


//yesterday Total Amount
$select_totalamount_yesterday_purchaseorder="select sum(total) from user_invoice where date='$Yesterday_date' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_totalamount_yesterday_purchaseorder=mysqli_query($db_conn,$select_totalamount_yesterday_purchaseorder);
$result_totalamount_yesterday_purchaseorder=mysqli_fetch_array($fetch_totalamount_yesterday_purchaseorder);

if($result_totalamount_yesterday_purchaseorder[0]!=NULL){
$yesterday_total_amount_purchaseorder=number_format($result_totalamount_yesterday_purchaseorder[0],2,'.','');
}else{$yesterday_total_amount_purchaseorder="0.00";}


//-------------------------------------This Month----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//thismonth Invoice Count
$select_count_invoice_thismonth_purchaseorder="select count(distinct inv_id) as numinvoicetdy from user_invoice where date between '$start_date' and '$endDate' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_count_invoice_thismonth_purchaseorder=mysqli_query($db_conn,$select_count_invoice_thismonth_purchaseorder);
$result_count_invoice_thismonth_purchaseorder=mysqli_fetch_array($fetch_count_invoice_thismonth_purchaseorder);

$thismonth_invoice_count_purchaseorder=$result_count_invoice_thismonth_purchaseorder['numinvoicetdy'];

//thismonth Product Qty
$select_prqty_thismonth_purchaseorder="select sum(qty) from user_invoice_items where date between '$start_date' and '$endDate' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_prqty_thismonth_purchaseorder=mysqli_query($db_conn,$select_prqty_thismonth_purchaseorder);
$result_prqty_thismonth_purchaseorder=mysqli_fetch_array($fetch_prqty_thismonth_purchaseorder);

if($result_prqty_thismonth_purchaseorder[0]!=NULL){
$thismonth_total_qty_purchaseorder=$result_prqty_thismonth_purchaseorder[0];
}else{$thismonth_total_qty_purchaseorder="0";}

//thismonth Total Amount
$select_totalamount_thismonth_purchaseorder="select sum(total) from user_invoice where date between '$start_date' and '$endDate' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_totalamount_thismonth_purchaseorder=mysqli_query($db_conn,$select_totalamount_thismonth_purchaseorder);
$result_totalamount_thismonth_purchaseorder=mysqli_fetch_array($fetch_totalamount_thismonth_purchaseorder);

if($result_totalamount_thismonth_purchaseorder[0]!=NULL){
$thismonth_total_amount_purchaseorder=number_format($result_totalamount_thismonth_purchaseorder[0],2,'.','');
}else{$thismonth_total_amount_purchaseorder="0.00";}


//-------------------------------------Last Month Till Date------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//lastmonth Invoice Count
$select_count_invoice_lastmonth_purchaseorder="select count(distinct inv_id) as numinvoicetdy from user_invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_count_invoice_lastmonth_purchaseorder=mysqli_query($db_conn,$select_count_invoice_lastmonth_purchaseorder);
$result_count_invoice_lastmonth_purchaseorder=mysqli_fetch_array($fetch_count_invoice_lastmonth_purchaseorder);

$lastmonth_invoice_count_purchaseorder=$result_count_invoice_lastmonth_purchaseorder['numinvoicetdy'];

//lastmonth Product Qty
$select_prqty_lastmonth_purchaseorder="select sum(qty) from user_invoice_items where date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_prqty_lastmonth_purchaseorder=mysqli_query($db_conn,$select_prqty_lastmonth_purchaseorder);
$result_prqty_lastmonth_purchaseorder=mysqli_fetch_array($fetch_prqty_lastmonth_purchaseorder);

if($result_prqty_lastmonth_purchaseorder[0]!=NULL)
{
$lastmonth_total_qty_purchaseorder=$result_prqty_lastmonth_purchaseorder[0];
}else{$lastmonth_total_qty_purchaseorder="0";}

//lastmonth Total Amount
$select_totalamount_lastmonth_purchaseorder="select sum(total) from user_invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl'";
$fetch_totalamount_lastmonth_purchaseorder=mysqli_query($db_conn,$select_totalamount_lastmonth_purchaseorder);
$result_totalamount_lastmonth_purchaseorder=mysqli_fetch_array($fetch_totalamount_lastmonth_purchaseorder);

if($result_totalamount_lastmonth_purchaseorder[0]!=NULL){
$lastmonth_total_amount_purchaseorder=number_format($result_totalamount_lastmonth_purchaseorder[0],2,'.','');
}else{$lastmonth_total_amount_purchaseorder="0.00";}

?>