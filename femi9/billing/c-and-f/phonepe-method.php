<?php 


/*
$select_count_dist="select count(*) as numdist from super_stockiest where district_id='$district_id'";
	$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
	
    //upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	}else{$uploadfile="Nil";}
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+$valid_months months", strtotime($valid_from)));
		
		
		$merchantOrderId=$_REQUEST['merchantOrderId'];
		$merchantTransactionId=$_REQUEST['merchantTransactionId'];
		$merchantUserId=$_REQUEST['merchantUserId'];
		
		//insert
		$insCpns="insert into super_stockiest (temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId)

		values ('$temp_id','$uploadfile','$name','$district_id','$email','$mobile_number',
		'$username','$password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','pending',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId')"; 
		
		mysqli_query($db_conn,$insCpns);
		
		echo "<script>window.location='payment.php?tempid=".base64_encode($temp_id)."';</script>";

		
	}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='add_ss.php?distalready';</script>";
	}
	*/

	?>