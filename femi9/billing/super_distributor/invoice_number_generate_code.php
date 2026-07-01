<?php 
//1. get last id_only (invoice number generate)
		/*
		$select_MaxID="select max(id_only) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
		$fetch_MaxID=mysqli_query($db_conn,$select_MaxID);
		$result_MaxID=mysqli_fetch_row($fetch_MaxID);
		$id_only=$result_MaxID[0]+1;
		$format_num = str_pad($id_only, 3, '0', STR_PAD_LEFT);
		
		$INVDATE=date("ymd",strtotime($_REQUEST['date']));
		
	if($invuser=="super_stockiest"){$INVNUMUSER="SS";}
	else if($invuser=="stockiest"){	$INVNUMUSER="S";}
	else if($invuser=="distributor"){$INVNUMUSER="D";}
	else if($invuser=="outlet"){$INVNUMUSER="";}
	else{$INVNUMUSER="R";}
	
		$inv_number="F9".$result_LoGuserDtails['id']."".$randum_number."".$INVNUMUSER."".$format_num."";
		*/
		?>