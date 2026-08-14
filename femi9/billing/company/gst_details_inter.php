<?php
								//inter-state registered person (tamilnadu)
								//1 (ss, st, dt, shop) — sum from *_items so the GST embedded in an
								// 'inclusive'-priced product's total can be stripped out via
								// gstamount_total, same fix as territory-partner/gstr1.php.
							   $select_sum_total_inter_register="select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='register' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_register=mysqli_query($db_conn,$select_sum_total_inter_register);
							   $result_sum_total_inter_register=mysqli_fetch_array($fetch_sum_total_inter_register);

							   if($result_sum_total_inter_register[0]!=NULL)
							   {$total_inter_register=$result_sum_total_inter_register[0];
							   }else{$total_inter_register="0";}

							   //2 (customer)
							   $select_sum_total_inter_register2="select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='register' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_register2=mysqli_query($db_conn,$select_sum_total_inter_register2);
							   $result_sum_total_inter_register2=mysqli_fetch_array($fetch_sum_total_inter_register2);

							   if($result_sum_total_inter_register2[0]!=NULL)
							   {$total_inter_register2=$result_sum_total_inter_register2[0];
							   }else{$total_inter_register2="0";}

							   //inter-state unregistered person (tamilnadu)
							   //3 (ss, st, dt, shop)
							   $select_sum_total_inter_unregister="select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_unregister=mysqli_query($db_conn,$select_sum_total_inter_unregister);
							   $result_sum_total_inter_unregister=mysqli_fetch_array($fetch_sum_total_inter_unregister);

							   if($result_sum_total_inter_unregister[0]!=NULL)
							   {$total_inter_unregister=$result_sum_total_inter_unregister[0];
							   }else{$total_inter_unregister="0";}

							   //4 (customer)
							   $select_sum_total_inter_unregister2="select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_unregister2=mysqli_query($db_conn,$select_sum_total_inter_unregister2);
							   $result_sum_total_inter_unregister2=mysqli_fetch_array($fetch_sum_total_inter_unregister2);

							   if($result_sum_total_inter_unregister2[0]!=NULL)
							   {$total_inter_unregister2=$result_sum_total_inter_unregister2[0];
							   }else{$total_inter_unregister2="0";}


							   //OT sales Inter sales (Other State)
//5 (OT sales) - register
$select_sum_total_inter_OTSLS="select sum(total-gst_amount) from ot_sales where buyer_gsttype='register' and date between '$from_date' and '$to_date' and godownid='$get_godown_id' and gst_type='outer'";
							   $fetch_sum_total_inter_OTSLS=mysqli_query($db_conn,$select_sum_total_inter_OTSLS);
							   $result_sum_total_inter_OTSLS=mysqli_fetch_array($fetch_sum_total_inter_OTSLS);

							   if($result_sum_total_inter_OTSLS[0]!=NULL)
							   {$total_reg_OTSLS_inter=$result_sum_total_inter_OTSLS[0];
							   }else{$total_reg_OTSLS_inter="0";}

							   //6 (OT sales) - unregister
$select_sum_total_inter_OTSLSUN="select sum(total-gst_amount) from ot_sales where buyer_gsttype='unregister' and date between '$from_date' and '$to_date' and godownid='$get_godown_id' and gst_type='outer'";
							   $fetch_sum_total_inter_OTSLSUN=mysqli_query($db_conn,$select_sum_total_inter_OTSLSUN);
							   $result_sum_total_inter_OTSLSUN=mysqli_fetch_array($fetch_sum_total_inter_OTSLSUN);

							   if($result_sum_total_inter_OTSLSUN[0]!=NULL)
							   {$total_reg_OTSLSUN_inter=$result_sum_total_inter_OTSLSUN[0];
							   }else{$total_reg_OTSLSUN_inter="0";}

	// TP sales (company -> territory partner stock transfers), godown-sourced only, inter-state.
	require_once __DIR__ . '/include/TpGstHelper.php';
	$tp_sls_lines_inter = tp_sales_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$get_godown_id'");
	$tp_sls_totals_inter = tp_gst_bucket_totals($tp_sls_lines_inter);
	$total_reg_TP_inter = $tp_sls_totals_inter['reg_inter'];
	$total_unreg_TP_inter = $tp_sls_totals_inter['unreg_inter'];

	// ---- Nil-rated-only totals (gst_percentage/gst = 0), inter-state ----
	// The "Total Sales" figures above mix nil-rated and taxable-rate lines together;
	// the Nil Rated Supplies filing table needs only the gst_percentage=0 portion.
	$nil_inter_register = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='register' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_inter_register += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='register' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_inter_register += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gst_amount) from ot_sales where buyer_gsttype='register' and gst_type='outer' and gst=0 and date between '$from_date' and '$to_date' and godownid='$get_godown_id'"))[0] ?? 0);
	$nil_inter_register += tp_gst_bucket_totals(array_filter($tp_sls_lines_inter, fn($l) => $l['gst_percentage'] == 0))['reg_inter'] ?? 0;

	$nil_inter_unregister = (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_inter_unregister += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gstamount_total) from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_type='outer' and gst_percentage=0 and date between '$from_date' and '$to_date'"))[0] ?? 0);
	$nil_inter_unregister += (float)(mysqli_fetch_array(mysqli_query($db_conn, "select sum(total-gst_amount) from ot_sales where buyer_gsttype='unregister' and gst_type='outer' and gst=0 and date between '$from_date' and '$to_date' and godownid='$get_godown_id'"))[0] ?? 0);
	$nil_inter_unregister += tp_gst_bucket_totals(array_filter($tp_sls_lines_inter, fn($l) => $l['gst_percentage'] == 0))['unreg_inter'] ?? 0;


							   ?>