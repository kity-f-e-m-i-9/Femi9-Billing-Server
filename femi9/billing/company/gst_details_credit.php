<?php
//return from registered person — sum from *_items so embedded GST on an
// 'inclusive'-priced product's total can be stripped via gstamount_total.
$select_sum_total_intra_register_credit="select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_register_credit=mysqli_query($db_conn,$select_sum_total_intra_register_credit);
$result_sum_total_intra_register_credit=mysqli_fetch_array($fetch_sum_total_intra_register_credit);

							   if($result_sum_total_intra_register_credit[0]!=NULL)
							   {$total_intra_register_credit=$result_sum_total_intra_register_credit[0];
							   }else{$total_intra_register_credit="0";}

							   //return from unregistered person
// buyer_gsttype not in ('register','unregister') covers blank/NULL rows (real data
// gap seen on user_return_stock_items) — defaulted to unregister (B2C) here, the
// conservative choice since it doesn't distort the B2B registered-person total that
// GST authorities cross-check against buyers' own filings.
$select_sum_total_intra_unregister_credit="select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and (gst_type='inner' or gst_type not in ('inner','outer')) and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_unregister_credit=mysqli_query($db_conn,$select_sum_total_intra_unregister_credit);
$result_sum_total_intra_unregister_credit=mysqli_fetch_array($fetch_sum_total_intra_unregister_credit);
							   
							   if($result_sum_total_intra_unregister_credit[0]!=NULL)
							   {$total_intra_unregister_credit=$result_sum_total_intra_unregister_credit[0];
							   }else{$total_intra_unregister_credit="0";}
							   
							   
							   //OT sales registered person
$select_TOT_intra_register_creditOT="select sum(total) from ot_sales_return where godownid='$get_godown_id' and buyer_gsttype='register' and return_date between '$from_date' and '$to_date' and (gst_type='inner' or gst_type not in ('inner','outer'))";
$fetch_TOT_intra_register_creditOT=mysqli_query($db_conn,$select_TOT_intra_register_creditOT);
$result_TOT_intra_register_creditOT=mysqli_fetch_array($fetch_TOT_intra_register_creditOT);
							   
							   if($result_TOT_intra_register_creditOT[0]!=NULL)
							   {$total_intra_register_creditOT=$result_TOT_intra_register_creditOT[0];
							   }else{$total_intra_register_creditOT="0";}
							   
							    //OT sales unregistered person
$select_TOT_intra_unregister_creditOT="select sum(total) from ot_sales_return where godownid='$get_godown_id' and buyer_gsttype='unregister' and return_date between '$from_date' and '$to_date' and (gst_type='inner' or gst_type not in ('inner','outer'))";
$fetch_TOT_intra_unregister_creditOT=mysqli_query($db_conn,$select_TOT_intra_unregister_creditOT);
$result_TOT_intra_unregister_creditOT=mysqli_fetch_array($fetch_TOT_intra_unregister_creditOT);
							   
							   if($result_TOT_intra_unregister_creditOT[0]!=NULL)
							   {$total_intra_unregister_creditOT=$result_TOT_intra_unregister_creditOT[0];
							   }else{$total_intra_unregister_creditOT="0";}

	// TP sales returns (credit notes), godown-sourced only, intra-state.
	require_once __DIR__ . '/include/TpGstHelper.php';
	$tp_credit_lines = tp_credit_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$get_godown_id'");
	$tp_credit_totals = tp_gst_bucket_totals($tp_credit_lines);
	$total_reg_TP_credit = $tp_credit_totals['reg_intra'];
	$total_unreg_TP_credit = $tp_credit_totals['unreg_intra'];

	// ---- Nil-rated-only credit note totals (gst_percentage=0), intra-state ----
	// Needed to net nil-rated returns out of the gross nil-rated sales total in
	// GSTR1.php's filing summary table (otherwise taxable = grand_total - gross_nil
	// double-subtracts nil-rated credit notes and can go negative).
	$nil_intra_register_credit = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and buyer_gsttype='register' and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_intra_register_credit += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(osr.total) from ot_sales_return osr join products p on p.id=osr.prid where osr.godownid='$get_godown_id' and osr.buyer_gsttype='register' and (osr.gst_type='inner' or osr.gst_type not in ('inner','outer')) and p.gst=0 and osr.return_date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_intra_register_credit += tp_gst_bucket_totals(array_filter($tp_credit_lines, fn($l) => $l['gst_percentage'] == 0))['reg_intra'] ?? 0;

	$nil_intra_unregister_credit = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and (gst_type='inner' or gst_type not in ('inner','outer')) and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_intra_unregister_credit += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(osr.total) from ot_sales_return osr join products p on p.id=osr.prid where osr.godownid='$get_godown_id' and osr.buyer_gsttype='unregister' and (osr.gst_type='inner' or osr.gst_type not in ('inner','outer')) and p.gst=0 and osr.return_date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_intra_unregister_credit += tp_gst_bucket_totals(array_filter($tp_credit_lines, fn($l) => $l['gst_percentage'] == 0))['unreg_intra'] ?? 0;

?>