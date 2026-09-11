<?php
// Intra-state credit notes (returns) for this TP — same structure as
// company/gst_details_credit.php, scoped to $tp_id, OT/internal-transfer/TP
// channels dropped (see gst_details.php for why).

//return from registered person
$select_sum_total_intra_register_credit="select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_register_credit=mysqli_query($db_conn,$select_sum_total_intra_register_credit);
$result_sum_total_intra_register_credit=mysqli_fetch_array($fetch_sum_total_intra_register_credit);
if($result_sum_total_intra_register_credit[0]!=NULL)
{$total_intra_register_credit=$result_sum_total_intra_register_credit[0];
}else{$total_intra_register_credit="0";}

//return from unregistered person — blank/NULL buyer_gsttype defaults to
// unregister here too, matching company/gst_details_credit.php's convention.
$select_sum_total_intra_unregister_credit="select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_unregister_credit=mysqli_query($db_conn,$select_sum_total_intra_unregister_credit);
$result_sum_total_intra_unregister_credit=mysqli_fetch_array($fetch_sum_total_intra_unregister_credit);
if($result_sum_total_intra_unregister_credit[0]!=NULL)
{$total_intra_unregister_credit=$result_sum_total_intra_unregister_credit[0];
}else{$total_intra_unregister_credit="0";}

// ---- Nil-rated-only credit note totals (gst_percentage=0), intra-state ----
$nil_intra_register_credit = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
$nil_intra_unregister_credit = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
?>
