<?php
// Inter-state credit notes (returns) for this TP — same structure as
// company/gst_details_credit_inter.php, scoped to $tp_id.

//return from registered person
$select_sum_total_inter_register_credit="select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and buyer_gsttype='register' and gst_type='outer' and date between '$from_date' and '$to_date'";
$fetch_sum_total_inter_register_credit=mysqli_query($db_conn,$select_sum_total_inter_register_credit);
$result_sum_total_inter_register_credit=mysqli_fetch_array($fetch_sum_total_inter_register_credit);
if($result_sum_total_inter_register_credit[0]!=NULL)
{$total_inter_register_credit=$result_sum_total_inter_register_credit[0];
}else{$total_inter_register_credit="0";}

//return from unregistered person
$select_sum_total_inter_unregister_credit="select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and buyer_gsttype='unregister' and gst_type='outer' and date between '$from_date' and '$to_date'";
$fetch_sum_total_inter_unregister_credit=mysqli_query($db_conn,$select_sum_total_inter_unregister_credit);
$result_sum_total_inter_unregister_credit=mysqli_fetch_array($fetch_sum_total_inter_unregister_credit);
if($result_sum_total_inter_unregister_credit[0]!=NULL)
{$total_inter_unregister_credit=$result_sum_total_inter_unregister_credit[0];
}else{$total_inter_unregister_credit="0";}

// ---- Nil-rated-only credit note totals (gst_percentage=0), inter-state ----
$nil_inter_register_credit = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and buyer_gsttype='register' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
$nil_inter_unregister_credit = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and buyer_gsttype='unregister' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
?>
