<?php
//return from registered person
$select_sum_total_intra_register_credit="select sum(total) from user_return_stock where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and buyer_gsttype='register' and gst_type='inner' and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_register_credit=mysqli_query($db_conn,$select_sum_total_intra_register_credit);
$result_sum_total_intra_register_credit=mysqli_fetch_array($fetch_sum_total_intra_register_credit);
							   
							   if($result_sum_total_intra_register_credit[0]!=NULL)
							   {$total_intra_register_credit=$result_sum_total_intra_register_credit[0];
							   }else{$total_intra_register_credit="0";}
							   
							   //return from unregistered person
$select_sum_total_intra_unregister_credit="select sum(total) from user_return_stock where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and buyer_gsttype='unregister' and gst_type='inner' and date between '$from_date' and '$to_date'";
$fetch_sum_total_intra_unregister_credit=mysqli_query($db_conn,$select_sum_total_intra_unregister_credit);
$result_sum_total_intra_unregister_credit=mysqli_fetch_array($fetch_sum_total_intra_unregister_credit);
							   
							   if($result_sum_total_intra_unregister_credit[0]!=NULL)
							   {$total_intra_unregister_credit=$result_sum_total_intra_unregister_credit[0];
							   }else{$total_intra_unregister_credit="0";}
							   
							   
							   //OT sales registered person
$select_TOT_intra_register_creditOT="select sum(total) from ot_sales_return where godownid='$get_godown_id' and buyer_gsttype='register' and return_date between '$from_date' and '$to_date' and gst_type='inner'";
$fetch_TOT_intra_register_creditOT=mysqli_query($db_conn,$select_TOT_intra_register_creditOT);
$result_TOT_intra_register_creditOT=mysqli_fetch_array($fetch_TOT_intra_register_creditOT);
							   
							   if($result_TOT_intra_register_creditOT[0]!=NULL)
							   {$total_intra_register_creditOT=$result_TOT_intra_register_creditOT[0];
							   }else{$total_intra_register_creditOT="0";}
							   
							    //OT sales unregistered person
$select_TOT_intra_unregister_creditOT="select sum(total) from ot_sales_return where godownid='$get_godown_id' and buyer_gsttype='unregister' and return_date between '$from_date' and '$to_date' and gst_type='inner'";
$fetch_TOT_intra_unregister_creditOT=mysqli_query($db_conn,$select_TOT_intra_unregister_creditOT);
$result_TOT_intra_unregister_creditOT=mysqli_fetch_array($fetch_TOT_intra_unregister_creditOT);
							   
							   if($result_TOT_intra_unregister_creditOT[0]!=NULL)
							   {$total_intra_unregister_creditOT=$result_TOT_intra_unregister_creditOT[0];
							   }else{$total_intra_unregister_creditOT="0";}


?>