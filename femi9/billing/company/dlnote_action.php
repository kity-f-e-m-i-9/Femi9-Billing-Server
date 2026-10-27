<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['ss','st','dt','sdt','shop','cus','stock_request','ot_channels','debit_note','manage_return','demo_free','report','payment_entry','manage_payment_entry','territory_partner']); include("include/db-connect.php");
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
	
	$Seleccount="select count(*) as numdl from delivery_note where inv_id=?";
	$fetchCount=db_q($db_conn,$Seleccount,[$inv_id]);
	$ResultCount=mysqli_fetch_array($fetchCount);
	if($ResultCount['numdl']==0)
	{
		$insertRecords="insert into delivery_note (inv_id,inv_number,inv_table,dl_note,mode_pmnt,ref_no,ref_date,ot_ref,order_no,dated,dispatch_doc_no,dlnote_date,dispatch_through,destination,terms) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
		db_q($db_conn,$insertRecords,[$inv_id,$inv_number,$inv_table,$dl_note,$mode_pmnt,$ref_no,$ref_date,$ot_ref,$order_no,$dated,$dispatch_doc_no,$dlnote_date,$dispatch_through,$destination,$terms]);
	}
	else{
	
	$update_DLNote="update delivery_note set 
	dl_note=?,mode_pmnt=?,ref_no=?,ref_date=?,ot_ref=?,
	order_no=?,dated=?,dispatch_doc_no=?,dlnote_date=?,
	dispatch_through=?,destination=?,terms=? where inv_id=?";
	db_q($db_conn,$update_DLNote,[$dl_note,$mode_pmnt,$ref_no,$ref_date,$ot_ref,$order_no,$dated,$dispatch_doc_no,$dlnote_date,$dispatch_through,$destination,$terms,$inv_id]);
	
	}
	
	
	if($inv_table=="user")
	{
		$printurl="user-invoice-print";
	}
	else if($inv_table=="shop")
	{
		$printurl="shop-user-invoice-print";
	}
	else
	{
		$printurl="customer-user-invoice-print";
	}
	
	echo "<script>window.location='$printurl?invoiceid=".base64_encode($inv_id)."';</script>";
	
}else{ echo "<script>window.location='dashboard';</script>";}
?>