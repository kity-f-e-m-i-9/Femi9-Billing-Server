<?php include("checksession.php");
include("config.php");
error_reporting(0);

if(isset($_REQUEST['sendrequest']))
{
	
//----------------------------------------------------------------------------
//----------------------------------------------------------------------------
	
	//**************
	$reqid=$_REQUEST['reqid'];
	$date=$_REQUEST['date'];
	$reqtype=$_REQUEST['reqtype'];
	
	$fromusertype=$_REQUEST['fromusertype'];
	$fromuserid=$_REQUEST['fromuserid'];
	
	$tousertype=$_REQUEST['tousertype'];
	$touserid=$_REQUEST['touserid'];
	
	date_default_timezone_set("Asia/Kolkata");
		$datefile=date("Ymd");
		$timefile=date("gis");
		
	//SCREEN SHOT (SCR1)
	$small_jpg_SCR1= $_FILES['screenshot']['name'];
	if($small_jpg_SCR1!=NULL)
	{
$file_extension_SCR1 = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
$rand_isd_SCR1=bin2hex(random_bytes(64));
$filename_SCR1=$rand_isd_SCR1 . '.' . $file_extension_SCR1;
$filenameINS_SCR1="".$datefile."".$timefile."SCR1".$filename_SCR1."";
                 $uploaddir_SCR1='screenshot/';
                 $uploadfile_SCR1=$uploaddir_SCR1.$filenameINS_SCR1;
	   move_uploaded_file($_FILES['screenshot']['tmp_name'],$uploadfile_SCR1);
	}else{$filenameINS_SCR1="Nil";}
	
	
	//SCREEN SHOT (SCR2)
	$small_jpg_SCR2= $_FILES['screenshot2']['name'];
	if($small_jpg_SCR2!=NULL)
	{
$file_extension_SCR2 = pathinfo($_FILES['screenshot2']['name'], PATHINFO_EXTENSION);
$rand_isd_SCR2=bin2hex(random_bytes(64));
$filename_SCR2=$rand_isd_SCR2 . '.' . $file_extension_SCR2;
$filenameINS_SCR2="".$datefile."".$timefile."SCR2".$filename_SCR2."";
                 $uploaddir_SCR2='screenshot/';
                 $uploadfile_SCR2=$uploaddir_SCR2.$filenameINS_SCR2;
	   move_uploaded_file($_FILES['screenshot2']['tmp_name'],$uploadfile_SCR2);
	}else{$filenameINS_SCR2="Nil";}
	
	$select_count_invoice="select count(*) as numrequest from stock_request where reqid='$reqid'";
	$fetch_count_invoice=mysqli_query($db_conn,$select_count_invoice);
	$result_count_invoice=mysqli_fetch_array($fetch_count_invoice);
	if($result_count_invoice['numrequest']==0)
	{
		
		$delivery_address=str_replace("'","&#39;",$_REQUEST['delivery_address']);
		$amount=str_replace("'","&#39;",$_REQUEST['amount']);
		$utr=str_replace("'","&#39;",$_REQUEST['utr']);
		
		$insert_Invoice="insert into stock_request (reqid,date,fromusertype,fromuserid,tousertype,touserid,status,inv_id,
		screenshot,screenshot2,verified,reqtype,delivery_address,amount,utr)
		values ('$reqid','$date','$fromusertype','$fromuserid','$tousertype','$touserid','pending','nil',
		'$filenameINS_SCR1','$filenameINS_SCR2','0','$reqtype','$delivery_address','$amount','$utr')";
		mysqli_query($db_conn,$insert_Invoice);
		
	}
	
	
//**************
$prid = implode("#",$_REQUEST['prid']);
$qty = implode("#",$_REQUEST['qty']);
	
$prid_ex = explode ("#",$prid); 
$qty_ex = explode ("#",$qty); 

$number = count($prid_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $prid_value = $prid_ex[$i]; 
	 
	 //get product amount
	 $select_pramount="select * from products where id='$prid_value'";
	 $fetch_pramount=mysqli_query($db_conn,$select_pramount);
	 $result_pramount=mysqli_fetch_array($fetch_pramount);
	 
	 $gst=$result_pramount['gst'];
     $hsn=$result_pramount['hsn'];

	 $pramount=$result_pramount['distributor_price'];
     $qty_value = $qty_ex[$i]; 
	 
	 $subtotal=$pramount*$qty_value;
	 $gsttotal=$subtotal*$gst/100;
	 $gsttotal=number_format($gsttotal,2,'.','');
	 $total=$subtotal+$gsttotal;
	 
	 $select_count_invoice12="select count(*) as numrequest2 from stock_request_items where reqid='$reqid' and prid='$prid_value'";
	$fetch_count_invoice12=mysqli_query($db_conn,$select_count_invoice12);
	$result_count_invoice12=mysqli_fetch_array($fetch_count_invoice12);
	if($result_count_invoice12['numrequest2']==0 && $prid_value!=NULL)
	{
		
		$insert_Invoice12="insert into stock_request_items (reqid,prid,amount,qty,total,fromusertype,fromuserid,tousertype,touserid,subtotal,gst,gsttotal,hsn)
		values 
		('$reqid','$prid_value','$pramount','$qty_value','$total','$fromusertype','$fromuserid','$tousertype','$touserid','$subtotal','$gst','$gsttotal','$hsn')";
		mysqli_query($db_conn,$insert_Invoice12);
		
	}
	
} 
	
//----------------------------------------------------------------------------
//----------------------------------------------------------------------------

echo "<script>window.location='stock-request-confirmation.php?reqid=".base64_encode($reqid)."';</script>";	


//if not submit
}else{
echo "<script>window.location='stock-request-add.php';</script>";	
}	
?>