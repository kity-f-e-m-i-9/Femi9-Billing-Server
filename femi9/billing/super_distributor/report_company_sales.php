<?php

//-------------------------------------Today---------------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today Invoice Count
$select_count_invoice_today="select count(*) as numinvoicetdy from user_invoice where date='$today_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_today=mysqli_query($db_conn,$select_count_invoice_today);
$result_count_invoice_today=mysqli_fetch_array($fetch_count_invoice_today);

$select_count_invoice_today2="select count(*) as numinvcntcus from invoice where date='$today_date' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_today2=mysqli_query($db_conn,$select_count_invoice_today2);
$result_count_invoice_today2=mysqli_fetch_array($fetch_count_invoice_today2);

$today_invoice_count=$result_count_invoice_today['numinvoicetdy']+$result_count_invoice_today2['numinvcntcus'];

//Today Product Qty
$select_prqty_today="select sum(qty) from user_invoice_items where date='$today_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_today=mysqli_query($db_conn,$select_prqty_today);
$result_prqty_today=mysqli_fetch_array($fetch_prqty_today);

$select_prqty12_today="select sum(qty) from invoice_items where date='$today_date' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_prqty12_today=mysqli_query($db_conn,$select_prqty12_today);
$result_prqty12_today=mysqli_fetch_array($fetch_prqty12_today);

$today_total_qtySUM=$result_prqty_today[0]+$result_prqty12_today[0];
if($today_total_qtySUM!=NULL){$today_total_qty=$today_total_qtySUM;}else{$today_total_qty="0";}

//Today Total Amount
$select_totalamount_today="select sum(total) from user_invoice where date='$today_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_today=mysqli_query($db_conn,$select_totalamount_today);
$result_totalamount_today=mysqli_fetch_array($fetch_totalamount_today);

$select_totalamount_today2="select sum(total) from invoice where date='$today_date' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_totalamount_today2=mysqli_query($db_conn,$select_totalamount_today2);
$result_totalamount_today2=mysqli_fetch_array($fetch_totalamount_today2);

$today_total_amountSUM=$result_totalamount_today[0]+$result_totalamount_today2[0];
if($today_total_amountSUM!=NULL){$today_total_amount=number_format($today_total_amountSUM,2,'.','');}else{$today_total_amount="0.00";}


//-------------------------------------Yesterday-----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//yesterday Invoice Count
$select_count_invoice_yesterday="select count(*) as numinvoicetdy from user_invoice where date='$Yesterday_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_yesterday=mysqli_query($db_conn,$select_count_invoice_yesterday);
$result_count_invoice_yesterday=mysqli_fetch_array($fetch_count_invoice_yesterday);

$select_count_invoice_yesterday2="select count(*) as numinvcntcus from invoice where date='$Yesterday_date' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_yesterday2=mysqli_query($db_conn,$select_count_invoice_yesterday2);
$result_count_invoice_yesterday2=mysqli_fetch_array($fetch_count_invoice_yesterday2);

$yesterday_invoice_count=$result_count_invoice_yesterday['numinvoicetdy']+$result_count_invoice_yesterday2['numinvcntcus'];

//yesterday Product Qty
$select_prqty_yesterday="select sum(qty) from user_invoice_items where date='$Yesterday_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_yesterday=mysqli_query($db_conn,$select_prqty_yesterday);
$result_prqty_yesterday=mysqli_fetch_array($fetch_prqty_yesterday);

$select_prqty12_yesterday="select sum(qty) from invoice_items where date='$Yesterday_date' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_prqty12_yesterday=mysqli_query($db_conn,$select_prqty12_yesterday);
$result_prqty12_yesterday=mysqli_fetch_array($fetch_prqty12_yesterday);

$yesterday_total_qty=$result_prqty_yesterday[0]+$result_prqty12_yesterday[0];
if($yesterday_total_qty!=NULL){$yesterday_total_qty=$yesterday_total_qty;}
else{$yesterday_total_qty="0";}

//yesterday Total Amount
$select_totalamount_yesterday="select sum(total) from user_invoice where date='$Yesterday_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_yesterday=mysqli_query($db_conn,$select_totalamount_yesterday);
$result_totalamount_yesterday=mysqli_fetch_array($fetch_totalamount_yesterday);

$select_totalamount_yesterday2="select sum(total) from invoice where date='$Yesterday_date' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_totalamount_yesterday2=mysqli_query($db_conn,$select_totalamount_yesterday2);
$result_totalamount_yesterday2=mysqli_fetch_array($fetch_totalamount_yesterday2);

$yesterday_total_amount=$result_totalamount_yesterday[0]+$result_totalamount_yesterday2[0];
if($yesterday_total_amount!=NULL){$yesterday_total_amount=number_format($yesterday_total_amount,2,'.','');}
else{$yesterday_total_amount="0.00";}


//-------------------------------------This Month----------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//thismonth Invoice Count
$select_count_invoice_thismonth="select count(*) as numinvoicetdy from user_invoice where date between '$start_date' and '$endDate' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_thismonth=mysqli_query($db_conn,$select_count_invoice_thismonth);
$result_count_invoice_thismonth=mysqli_fetch_array($fetch_count_invoice_thismonth);

