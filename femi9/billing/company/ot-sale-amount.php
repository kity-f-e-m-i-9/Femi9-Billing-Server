<?php include("checksession.php");
error_reporting(0);
date_default_timezone_set("Asia/kolkata");
$today=date("Y-m-d");

$tempid=base64_decode($_REQUEST['tempid']);
$select_product_list="update ot_sales set amount_received='1',amount_date='$today' where tempid='$tempid'";
mysqli_query($db_conn,$select_product_list);

$_SESSION['sucMessage']="Amount Received Updated.";
?>
<script>
opener.location.reload(true);
     self.close();
	 </script>
