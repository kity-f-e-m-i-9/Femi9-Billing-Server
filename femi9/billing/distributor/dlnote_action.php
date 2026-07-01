<?php include("include/db-connect.php");
include("checksession.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if (isset($_REQUEST['UpdateDlNote'])) {
	
	$inv_id=$_POST["inv_id"];
	$inv_number=$_POST["inv_number"];
	$inv_table=$_POST["inv_table"];
	
	$dl_note = RemoveSpecialChar($_POST["dl_note"]);
	$mode_pmnt = RemoveSpecialChar($_POST["mode_pmnt"]);
	$ref_no = RemoveSpecialChar($_POST["ref_no"]);
	$ref_date = RemoveSpecialChar($_POST["ref_date"]);
	$ot_ref = RemoveSpecialChar($_POST["ot_ref"]);
	$order_no = RemoveSpecialChar($_POST["order_no"]);
	
	$dated = RemoveSpecialChar($_POST["dated"]);
	$dispatch_doc_no = RemoveSpecialChar($_POST["dispatch_doc_no"]);
	$dlnote_date = RemoveSpecialChar($_POST["dlnote_date"]);
	$dispatch_through = RemoveSpecialChar($_POST["dispatch_through"]);
	$destination = RemoveSpecialChar($_POST["destination"]);
	$terms = RemoveSpecialChar($_POST["terms"]);
	
	$Seleccount="select count(*) as numdl from delivery_note where inv_id='$inv_id'";
	$fetchCount=mysqli_query($db_conn,$Seleccount);
	$ResultCount=mysqli_fetch_array($fetchCount);
	if($ResultCount['numdl']==0)
	{
		$insertRecords="insert into delivery_note (inv_id,inv_number,inv_table,dl_note,mode_pmnt,ref_no,ref_date,ot_ref,order_no,dated,dispatch_doc_no,dlnote_date,dispatch_through,destination,terms) values ('$inv_id','$inv_number','$inv_table','$dl_note','$mode_pmnt','$ref_no','$ref_date','$ot_ref','$order_no','$dated','$dispatch_doc_no','$dlnote_date','$dispatch_through','$destination','$terms')";
		mysqli_query($db_conn,$insertRecords);
	}
	else{
	
	$update_DLNote="update delivery_note set 
	dl_note='$dl_note',mode_pmnt='$mode_pmnt',ref_no='$ref_no',ref_date='$ref_date',ot_ref='$ot_ref',
	order_no='$order_no',dated='$dated',dispatch_doc_no='$dispatch_doc_no',dlnote_date='$dlnote_date',
	dispatch_through='$dispatch_through',destination='$destination',terms='$terms' where inv_id='$inv_id'";
	mysqli_query($db_conn,$update_DLNote);
	
	}
	
	
	if($inv_table=="user")
	{
		$printurl="user-invoice-print";
	}
	else if($inv_table=="shop")
	{
		$printurl="user-invoice-print";
	}
	else
	{
		$printurl="customer-user-invoice-print";
	}
	
	echo "<script>window.location='$printurl?invoiceid=".base64_encode($inv_id)."';</script>";
	
}else{ echo "<script>window.location='dashboard';</script>";}
?>