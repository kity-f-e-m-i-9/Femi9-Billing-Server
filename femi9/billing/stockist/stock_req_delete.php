<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
error_reporting(0);

$reqid=$_REQUEST['reqid'];
$reqid_decode=base64_decode($reqid);

$select_reqdetaiis="select * from stock_request where reqid=?";
	$fetch_reqdetaiis=db_q($db_conn,$select_reqdetaiis,[$reqid_decode]);
	$result_reqdetaiis=mysqli_fetch_array($fetch_reqdetaiis);
	
	if($result_reqdetaiis['screenshot']!="Nil"){
	unlink("screenshot/".$result_reqdetaiis['screenshot']."");
}
	
	if($result_reqdetaiis['screenshot2']!="Nil"){
	unlink("screenshot/".$result_reqdetaiis['screenshot2']."");
	}

$del_product="delete from stock_request where reqid=?";
db_q($db_conn,$del_product,[$reqid_decode]);

$del_product12="delete from stock_request_items where reqid=?";
db_q($db_conn,$del_product12,[$reqid_decode]);

echo "<script>window.location='stock-request-manage.php?deletedDone';</script>";
?>