$select_count_invoice_thismonth2="select count(*) as numinvcntcus from invoice where date between '$start_date' and '$endDate' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_thismonth2=mysqli_query($db_conn,$select_count_invoice_thismonth2);
$result_count_invoice_thismonth2=mysqli_fetch_array($fetch_count_invoice_thismonth2);

$thismonth_invoice_count=$result_count_invoice_thismonth['numinvoicetdy']+$result_count_invoice_thismonth2['numinvcntcus'];

//thismonth Product Qty
$select_prqty_thismonth="select sum(qty) from user_invoice_items where date between '$start_date' and '$endDate' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_thismonth=mysqli_query($db_conn,$select_prqty_thismonth);
$result_prqty_thismonth=mysqli_fetch_array($fetch_prqty_thismonth);

$select_prqty12_thismonth="select sum(qty) from invoice_items where date between '$start_date' and '$endDate' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_prqty12_thismonth=mysqli_query($db_conn,$select_prqty12_thismonth);
$result_prqty12_thismonth=mysqli_fetch_array($fetch_prqty12_thismonth);

$thismonth_total_qty=$result_prqty_thismonth[0]+$result_prqty12_thismonth[0];
if($thismonth_total_qty!=NULL){$thismonth_total_qty=$thismonth_total_qty;}else{$thismonth_total_qty="0";}

//thismonth Total Amount
$select_totalamount_thismonth="select sum(total) from user_invoice where date between '$start_date' and '$endDate' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_thismonth=mysqli_query($db_conn,$select_totalamount_thismonth);
$result_totalamount_thismonth=mysqli_fetch_array($fetch_totalamount_thismonth);

$select_totalamount_thismonth2="select sum(total) from invoice where date between '$start_date' and '$endDate' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_totalamount_thismonth2=mysqli_query($db_conn,$select_totalamount_thismonth2);
$result_totalamount_thismonth2=mysqli_fetch_array($fetch_totalamount_thismonth2);

$thismonth_total_amount=$result_totalamount_thismonth[0]+$result_totalamount_thismonth2[0];
if($thismonth_total_amount!=NULL){$thismonth_total_amount=number_format($thismonth_total_amount,2,'.','');}
else{$thismonth_total_amount="0.00";}


//-------------------------------------Last Month Till Date------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//lastmonth Invoice Count
$select_count_invoice_lastmonth="select count(*) as numinvoicetdy from user_invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_lastmonth=mysqli_query($db_conn,$select_count_invoice_lastmonth);
$result_count_invoice_lastmonth=mysqli_fetch_array($fetch_count_invoice_lastmonth);

$select_count_invoice_lastmonth2="select count(*) as numinvcntcus from invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and sub_total>0";
$fetch_count_invoice_lastmonth2=mysqli_query($db_conn,$select_count_invoice_lastmonth2);
$result_count_invoice_lastmonth2=mysqli_fetch_array($fetch_count_invoice_lastmonth2);

$lastmonth_invoice_count=$result_count_invoice_lastmonth['numinvoicetdy']+$result_count_invoice_lastmonth2['numinvcntcus'];

//lastmonth Product Qty
$select_prqty_lastmonth="select sum(qty) from user_invoice_items where date between '$lastmonth_date_start' and '$lastmonth_date_end' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_prqty_lastmonth=mysqli_query($db_conn,$select_prqty_lastmonth);
$result_prqty_lastmonth=mysqli_fetch_array($fetch_prqty_lastmonth);

$select_prqty12_lastmonth="select sum(qty) from invoice_items where date between '$lastmonth_date_start' and '$lastmonth_date_end' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_prqty12_lastmonth=mysqli_query($db_conn,$select_prqty12_lastmonth);
$result_prqty12_lastmonth=mysqli_fetch_array($fetch_prqty12_lastmonth);

$lastmonth_total_qty=$result_prqty_lastmonth[0]+$result_prqty12_lastmonth[0];
if($lastmonth_total_qty!=NULL){$lastmonth_total_qty=$lastmonth_total_qty;}
else{$lastmonth_total_qty="0";}

//lastmonth Total Amount
$select_totalamount_lastmonth="select sum(total) from user_invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_totalamount_lastmonth=mysqli_query($db_conn,$select_totalamount_lastmonth);
$result_totalamount_lastmonth=mysqli_fetch_array($fetch_totalamount_lastmonth);

$select_totalamount_lastmonth2="select sum(total) from invoice where date between '$lastmonth_date_start' and '$lastmonth_date_end' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_totalamount_lastmonth2=mysqli_query($db_conn,$select_totalamount_lastmonth2);
$result_totalamount_lastmonth2=mysqli_fetch_array($fetch_totalamount_lastmonth2);

$lastmonth_total_amount=$result_totalamount_lastmonth[0]+$result_totalamount_lastmonth2[0];
if($lastmonth_total_amount!=NULL){$lastmonth_total_amount=number_format($lastmonth_total_amount,2,'.','');}
else{$lastmonth_total_amount="0.00";}

?>