<?php
// Inter-state (Other States) sales totals for this TP — same structure as
// company/gst_details_inter.php, scoped to $tp_id, company-only channels
// dropped (see gst_details.php).

//inter-state registered person
//1 (shop)
$select_sum_total_inter_register="select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='register' and gst_type='outer' and date between '$from_date' and '$to_date'";
$fetch_sum_total_inter_register=mysqli_query($db_conn,$select_sum_total_inter_register);
$result_sum_total_inter_register=mysqli_fetch_array($fetch_sum_total_inter_register);
if($result_sum_total_inter_register[0]!=NULL)
{$total_inter_register=$result_sum_total_inter_register[0];
}else{$total_inter_register="0";}

//2 (customer)
$select_sum_total_inter_register2="select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='register' and gst_type='outer' and date between '$from_date' and '$to_date'";
$fetch_sum_total_inter_register2=mysqli_query($db_conn,$select_sum_total_inter_register2);
$result_sum_total_inter_register2=mysqli_fetch_array($fetch_sum_total_inter_register2);
if($result_sum_total_inter_register2[0]!=NULL)
{$total_inter_register2=$result_sum_total_inter_register2[0];
}else{$total_inter_register2="0";}

//inter-state unregistered person
//3 (shop)
$select_sum_total_inter_unregister="select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='unregister' and gst_type='outer' and date between '$from_date' and '$to_date'";
$fetch_sum_total_inter_unregister=mysqli_query($db_conn,$select_sum_total_inter_unregister);
$result_sum_total_inter_unregister=mysqli_fetch_array($fetch_sum_total_inter_unregister);
if($result_sum_total_inter_unregister[0]!=NULL)
{$total_inter_unregister=$result_sum_total_inter_unregister[0];
}else{$total_inter_unregister="0";}

//4 (customer)
$select_sum_total_inter_unregister2="select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='unregister' and gst_type='outer' and date between '$from_date' and '$to_date'";
$fetch_sum_total_inter_unregister2=mysqli_query($db_conn,$select_sum_total_inter_unregister2);
$result_sum_total_inter_unregister2=mysqli_fetch_array($fetch_sum_total_inter_unregister2);
if($result_sum_total_inter_unregister2[0]!=NULL)
{$total_inter_unregister2=$result_sum_total_inter_unregister2[0];
}else{$total_inter_unregister2="0";}

// ---- Nil-rated-only totals (gst_percentage=0), inter-state ----
$nil_inter_register = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='register' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
$nil_inter_register += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='register' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);

$nil_inter_unregister = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='unregister' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
$nil_inter_unregister += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='unregister' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
?>
