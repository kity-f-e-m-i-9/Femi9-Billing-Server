<?php
// Intra-state (Tamilnadu) sales totals for this TP — same structure as
// company/gst_details.php, but scoped to $Login_user_TYPEvl='territory_partner'
// / $tp_id, and with the company-only channels (OT sales, internal transfer,
// TP invoices bought) dropped since a TP doesn't have those.

//intra-state registered person
//1 (shop)
$select_sum_total_intra_register="select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_register=mysqli_query($db_conn,$select_sum_total_intra_register);
$result_sum_total_intra_register=mysqli_fetch_array($fetch_sum_total_intra_register);
if($result_sum_total_intra_register[0]!=NULL)
{$total_intra_register=$result_sum_total_intra_register[0];
}else{$total_intra_register="0";}

//2 (customer)
$select_sum_total_intra_register2="select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_register2=mysqli_query($db_conn,$select_sum_total_intra_register2);
$result_sum_total_intra_register2=mysqli_fetch_array($fetch_sum_total_intra_register2);
if($result_sum_total_intra_register2[0]!=NULL)
{$total_intra_register2=$result_sum_total_intra_register2[0];
}else{$total_intra_register2="0";}

//intra-state unregistered person
//3 (shop)
$select_sum_total_intra_unregister="select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='unregister' and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_unregister=mysqli_query($db_conn,$select_sum_total_intra_unregister);
$result_sum_total_intra_unregister=mysqli_fetch_array($fetch_sum_total_intra_unregister);
if($result_sum_total_intra_unregister[0]!=NULL)
{$total_intra_unregister=$result_sum_total_intra_unregister[0];
}else{$total_intra_unregister="0";}

//4 (customer)
$select_sum_total_intra_unregister2="select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='unregister' and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_unregister2=mysqli_query($db_conn,$select_sum_total_intra_unregister2);
$result_sum_total_intra_unregister2=mysqli_fetch_array($fetch_sum_total_intra_unregister2);
if($result_sum_total_intra_unregister2[0]!=NULL)
{$total_intra_unregister2=$result_sum_total_intra_unregister2[0];
}else{$total_intra_unregister2="0";}

// ---- Nil-rated-only totals (gst_percentage=0), intra-state ----
// The "Total Sales" figures above mix nil-rated and taxable-rate lines together;
// the Nil Rated Supplies filing table needs only the gst_percentage=0 portion.
$nil_intra_register = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
$nil_intra_register += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);

$nil_intra_unregister = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='unregister' and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
$nil_intra_unregister += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='unregister' and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
?>
