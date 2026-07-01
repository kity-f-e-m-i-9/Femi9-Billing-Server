<?php include("checksession.php");
error_reporting(0);

$reqid=$_REQUEST['reqid'];
$reqid_decode=base64_decode($reqid);

	$select_reqdetaiis="select * from stock_request where reqid='$reqid_decode'";
	$fetch_reqdetaiis=mysqli_query($db_conn,$select_reqdetaiis);
	$result_reqdetaiis=mysqli_fetch_array($fetch_reqdetaiis);

if($result_reqdetaiis['screenshot']!="Nil"){
	unlink("screenshot/".$result_reqdetaiis['screenshot']."");
}
	
	if($result_reqdetaiis['screenshot2']!="Nil"){
	unlink("screenshot/".$result_reqdetaiis['screenshot2']."");
	}
	

$del_product="delete from stock_request where reqid='$reqid_decode'";
mysqli_query($db_conn,$del_product);

$del_product12="delete from stock_request_items where reqid='$reqid_decode'";
mysqli_query($db_conn,$del_product12);

echo "<script>window.location='stock-request-manage.php?deletedDone';</script>";
?>