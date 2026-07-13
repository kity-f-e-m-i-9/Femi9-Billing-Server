<?php
								//intra-state registered person (tamilnadu)
								//1 (ss, st, dt, shop)
							   $select_sum_total_intra_register="select sum(total) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='register' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_register=mysqli_query($db_conn,$select_sum_total_intra_register);
							   $result_sum_total_intra_register=mysqli_fetch_array($fetch_sum_total_intra_register);
							   
							   if($result_sum_total_intra_register[0]!=NULL)
							   {$total_intra_register=$result_sum_total_intra_register[0];
							   }else{$total_intra_register="0";}
							   
							   //2 (customer)
							   $select_sum_total_intra_register2="select sum(total) from invoice where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='register' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_register2=mysqli_query($db_conn,$select_sum_total_intra_register2);
							   $result_sum_total_intra_register2=mysqli_fetch_array($fetch_sum_total_intra_register2);
							   
							   if($result_sum_total_intra_register2[0]!=NULL)
							   {$total_intra_register2=$result_sum_total_intra_register2[0];
							   }else{$total_intra_register2="0";}
							   
							   
							   //intra-state unregistered person (tamilnadu)
							   //3 (ss, st, dt, shop)
							   $select_sum_total_intra_unregister="select sum(total) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_unregister=mysqli_query($db_conn,$select_sum_total_intra_unregister);
							   $result_sum_total_intra_unregister=mysqli_fetch_array($fetch_sum_total_intra_unregister);
							   
							   if($result_sum_total_intra_unregister[0]!=NULL)
							   {$total_intra_unregister=$result_sum_total_intra_unregister[0];
							   }else{$total_intra_unregister="0";}
							   
							   //4 (customer)
							   $select_sum_total_intra_unregister2="select sum(total) from invoice where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_unregister2=mysqli_query($db_conn,$select_sum_total_intra_unregister2);
							   $result_sum_total_intra_unregister2=mysqli_fetch_array($fetch_sum_total_intra_unregister2);
							   
							   if($result_sum_total_intra_unregister2[0]!=NULL)
							   {$total_intra_unregister2=$result_sum_total_intra_unregister2[0];
							   }else{$total_intra_unregister2="0";}
							   
							   
//OT sales intra sales (Tamilnadu)
//5 (OT sales) - register
$select_sum_total_intra_OTSLS="select sum(total) from ot_sales where buyer_gsttype='register' and date between '$from_date' and '$to_date' and godownid='$get_godown_id' and gst_type='inner'";
							   $fetch_sum_total_intra_OTSLS=mysqli_query($db_conn,$select_sum_total_intra_OTSLS);
							   $result_sum_total_intra_OTSLS=mysqli_fetch_array($fetch_sum_total_intra_OTSLS);
							   
							   if($result_sum_total_intra_OTSLS[0]!=NULL)
							   {$total_reg_OTSLS_intra=$result_sum_total_intra_OTSLS[0];
							   }else{$total_reg_OTSLS_intra="0";}
							   
							   //6 (OT sales) - unregister
$select_sum_total_intra_OTSLSUN="select sum(total) from ot_sales where buyer_gsttype='unregister' and date between '$from_date' and '$to_date' and godownid='$get_godown_id' and gst_type='inner'";
							   $fetch_sum_total_intra_OTSLSUN=mysqli_query($db_conn,$select_sum_total_intra_OTSLSUN);
							   $result_sum_total_intra_OTSLSUN=mysqli_fetch_array($fetch_sum_total_intra_OTSLSUN);
							   
							   if($result_sum_total_intra_OTSLSUN[0]!=NULL)
							   {$total_reg_OTSLSUN_intra=$result_sum_total_intra_OTSLSUN[0];
							   }else{$total_reg_OTSLSUN_intra="0";}
							   
							   
							   // Only Intra Sales (Tamilnadu) only Registered Person
							   //7 (Internal Transfer)
							   $select_sum_total_intra_INTR="select sum(total) from internal_transfer where date between '$from_date' and '$to_date' and send_from='$get_godown_id'";
							   $fetch_sum_total_intra_INTR=mysqli_query($db_conn,$select_sum_total_intra_INTR);
							   $result_sum_total_intra_INTR=mysqli_fetch_array($fetch_sum_total_intra_INTR);
							   
							   if($result_sum_total_intra_INTR[0]!=NULL)
							   {$total_reg_INTR=$result_sum_total_intra_INTR[0];
							   }else{$total_reg_INTR="0";}

	// TP sales (company -> territory partner stock transfers), godown-sourced only, intra-state.
	require_once __DIR__ . '/include/TpGstHelper.php';
	$tp_sls_lines = tp_sales_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$get_godown_id'");
	$tp_sls_totals = tp_gst_bucket_totals($tp_sls_lines);
	$total_reg_TP = $tp_sls_totals['reg_intra'];
	$total_unreg_TP = $tp_sls_totals['unreg_intra'];


							   
   

							   ?>