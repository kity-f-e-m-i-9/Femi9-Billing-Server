<?php include("checksession.php");
include("include/db-connect.php");
include("config.php");
error_reporting(0);
include("RemoveSpecialChar.php");

//INSERT
if (isset($_REQUEST['ADDDEMOAWR'])) {
	
	$tempid=$_REQUEST["tempid"];
	$date=$_REQUEST["date"];
	$usertype=$_REQUEST["usertype"];
	$userid=$_REQUEST["userid"];
	$title=RemoveSpecialChar($_REQUEST["title"]);
	
	$ssid=$_REQUEST["ssid"];
	$stockist_id=$_REQUEST["stockist_id"];
	$distributor_id=$_REQUEST["distributor_id"];
	
	$select_count_dist="select count(*) as numdist from demo_awareness where tempid='$tempid'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
    
    //UPLOAD PHOTO
	$small_jpg= $_FILES['photo']['name'];
	if($small_jpg!=NULL)
	{
	   $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
		$rand_isd=bin2hex(random_bytes(64));
	    $filename=$rand_isd . '.' . $file_extension;
		
                 $uploaddir='demo_photo/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['photo']['tmp_name'],$uploadfile);
	}else{$uploadfile="Nil";}
	
        $InsertRecords="insert into demo_awareness (tempid,date,photo,title,usertype,userid,ssid,stockist_id,distributor_id)
		values ('$tempid','$date','$uploadfile','$title','$usertype','$userid','$ssid','$stockist_id','$distributor_id')";
		mysqli_query($db_conn,$InsertRecords);
		
	}

	   echo "<script>window.location='manage_demo.php?addedsuccess';</script>";
	
}


//UPDATE
if (isset($_REQUEST['UPDATEDEMO'])) {
	
	$update_id=$_REQUEST["update_id"];
	$old_photo=$_REQUEST["old_photo"];
	$title=RemoveSpecialChar($_REQUEST["title"]);
	
	//UPLOAD PHOTO
	$small_jpg= $_FILES['photo']['name'];
	if($small_jpg!=NULL)
	{
	   $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
		$rand_isd=bin2hex(random_bytes(64));
	    $filename=$rand_isd . '.' . $file_extension;
		
                 $uploaddir='demo_photo/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['photo']['tmp_name'],$uploadfile);
	   
	   //UNLINK photo
	   unlink("".$old_photo."");
	}
	else
	{ $uploadfile=$old_photo; }
		
		
		$update_records="update demo_awareness set title='$title',photo='$uploadfile' where id='$update_id'";
		mysqli_query($db_conn,$update_records);
		
		echo "<script>window.location='manage_demo.php?updatedSuccess';</script>";
	
}
?>